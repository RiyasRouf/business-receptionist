<?php

namespace App\Modules\Summary\Services;

use App\Models\Session;
use App\Models\Summary;
use App\Models\Transcript;
use App\Modules\AIAdapter\Contracts\AIProviderInterface;
use App\Modules\OutboxRelay\Services\OutboxService;
use Illuminate\Support\Facades\DB;

/**
 * Post-call AI summarisation (ADR-007 async post-call pipeline). The
 * model is asked for strict JSON so the summary lands as structured,
 * queryable data (intent/sentiment/outcome/callback) — with a plain-text
 * fallback parse if the model ignores the format.
 */
class SummaryService
{
    public function __construct(
        private readonly AIProviderInterface $ai,
        private readonly OutboxService $outbox,
    ) {}

    public function generateSummary(Session $session, Transcript $transcript): Summary
    {
        $messages = [
            ['role' => 'system', 'content' => <<<'PROMPT'
You summarise business receptionist conversations. Reply with ONLY a JSON object, no markdown fences, with exactly these keys:
{"summary": "2-3 sentence factual summary of the conversation",
 "caller_intent": "short phrase, e.g. admission enquiry, pricing question, complaint",
 "sentiment": "positive|neutral|negative",
 "outcome": "lead_captured|answered|escalated|incomplete",
 "callback_requested": true|false,
 "action_items": ["short imperative follow-up items, empty array if none"]}
PROMPT],
            ['role' => 'user', 'content' => "Channel: {$session->channel}\n\nTranscript:\n{$transcript->content}"],
        ];

        $aiResponse = $this->ai->complete($messages);
        $parsed = $this->parse($aiResponse->content);

        return DB::transaction(function () use ($session, $parsed) {
            $summary = Summary::create([
                'tenant_id' => $session->tenant_id,
                'session_id' => $session->session_id,
                'content' => $parsed['summary'],
                'action_items_json' => $parsed['action_items'],
                'caller_intent' => $parsed['caller_intent'],
                'sentiment' => $parsed['sentiment'],
                'outcome' => $parsed['outcome'],
                'callback_requested' => $parsed['callback_requested'],
            ]);

            $this->outbox->write(
                tenantId: $session->tenant_id,
                eventType: 'summary.generated',
                payload: ['summary_id' => $summary->summary_id, 'outcome' => $summary->outcome],
                sessionId: $session->session_id,
            );

            return $summary;
        });
    }

    /**
     * @return array{summary: string, caller_intent: ?string, sentiment: ?string, outcome: ?string, callback_requested: bool, action_items: string[]}
     */
    private function parse(string $raw): array
    {
        // Strip accidental markdown fences, then try strict JSON.
        $clean = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($raw)));
        $json = json_decode($clean, true);

        if (is_array($json) && isset($json['summary'])) {
            return [
                'summary' => (string) $json['summary'],
                'caller_intent' => isset($json['caller_intent']) ? substr((string) $json['caller_intent'], 0, 255) : null,
                'sentiment' => in_array($json['sentiment'] ?? null, ['positive', 'neutral', 'negative'], true) ? $json['sentiment'] : null,
                'outcome' => in_array($json['outcome'] ?? null, ['lead_captured', 'answered', 'escalated', 'incomplete'], true) ? $json['outcome'] : null,
                'callback_requested' => (bool) ($json['callback_requested'] ?? false),
                'action_items' => array_values(array_map('strval', (array) ($json['action_items'] ?? []))),
            ];
        }

        // Fallback: legacy "prose + - bullets" parse so a non-JSON reply
        // still produces a usable summary instead of failing the pipeline.
        $summaryLines = [];
        $actionItems = [];

        foreach (explode("\n", trim($raw)) as $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, '-')) {
                $actionItems[] = trim(substr($trimmed, 1));
            } elseif ($trimmed !== '') {
                $summaryLines[] = $trimmed;
            }
        }

        return [
            'summary' => implode(' ', $summaryLines),
            'caller_intent' => null,
            'sentiment' => null,
            'outcome' => null,
            'callback_requested' => false,
            'action_items' => $actionItems,
        ];
    }
}
