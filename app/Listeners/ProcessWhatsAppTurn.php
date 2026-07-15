<?php

namespace App\Listeners;

use App\Models\Session;
use App\Models\Tenant;
use App\Modules\ConversationEngine\Services\ConversationEngine;
use App\Modules\ConversationEngine\ValueObjects\ConversationState;
use App\Modules\WhatsAppAdapter\Contracts\MessagingAdapterInterface;
use App\Modules\WhatsAppAdapter\Events\WhatsAppMessageReceived;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Closes the gap left in Sprint 2 (Module 6B built before Module 7
 * existed): routes an inbound WhatsApp message into the same
 * channel-agnostic ConversationEngine (ADR-041) voice sessions use,
 * then sends the turn's response back over WhatsApp.
 *
 * Multi-tenant WhatsApp routing (which tenant owns which sandbox
 * number) is deferred to the School Admin Portal per D-013 — for the
 * single-sandbox-number MVP this resolves to the one active tenant,
 * same documented simplification already in place elsewhere.
 *
 * Session continuity keys off a Redis phone-hash -> session_id pointer
 * rather than querying Session.caller_number directly, since that
 * column is AES-GCM envelope-encrypted with a random nonce per write
 * (PiiEncryptionService) and is not equality-queryable.
 */
class ProcessWhatsAppTurn
{
    private const SESSION_PTR_TTL = 1800; // matches DATA_ARCHITECTURE §7 active session TTL

    public function __construct(
        private readonly ConversationEngine $engine,
        private readonly MessagingAdapterInterface $adapter,
    ) {}

    public function handle(WhatsAppMessageReceived $event): void
    {
        $message = $event->message;

        $tenant = Tenant::where('status', 'active')->first();

        if ($tenant === null) {
            return;
        }

        $phoneHash = hash('sha256', preg_replace('/\D/', '', $message->from));
        $pointerKey = "whatsapp:session_ptr:{$tenant->tenant_id}:{$phoneHash}";
        $sessionId = Redis::get($pointerKey);

        $session = $sessionId ? Session::find($sessionId) : null;

        if ($session === null || $session->status !== 'active') {
            $session = $this->engine->startSession($tenant->tenant_id, 'whatsapp', $message->from);
        }

        Redis::setex($pointerKey, self::SESSION_PTR_TTL, $session->session_id);

        $result = $this->engine->processTurn($session, $message->body);

        $sendResult = $this->adapter->send($message->from, $result->response);

        if (! $sendResult->success) {
            Log::error('whatsapp.send_failed', [
                'session_id' => $session->session_id,
                'to' => $message->from,
                'error' => $sendResult->error,
            ]);
        }

        if (in_array($result->state, [ConversationState::Confirming, ConversationState::Escalating], true)) {
            $this->engine->completeSession($session);
            Redis::del($pointerKey);
        }
    }
}
