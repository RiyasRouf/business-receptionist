<?php

namespace App\Modules\VoiceAdapter\Http\Controllers;

use App\Models\Session;
use App\Models\TenantIntegration;
use App\Models\WebhookLog;
use App\Modules\ConversationEngine\Services\ConversationEngine;
use App\Modules\ConversationEngine\ValueObjects\ConversationState;
use App\Modules\CorePlatform\Services\Providers\InfobipVoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Infobip Calls API webhook surface — the channel adapter between
 * Infobip and the channel-agnostic ConversationEngine (ADR-041), mirror
 * of TwilioWebhookController for the Infobip provider.
 *
 * Unlike Twilio (call flow returned synchronously as TwiML), Infobip's
 * Calls API is event-driven: one webhook URL receives a POST per call
 * event (CALL_RECEIVED, CALL_ESTABLISHED, SPEECH_CAPTURED, SAY_FINISHED,
 * CALL_FINISHED, CALL_FAILED) and every reply (answer/say/capture
 * speech/hangup) is a separate outbound REST call keyed by callId. The
 * turn loop is therefore driven by chaining webhook events rather than
 * a single request/response per turn.
 *
 * Auth: tenant is resolved directly from the event's callsConfigurationId
 * (each tenant gets its own Calls Configuration — no number-guessing
 * needed). The webhook URL itself carries a per-tenant ?key= secret
 * (Infobip's own Subscriptions-API signature scheme couldn't be
 * confirmed live — see InfobipVoiceService::wireVoiceWebhook).
 */
class InfobipWebhookController
{
    private const PTR_TTL = 14400; // call/session pointer, outlives any real call

    public function __construct(
        private readonly ConversationEngine $engine,
        private readonly InfobipVoiceService $infobip,
    ) {
    }

    private function resolveTenant(?string $callsConfigurationId): ?TenantIntegration
    {
        if (! $callsConfigurationId) {
            return null;
        }

        return TenantIntegration::where('voice_provider', 'infobip')
            ->where('voice_account_sid', $callsConfigurationId)
            ->first();
    }

    private function validKey(Request $request, ?string $secret): bool
    {
        // No stored secret = webhook not fully wired yet — accept rather
        // than dead-letter (same posture as Twilio's blank-token case).
        if (blank($secret)) {
            return true;
        }

        return hash_equals((string) $secret, (string) $request->query('key'));
    }

    private function log(?string $tenantId, string $event, array $payload): void
    {
        WebhookLog::create([
            'tenant_id' => $tenantId,
            'provider' => 'infobip',
            'event' => $event,
            'payload_json' => $payload,
        ]);
    }

    private function pointerKey(string $callId): string
    {
        return "infobip:call:{$callId}";
    }

    private function endingKey(string $callId): string
    {
        return "infobip:call:{$callId}:ending";
    }

    private function sessionForCall(string $callId): ?Session
    {
        $sessionId = Redis::get($this->pointerKey($callId));

        return $sessionId ? Session::find($sessionId) : null;
    }

    private function cleanup(string $callId): void
    {
        Redis::del($this->pointerKey($callId), $this->endingKey($callId));
    }

    /** Single unified webhook — every Calls API event for this tenant lands here. */
    public function events(Request $request): Response
    {
        $payload = $request->all();
        $type = $payload['type'] ?? null;
        $callId = $payload['callId'] ?? null;
        $integration = $this->resolveTenant($payload['callsConfigurationId'] ?? null);

        if ($integration && ! $this->validKey($request, $integration->voice_webhook_secret)) {
            Log::warning('infobip.voice webhook key rejected', ['tenant_id' => $integration->tenant_id]);

            return response()->noContent();
        }

        $this->log($integration?->tenant_id, 'voice.'.strtolower((string) ($type ?? 'unknown')), $payload);

        if (! $integration || ! $callId || ! $type) {
            return response()->noContent();
        }

        match ($type) {
            'CALL_RECEIVED' => $this->onCallReceived($integration, $callId, $payload),
            'CALL_ESTABLISHED' => $this->onCallEstablished($integration, $callId),
            'SPEECH_CAPTURED' => $this->onSpeechCaptured($integration, $callId, $payload),
            'SAY_FINISHED' => $this->onSayFinished($integration, $callId),
            'CALL_FINISHED', 'CALL_FAILED' => $this->onCallEnded($integration, $callId),
            default => null,
        };

        return response()->noContent();
    }

    private function onCallReceived(TenantIntegration $integration, string $callId, array $payload): void
    {
        // Exact caller-id field name unconfirmed against a live payload
        // (docs show only the generic event envelope) — tried in order of
        // likelihood, falls back to "unknown" rather than failing the call.
        // Verify + correct against WebhookLog on the first real inbound call.
        $from = $payload['from'] ?? $payload['callerNumber'] ?? $payload['caller']['phoneNumber'] ?? 'unknown';

        $session = $this->engine->startSession($integration->tenant_id, 'voice', $from);
        Redis::setex($this->pointerKey($callId), self::PTR_TTL, $session->session_id);

        $this->infobip->answer($integration, $callId);
    }

    private function onCallEstablished(TenantIntegration $integration, string $callId): void
    {
        $session = $this->sessionForCall($callId);
        if ($session === null) {
            return;
        }

        $greeting = $integration->fallback_message
            ?: 'Hello! Thank you for calling. I am the AI receptionist. How can I help you today?';

        $this->infobip->say($integration, $callId, $greeting);
    }

    private function onSpeechCaptured(TenantIntegration $integration, string $callId, array $payload): void
    {
        $session = $this->sessionForCall($callId);
        if ($session === null || $session->status !== 'active') {
            return;
        }

        $speech = trim((string) ($payload['text'] ?? $payload['result']['text'] ?? ''));

        if ($speech === '') {
            $this->infobip->say($integration, $callId, "Sorry, I didn't catch that. Could you repeat?");

            return;
        }

        $result = $this->engine->processTurn($session, $speech);

        if (in_array($result->state, [ConversationState::Confirming, ConversationState::Escalating], true)) {
            Redis::setex($this->endingKey($callId), self::PTR_TTL, '1');
            $this->infobip->say($integration, $callId, $result->response.' Goodbye!');

            return;
        }

        $this->infobip->say($integration, $callId, $result->response);
    }

    /** A say() action just finished — either keep listening or hang up, mirroring Twilio's gather-or-hangup branch. */
    private function onSayFinished(TenantIntegration $integration, string $callId): void
    {
        $session = $this->sessionForCall($callId);
        if ($session === null) {
            return;
        }

        if (Redis::get($this->endingKey($callId))) {
            $this->engine->completeSession($session);
            $this->infobip->hangup($integration, $callId);
            $this->cleanup($callId);

            return;
        }

        $this->infobip->captureSpeech($integration, $callId);
    }

    private function onCallEnded(TenantIntegration $integration, string $callId): void
    {
        $session = $this->sessionForCall($callId);
        if ($session !== null && $session->status === 'active') {
            $this->engine->completeSession($session);
        }
        $this->cleanup($callId);
    }
}
