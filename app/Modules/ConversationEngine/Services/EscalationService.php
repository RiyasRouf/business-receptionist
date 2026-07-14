<?php

namespace App\Modules\ConversationEngine\Services;

use App\Models\Session;
use App\Modules\ConversationEngine\ValueObjects\EscalationTrigger;
use App\Modules\OutboxRelay\Services\OutboxService;
use Illuminate\Support\Facades\DB;

/**
 * Escalation Pipeline (ADR-045, AI_ARCHITECTURE.md §9):
 * lead captured (if not already, caller's responsibility before calling
 * this) -> handoff message -> call.escalated event -> session status
 * updated, all in one transaction (ADR-033 atomic outbox write).
 */
class EscalationService
{
    public function __construct(private readonly OutboxService $outbox) {}

    public function escalate(Session $session, EscalationTrigger $trigger): void
    {
        DB::transaction(function () use ($session, $trigger) {
            $session->status = 'escalated';
            $session->save();

            $this->outbox->write(
                tenantId: $session->tenant_id,
                eventType: 'call.escalated',
                payload: ['trigger' => $trigger->value],
                sessionId: $session->session_id,
            );
        });
    }
}
