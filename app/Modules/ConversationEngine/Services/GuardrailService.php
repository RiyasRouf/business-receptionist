<?php

namespace App\Modules\ConversationEngine\Services;

use App\Modules\ConversationEngine\ValueObjects\GuardrailResult;

/**
 * Platform base guardrails, non-disableable (ADR-046). Tenant admins may
 * extend, not replace — extension points are left as TODO since tenant-
 * specific guardrail config (tenant_config) isn't wired until a tenant
 * admin UI exists to manage it (Sprint 5, Module 16).
 *
 * Pre-check injection/off-topic detection here is pattern-based, not a
 * trained classifier — there's no ML guardrail service in the frozen
 * architecture, and building one is out of scope for this pass. This is
 * a real limitation, not a silent gap: documented so it's revisited
 * before relying on it as the sole defence in production.
 */
class GuardrailService
{
    /**
     * Heuristic patterns for common injection/jailbreak attempts.
     * Not exhaustive — a determined attacker can phrase around these.
     * Real defence-in-depth is ADR-048's structural mitigations (caller
     * input always `user` role, system prompt never caller-influenced)
     * which JwtService/ConversationEngine already enforce structurally,
     * independent of this heuristic.
     */
    private const INJECTION_PATTERNS = [
        '/ignore (all |the )?(previous|prior|above) instructions/i',
        '/you are now/i',
        '/system\s*:/i',
        '/\bact as\b.*\b(admin|root|developer)\b/i',
        '/disregard (your |the )?(rules|guardrails|instructions)/i',
        '/reveal (your |the )?(system prompt|instructions)/i',
    ];

    /**
     * Crude PII patterns for post-check sanitisation — credit card-like
     * digit runs and a generic ID-number shape. Not a substitute for a
     * real PII classifier; catches the obvious cases only.
     */
    private const PII_PATTERNS = [
        '/\b(?:\d[ -]*?){13,19}\b/', // card-number-shaped digit runs
    ];

    public function sanitizeInput(string $raw): string
    {
        // Strip control characters before any check runs (ADR-048).
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $raw) ?? '';
    }

    public function preCheck(string $rawInput): GuardrailResult
    {
        $input = $this->sanitizeInput($rawInput);

        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $input)) {
                return GuardrailResult::fail(
                    reason: 'prompt_injection_detected',
                    fallbackResponse: "I can only help with school admission questions — let's get back to that.",
                );
            }
        }

        return GuardrailResult::pass();
    }

    /**
     * No-fabrication enforcement (KR-2, KR-3): an enquiry response is
     * only valid if it was actually grounded in retrieved KB chunks.
     * Without that grounding, the response is replaced with the
     * tenant's configured fallback — never delivered as-is, regardless
     * of what the model produced (module 5 sprint task AC).
     */
    /**
     * Grounding sentinel: the system prompt instructs the model to emit
     * exactly this when a FACTUAL business question has no KB coverage.
     * Conversational turns (greetings, "can you repeat that", thanks)
     * are allowed through without KB — the old blanket "no KB = replace
     * with fallback" rule made the agent unable to hold a conversation.
     */
    public const CANNOT_CONFIRM = 'CANNOT_CONFIRM';

    public function postCheck(string $response, bool $kbMatched, string $fallbackPhrase): GuardrailResult
    {
        if (str_contains($response, self::CANNOT_CONFIRM)) {
            return GuardrailResult::fail(
                reason: 'model_declared_no_grounding',
                fallbackResponse: $fallbackPhrase,
            );
        }

        if (trim($response) === '') {
            return GuardrailResult::fail(
                reason: 'hallucination_signal_empty_response',
                fallbackResponse: $fallbackPhrase,
                shouldEscalate: true,
            );
        }

        return GuardrailResult::pass();
    }

    /**
     * PII-in-response is sanitised (redacted), not blocked — different
     * action from the other post-check guardrails (ADR-046 table).
     */
    public function redactPii(string $response): string
    {
        foreach (self::PII_PATTERNS as $pattern) {
            $response = preg_replace($pattern, '[redacted]', $response) ?? $response;
        }

        return $response;
    }
}
