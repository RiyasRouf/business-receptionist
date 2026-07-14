<?php

namespace App\Modules\OutboxRelay\Console;

use App\Models\Outbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Relay process — the only writer of outbox.status (ADR-056). Polls
 * pending rows, XADDs to a per-tenant Redis Stream, marks published.
 * After MAX_RETRIES, moves to dead_letter instead of retrying forever
 * (ADR-023 DLQ; "manual replay" = an operator resets status back to
 * pending). This is a basic polling relay, not a full consumer-group
 * pipeline with ack tracking — sufficient for MVP volume, documented
 * as a scaling limitation rather than pretending otherwise.
 */
class RelayOutboxCommand extends Command
{
    private const MAX_RETRIES = 5;

    private const BATCH_SIZE = 100;

    protected $signature = 'outbox:relay';

    protected $description = 'Relay pending outbox events to Redis Streams';

    public function handle(): int
    {
        $rows = Outbox::where('status', 'pending')
            ->orderBy('created_at')
            ->limit(self::BATCH_SIZE)
            ->get();

        $published = 0;
        $failed = 0;

        foreach ($rows as $row) {
            try {
                $streamKey = "tenant:{$row->tenant_id}:events";

                Redis::xadd($streamKey, '*', [
                    'event_id' => $row->event_id,
                    'event_type' => $row->event_type,
                    'payload' => json_encode($row->payload),
                ]);

                $row->update(['status' => 'published', 'published_at' => now()]);
                $published++;
            } catch (\Throwable $e) {
                $newRetryCount = $row->retry_count + 1;
                $newStatus = $newRetryCount >= self::MAX_RETRIES ? 'dead_letter' : 'pending';

                $row->update(['retry_count' => $newRetryCount, 'status' => $newStatus]);
                $failed++;

                Log::warning('outbox.relay_failed', [
                    'outbox_id' => $row->outbox_id,
                    'event_type' => $row->event_type,
                    'retry_count' => $newRetryCount,
                    'status' => $newStatus,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Relayed {$published} events, {$failed} failed.");

        return self::SUCCESS;
    }
}
