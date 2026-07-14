<?php

namespace App\Modules\Summary\Services;

use App\Models\Session;
use App\Models\Summary;
use App\Models\Transcript;
use App\Modules\AIAdapter\Contracts\AIProviderInterface;
use App\Modules\OutboxRelay\Services\OutboxService;
use Illuminate\Support\Facades\DB;

/**
 * Post-call AI summarisation + structured action items (ADR-007 async
 * post-call pipeline). With MockAIProvider, the "summary" is a
 * deterministic placeholder, not a real distillation — real
 * summarisation needs a working chat provider (blocked, see Module 5).
 * The pipeline (transcript in, summary + action_items stored, event
 * emitted) is fully wired regardless of which provider answers.
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
            ['role' => 'system', 'content' => 'Summarise this school admissions call in 2-3 sentences. Then list any follow-up action items as a short bullet list prefixed with "- ".'],
            ['role' => 'user', 'content' => $transcript->content],
        ];

        $aiResponse = $this->ai->complete($messages);
        [$summaryText, $actionItems] = $this->parseSummary($aiResponse->content);

        return DB::transaction(function () use ($session, $summaryText, $actionItems) {
            $summary = Summary::create([
                'tenant_id' => $session->tenant_id,
                'session_id' => $session->session_id,
                'content' => $summaryText,
                'action_items_json' => $actionItems,
            ]);

            $this->outbox->write(
                tenantId: $session->tenant_id,
                eventType: 'summary.generated',
                payload: ['summary_id' => $summary->summary_id],
                sessionId: $session->session_id,
            );

            return $summary;
        });
    }

    /**
     * @return array{0: string, 1: string[]}
     */
    private function parseSummary(string $raw): array
    {
        $lines = explode("\n", trim($raw));
        $summaryLines = [];
        $actionItems = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, '-')) {
                $actionItems[] = trim(substr($trimmed, 1));
            } elseif ($trimmed !== '') {
                $summaryLines[] = $trimmed;
            }
        }

        return [implode(' ', $summaryLines), $actionItems];
    }
}
