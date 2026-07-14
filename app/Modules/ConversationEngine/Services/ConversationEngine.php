<?php

namespace App\Modules\ConversationEngine\Services;

use App\Models\Session;
use App\Modules\AIAdapter\Contracts\AIProviderInterface;
use App\Modules\ConversationEngine\ValueObjects\ConversationState;
use App\Modules\ConversationEngine\ValueObjects\EscalationTrigger;
use App\Modules\ConversationEngine\ValueObjects\Intent;
use App\Modules\ConversationEngine\ValueObjects\LeadField;
use App\Modules\ConversationEngine\ValueObjects\TurnResult;
use App\Modules\KnowledgeBase\Services\RetrievalService;
use App\Modules\LeadCapture\Services\LeadCaptureService;
use App\Modules\OutboxRelay\Services\OutboxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Channel-agnostic (ADR-041) — receives/emits plain text turns; voice
 * semantics (TwiML, DTMF) live only in VoiceAdapter/WhatsAppAdapter,
 * never here.
 *
 * Field extraction during lead capture is verbatim capture of whatever
 * the caller says in response to a field prompt — not NLU-based
 * extraction from free-form speech. Matches the project's honesty
 * pattern for MVP-scope simplifications (documented, not hidden)
 * rather than a silent limitation.
 */
class ConversationEngine
{
    private const MAX_TURNS = 20; // ADR-045 max turn limit -> automatic escalation
    private const SLIDING_WINDOW = 10; // ADR-052 default 10 turns

    public function __construct(
        private readonly GuardrailService $guardrails,
        private readonly IntentClassifierService $intentClassifier,
        private readonly RetrievalService $retrieval,
        private readonly AIProviderInterface $ai,
        private readonly LeadCaptureService $leadCapture,
        private readonly EscalationService $escalation,
        private readonly OutboxService $outbox,
    ) {}

    public function startSession(string $tenantId, string $channel, ?string $callerNumber = null): Session
    {
        $session = Session::create([
            'tenant_id' => $tenantId,
            'status' => 'active',
            'channel' => $channel,
            'started_at' => now(),
            'caller_number' => $callerNumber,
            'metadata_json' => ['state' => ConversationState::Initiated->value, 'turn_count' => 0],
        ]);

        $session->metadata_json = array_merge($session->metadata_json, ['state' => ConversationState::Greeting->value]);
        $session->save();

        return $session;
    }

    public function processTurn(Session $session, string $rawInput): TurnResult
    {
        $meta = $session->metadata_json ?? [];
        $turnCount = ($meta['turn_count'] ?? 0) + 1;
        $meta['turn_count'] = $turnCount;

        if ($turnCount > self::MAX_TURNS) {
            $this->escalation->escalate($session, EscalationTrigger::MaxTurnLimit);

            return new TurnResult(
                response: "I'll connect you with our team for further help.",
                state: ConversationState::Escalating,
            );
        }

        // Pre-check (ADR-046, ADR-048) — reject before any AI dispatch.
        $preCheck = $this->guardrails->preCheck($rawInput);

        if (! $preCheck->passed) {
            $this->appendTurn($session, 'user', $rawInput);
            $this->appendTurn($session, 'assistant', $preCheck->fallbackResponse);
            $session->update(['metadata_json' => $meta]);

            return new TurnResult(
                response: $preCheck->fallbackResponse,
                state: ConversationState::Listening,
                guardrailTriggered: true,
                guardrailStage: 'pre',
            );
        }

        $sanitised = $this->guardrails->sanitizeInput($rawInput);
        $this->appendTurn($session, 'user', $sanitised);

        // If mid lead-capture, treat this turn as the answer to the
        // field we're currently awaiting rather than reclassifying intent.
        if (($meta['state'] ?? null) === ConversationState::LeadCapture->value && ($meta['awaiting_field'] ?? null)) {
            return $this->handleLeadFieldAnswer($session, $meta, $sanitised);
        }

        $intent = $this->intentClassifier->classify($sanitised);

        if ($intent === Intent::Escalation) {
            $this->escalation->escalate($session, EscalationTrigger::ExplicitRequest);
            $response = "Of course — I'll connect you with our team now.";
            $this->appendTurn($session, 'assistant', $response);
            $meta['state'] = ConversationState::Escalating->value;
            $session->update(['metadata_json' => $meta]);

            return new TurnResult(response: $response, state: ConversationState::Escalating);
        }

        return $this->handleEnquiry($session, $meta, $sanitised);
    }

    private function handleEnquiry(Session $session, array $meta, string $input): TurnResult
    {
        $fallbackPhrase = "I'm not able to confirm that — I'll have our team follow up with you directly.";

        $results = $this->retrieval->retrieve($session->tenant_id, $input, 5);
        $kbMatched = $results !== [];

        $context = $kbMatched
            ? "Knowledge base context:\n".implode("\n", array_column($results, 'content'))
            : '';

        $messages = array_filter([
            ['role' => 'system', 'content' => 'You are a school admissions assistant. Answer only from the provided context. If no context is given, say you cannot confirm and offer a callback. Keep responses to 3 sentences or fewer.'],
            $context !== '' ? ['role' => 'system', 'content' => $context] : null,
            ['role' => 'user', 'content' => $input],
        ]);

        $aiResponse = $this->ai->complete(array_values($messages));

        $postCheck = $this->guardrails->postCheck($aiResponse->content, $kbMatched, $fallbackPhrase);
        $response = $postCheck->passed ? $aiResponse->content : $postCheck->fallbackResponse;
        $response = $this->guardrails->redactPii($response);

        if ($postCheck->shouldEscalate) {
            $this->escalation->escalate($session, EscalationTrigger::GuardrailPostCheckFailure);
            $this->appendTurn($session, 'assistant', $response);
            $meta['state'] = ConversationState::Escalating->value;
            $session->update(['metadata_json' => $meta]);

            return new TurnResult($response, ConversationState::Escalating, ! $postCheck->passed, 'post');
        }

        $this->appendTurn($session, 'assistant', $response);

        // Proactively move to lead capture after answering — matches J1
        // (enquiry answered, then AI asks for details), not intent-driven.
        $lead = $this->leadCapture->getOrCreateForSession($session->tenant_id, $session->session_id);
        $missing = $this->leadCapture->missingMvpFields($lead);

        if ($missing === []) {
            $meta['state'] = ConversationState::Confirming->value;
            $session->update(['metadata_json' => $meta]);

            return new TurnResult($response, ConversationState::Confirming, ! $postCheck->passed, $postCheck->passed ? null : 'post');
        }

        $nextField = $missing[0];
        $prompt = $response.' '.$this->fieldPrompt($nextField);
        $meta['state'] = ConversationState::LeadCapture->value;
        $meta['awaiting_field'] = $nextField->value;
        $session->update(['metadata_json' => $meta]);

        $this->appendTurn($session, 'assistant', $this->fieldPrompt($nextField));

        return new TurnResult($prompt, ConversationState::LeadCapture, ! $postCheck->passed, $postCheck->passed ? null : 'post');
    }

    private function handleLeadFieldAnswer(Session $session, array $meta, string $input): TurnResult
    {
        $field = LeadField::from($meta['awaiting_field']);
        $lead = $this->leadCapture->getOrCreateForSession($session->tenant_id, $session->session_id);
        $lead = $this->leadCapture->captureField($lead, $field, $input);

        $missing = $this->leadCapture->missingMvpFields($lead);

        if ($missing !== []) {
            $nextField = $missing[0];
            $prompt = $this->fieldPrompt($nextField);
            $meta['awaiting_field'] = $nextField->value;
            $session->update(['metadata_json' => $meta]);
            $this->appendTurn($session, 'assistant', $prompt);

            return new TurnResult($prompt, ConversationState::LeadCapture);
        }

        unset($meta['awaiting_field']);
        $meta['state'] = ConversationState::Confirming->value;
        $session->update(['metadata_json' => $meta]);

        $confirmation = $this->buildConfirmation($lead);
        $this->appendTurn($session, 'assistant', $confirmation);

        return new TurnResult($confirmation, ConversationState::Confirming);
    }

    /**
     * F-08: reads back captured details, sets callback expectation.
     * Also completes the session — outbox write in the same transaction
     * as the session status update (ADR-033).
     */
    public function completeSession(Session $session): void
    {
        DB::transaction(function () use ($session) {
            $session->status = 'completed';
            $session->ended_at = now();
            $session->duration_seconds = $session->started_at
                ? (int) $session->started_at->diffInSeconds($session->ended_at)
                : null;
            $session->save();

            $meta = $session->metadata_json ?? [];
            $meta['state'] = ConversationState::Ended->value;
            $session->update(['metadata_json' => $meta]);

            $this->outbox->write(
                tenantId: $session->tenant_id,
                eventType: 'call.completed',
                payload: ['channel' => $session->channel],
                sessionId: $session->session_id,
            );
        });
    }

    private function buildConfirmation(\App\Models\Lead $lead): string
    {
        $fields = $lead->fields_json ?? [];
        $name = $fields[LeadField::ParentName->value] ?? 'there';
        $child = $fields[LeadField::ChildName->value] ?? 'your child';

        return "Thanks, {$name} — I've got everything I need for {$child}'s enquiry. Our admissions team will follow up with you soon.";
    }

    private function fieldPrompt(LeadField $field): string
    {
        return match ($field) {
            LeadField::ParentName => 'Could I get your name, please?',
            LeadField::ParentPhone => "What's the best phone number to reach you on?",
            LeadField::ParentEmail => "And your email address?",
            LeadField::ChildName => "What's your child's name?",
            LeadField::ChildAge => "How old is your child?",
            LeadField::GradeApplyingFor => 'Which grade are you applying for?',
            default => "Could you tell me a bit more about {$field->value}?",
        };
    }

    private function appendTurn(Session $session, string $role, string $content): void
    {
        $key = "tenant:{$session->tenant_id}:session:{$session->session_id}:turns";

        Redis::rpush($key, json_encode(['role' => $role, 'content' => $content]));
        Redis::ltrim($key, -self::SLIDING_WINDOW, -1);
        Redis::expire($key, 1800); // 30 min TTL, matches DATA_ARCHITECTURE §7 Active session TTL
    }
}
