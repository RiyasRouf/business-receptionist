<?php

namespace App\Modules\ConversationEngine\Services;

use App\Modules\ConversationEngine\ValueObjects\Intent;

/**
 * Keyword-heuristic classifier — NOT the intended production path.
 * AI_ARCHITECTURE.md's pipeline expects semantic intent classification
 * via the AI provider, but real chat generation is currently blocked
 * (Gemini quota/billing gate, see Module 5). This heuristic is the
 * interim implementation so the Conversation Engine is fully wired and
 * testable now; swap for AI-based classification once chat access is
 * unblocked — the ConversationEngine call site doesn't need to change,
 * only this class's internals.
 */
class IntentClassifierService
{
    private const ESCALATION_PHRASES = [
        'talk to a human', 'speak to someone', 'speak to a person',
        'real person', 'human please', 'talk to staff', 'speak to staff',
        'representative', 'transfer me', 'connect me to',
    ];

    public function classify(string $input): Intent
    {
        $normalised = mb_strtolower(trim($input));

        foreach (self::ESCALATION_PHRASES as $phrase) {
            if (str_contains($normalised, $phrase)) {
                return Intent::Escalation;
            }
        }

        if ($normalised === '') {
            return Intent::OutOfScope;
        }

        // Default to enquiry — genuine off-topic/no-match input is caught
        // downstream by GuardrailService::postCheck's no-fabrication rule
        // (KR-2, KR-3), not by pretending this heuristic can reliably
        // detect "off-topic" on its own.
        return Intent::Enquiry;
    }
}
