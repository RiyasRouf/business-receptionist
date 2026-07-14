<?php

namespace App\Modules\OutboxRelay\Console;

use App\Models\Session;
use App\Modules\Media\Services\MediaService;
use App\Modules\Summary\Services\SummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Async post-call pipeline consumer (ADR-007). Reads call.completed off
 * the shared platform:events stream and runs Media -> Summary.
 *
 * Basic consumer-group read with per-entry ack, not a full DLQ-on-
 * handler-failure pipeline — a failed entry stays unacked and will be
 * redelivered on next poll (Redis Streams' own pending-entries
 * mechanism), rather than a bespoke retry counter. Documented scaling
 * limitation, same as the relay side (ADR-023).
 */
class ConsumePostCallEventsCommand extends Command
{
    private const GROUP = 'post-call-pipeline';

    private const CONSUMER = 'worker-1';

    protected $signature = 'events:consume-post-call {--once : Process one batch and exit}';

    protected $description = 'Consume call.completed events -> generate transcript + summary';

    public function handle(MediaService $media, SummaryService $summary): int
    {
        $this->ensureGroupExists();

        do {
            $entries = Redis::xreadgroup(
                self::GROUP,
                self::CONSUMER,
                [RelayOutboxCommand::STREAM_KEY => '>'],
                10,
                2000
            );

            // Redis reports back the key name it actually has stored —
            // which is the prefixed one, since Laravel's automatic key
            // prefixing rewrites outgoing command arguments but doesn't
            // strip the prefix back out of returned data structures.
            // Iterate whatever key actually comes back rather than
            // assuming it matches the unprefixed constant (confirmed via
            // a real run: xadd/xgroup/xack all worked against the
            // unprefixed name, but the response array key was prefixed —
            // a naive $entries[STREAM_KEY] lookup silently found nothing).
            $messages = $entries === [] ? [] : reset($entries);

            foreach ($messages as $id => $fields) {
                if (($fields['event_type'] ?? null) !== 'call.completed') {
                    Redis::xack(RelayOutboxCommand::STREAM_KEY, self::GROUP, $id);

                    continue;
                }

                $payload = json_decode($fields['payload'] ?? '{}', true);
                $sessionId = $payload['session_id'] ?? null;
                $session = $sessionId ? Session::find($sessionId) : null;

                if ($session === null) {
                    Log::warning('post_call_pipeline.session_not_found', ['event_id' => $fields['event_id'] ?? null]);
                    Redis::xack(RelayOutboxCommand::STREAM_KEY, self::GROUP, $id);

                    continue;
                }

                try {
                    $transcript = $media->generateTranscript($session);
                    $summary->generateSummary($session, $transcript);
                    Redis::xack(RelayOutboxCommand::STREAM_KEY, self::GROUP, $id);
                    $this->info("Processed call.completed for session {$session->session_id}");
                } catch (\Throwable $e) {
                    Log::error('post_call_pipeline.failed', [
                        'session_id' => $session->session_id,
                        'error' => $e->getMessage(),
                    ]);
                    // Left unacked — redelivered on next poll via XREADGROUP.
                }
            }
        } while (! $this->option('once') && $entries !== []);

        return self::SUCCESS;
    }

    private function ensureGroupExists(): void
    {
        try {
            Redis::xgroup('CREATE', RelayOutboxCommand::STREAM_KEY, self::GROUP, '0', 'MKSTREAM');
        } catch (\Throwable $e) {
            if (! str_contains($e->getMessage(), 'BUSYGROUP')) {
                throw $e;
            }
        }
    }
}
