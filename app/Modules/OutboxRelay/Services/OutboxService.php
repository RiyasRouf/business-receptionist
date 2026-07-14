<?php

namespace App\Modules\OutboxRelay\Services;

use App\Models\Outbox;
use Illuminate\Support\Str;

/**
 * Writes an outbox row using the standard event envelope (ADR-012).
 * Caller is responsible for wrapping this in the same DB transaction as
 * the state change it's recording (ADR-033) — this method does not open
 * its own transaction, since Laravel transactions don't nest safely
 * across independent DB::transaction() calls without savepoints, and
 * the whole point is atomicity with the caller's write.
 */
class OutboxService
{
    private const EVENT_VERSION = '1.0';

    private const SCHEMA_VERSION = '1.0';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function write(string $tenantId, string $eventType, array $payload, ?string $sessionId = null): Outbox
    {
        $eventId = (string) Str::uuid();

        $envelope = [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'event_version' => self::EVENT_VERSION,
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'timestamp' => now()->toIso8601String(),
            'schema_version' => self::SCHEMA_VERSION,
            'payload' => $payload,
        ];

        return Outbox::create([
            'event_id' => $eventId,
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'event_version' => self::EVENT_VERSION,
            'payload' => $envelope,
            'status' => 'pending',
        ]);
    }
}
