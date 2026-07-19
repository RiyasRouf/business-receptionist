<?php

namespace App\Modules\VoiceAdapter\Http\Controllers;

use App\Models\Session;
use App\Models\TenantIntegration;
use App\Models\WebhookLog;
use App\Modules\ConversationEngine\Services\ConversationEngine;
use App\Modules\ConversationEngine\ValueObjects\ConversationState;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Twilio webhook surface — the channel adapter between Twilio and the
 * channel-agnostic ConversationEngine (ADR-041). Voice uses Twilio
 * <Gather input="speech"> for STT and <Say> for TTS: no media-stream
 * socket server needed for MVP, the AI turn loop runs over plain
 * webhooks. Session ends (status callback or conversation Confirming/
 * Escalating) trigger completeSession -> outbox call.completed -> the
 * existing consumer generates transcript + summary.
 *
 * Signature check (X-Twilio-Signature, HMAC-SHA1 over URL + sorted POST
 * params with the tenant's own auth token) is per-tenant: tenant is
 * resolved from the called number (Model B — tenant-owned accounts).
 */
class TwilioWebhookController
{
    private const PTR_TTL = 14400; // call/session pointer, outlives any real call

    public function __construct(private readonly ConversationEngine $engine)
    {
    }

    private function resolveTenant(Request $request): ?TenantIntegration
    {
        $called = $request->input('To') ?? $request->input('Called');

        if (! $called) {
            return null;
        }

        $number = str_replace('whatsapp:', '', $called);

        return TenantIntegration::where('voice_phone_number', $number)
            ->orWhere('whatsapp_number', $number)
            ->first();
    }

    private function validSignature(Request $request, ?string $authToken): bool
    {
        // No stored token = nothing to verify against (tenant not fully
        // configured yet) — accept rather than dead-letter their calls.
        if (blank($authToken)) {
            return true;
        }

        $signature = $request->header('X-Twilio-Signature');
        if (! $signature) {
            return false;
        }

        $params = $request->post();
        ksort($params);

        $payload = $request->fullUrl();
        foreach ($params as $key => $value) {
            $payload .= $key.$value;
        }

        return hash_equals(base64_encode(hash_hmac('sha1', $payload, $authToken, true)), $signature);
    }

    private function log(?string $tenantId, string $event, Request $request): void
    {
        WebhookLog::create([
            'tenant_id' => $tenantId,
            'provider' => 'twilio',
            'event' => $event,
            'payload_json' => collect($request->post())->except(['AccountSid'])->all(),
        ]);
    }

    private function twiml(string $inner): Response
    {
        return response('<?xml version="1.0" encoding="UTF-8"?><Response>'.$inner.'</Response>', 200)
            ->header('Content-Type', 'text/xml');
    }

    private function say(string $text): string
    {
        return '<Say voice="Polly.Joanna">'.htmlspecialchars($text, ENT_XML1).'</Say>';
    }

    private function gather(TenantIntegration $integration, string $inner = ''): string
    {
        $timeout = $integration->voice_speech_timeout ? (string) $integration->voice_speech_timeout : 'auto';

        return '<Gather input="speech" action="'.url('/api/v1/twilio/voice/turn').'" method="POST"'
            .' speechTimeout="'.$timeout.'" language="en-US">'.$inner.'</Gather>'
            // Reached only if the caller stays silent through the Gather.
            .$this->say('Thank you for calling. Goodbye.').'<Hangup/>';
    }

    private function sessionForCall(string $callSid): ?Session
    {
        $sessionId = Redis::get("twilio:call:{$callSid}");

        return $sessionId ? Session::find($sessionId) : null;
    }

    /** Inbound voice call — open an engine session, greet, and listen. */
    public function voice(Request $request): Response
    {
        $integration = $this->resolveTenant($request);

        if ($integration && ! $this->validSignature($request, $integration->voice_auth_token)) {
            Log::warning('twilio.voice signature rejected', ['tenant_id' => $integration->tenant_id]);

            return $this->twiml('');
        }

        $this->log($integration?->tenant_id, 'voice.inbound_call', $request);

        if ($integration === null) {
            return $this->twiml($this->say('This number is not yet configured. Please try again later.').'<Hangup/>');
        }

        $session = $this->engine->startSession($integration->tenant_id, 'voice', $request->input('From'));
        Redis::setex('twilio:call:'.$request->input('CallSid'), self::PTR_TTL, $session->session_id);

        $greeting = $integration->fallback_message
            ?: 'Hello! Thank you for calling. I am the AI receptionist. How can I help you today?';

        return $this->twiml($this->gather($integration, $this->say($greeting)));
    }

    /** One speech turn: Twilio STT result in, engine answer out, keep listening. */
    public function voiceTurn(Request $request): Response
    {
        $integration = $this->resolveTenant($request);

        if ($integration && ! $this->validSignature($request, $integration->voice_auth_token)) {
            return $this->twiml('');
        }

        $session = $this->sessionForCall((string) $request->input('CallSid'));

        if ($integration === null || $session === null || $session->status !== 'active') {
            return $this->twiml($this->say('Sorry, something went wrong. Please call again.').'<Hangup/>');
        }

        $speech = trim((string) $request->input('SpeechResult', ''));

        if ($speech === '') {
            return $this->twiml($this->gather($integration, $this->say("Sorry, I didn't catch that. Could you repeat?")));
        }

        $result = $this->engine->processTurn($session, $speech);

        // Conversation reached a natural end — say it, hang up, and kick
        // off the post-call pipeline (transcript + summary + lead events).
        if (in_array($result->state, [ConversationState::Confirming, ConversationState::Escalating], true)) {
            $this->engine->completeSession($session);
            Redis::del('twilio:call:'.$request->input('CallSid'));

            return $this->twiml($this->say($result->response).$this->say('Goodbye!').'<Hangup/>');
        }

        return $this->twiml($this->gather($integration, $this->say($result->response)));
    }

    /** Call status lifecycle — completing an active session ends it cleanly. */
    public function status(Request $request): Response
    {
        $integration = $this->resolveTenant($request);
        $callStatus = (string) $request->input('CallStatus', 'unknown');
        $this->log($integration?->tenant_id, "voice.status.{$callStatus}", $request);

        if (in_array($callStatus, ['completed', 'busy', 'failed', 'no-answer', 'canceled'], true)) {
            $session = $this->sessionForCall((string) $request->input('CallSid'));

            if ($session !== null && $session->status === 'active') {
                $this->engine->completeSession($session);
            }
            Redis::del('twilio:call:'.$request->input('CallSid'));
        }

        return response()->noContent();
    }

    /** Recording completion callback. */
    public function recording(Request $request): Response
    {
        $integration = $this->resolveTenant($request);
        $this->log($integration?->tenant_id, 'voice.recording.'.($request->input('RecordingStatus') ?? 'unknown'), $request);

        return response()->noContent();
    }

    /** WhatsApp/SMS delivery status callback. */
    public function messageStatus(Request $request): Response
    {
        $integration = $this->resolveTenant($request);
        $this->log($integration?->tenant_id, 'whatsapp.status.'.($request->input('MessageStatus') ?? 'unknown'), $request);

        return response()->noContent();
    }

    /** Inbound WhatsApp message via Twilio — full AI turn, per-tenant. */
    public function whatsappInbound(Request $request): Response
    {
        $integration = $this->resolveTenant($request);

        if ($integration && ! $this->validSignature($request, $integration->whatsapp_auth_token)) {
            Log::warning('twilio.whatsapp signature rejected', ['tenant_id' => $integration->tenant_id]);

            return $this->twiml('');
        }

        $this->log($integration?->tenant_id, 'whatsapp.inbound_message', $request);

        $body = trim((string) $request->input('Body', ''));

        if ($integration === null || $body === '') {
            return $this->twiml('');
        }

        // Same session-pointer pattern as the 360dialog listener: one
        // active session per (tenant, sender), 30-min sliding window.
        $from = str_replace('whatsapp:', '', (string) $request->input('From'));
        $phoneHash = hash('sha256', preg_replace('/\D/', '', $from));
        $pointerKey = "twilio:wa:{$integration->tenant_id}:{$phoneHash}";

        $sessionId = Redis::get($pointerKey);
        $session = $sessionId ? Session::find($sessionId) : null;

        if ($session === null || $session->status !== 'active') {
            $session = $this->engine->startSession($integration->tenant_id, 'whatsapp', $from);
        }

        Redis::setex($pointerKey, 1800, $session->session_id);

        // First contact + configured greeting: prepend it once.
        $isNew = ($session->metadata_json['turn_count'] ?? 0) === 0;

        $result = $this->engine->processTurn($session, $body);
        $reply = ($isNew && $integration->whatsapp_greeting ? $integration->whatsapp_greeting.' ' : '').$result->response;

        if (in_array($result->state, [ConversationState::Confirming, ConversationState::Escalating], true)) {
            $this->engine->completeSession($session);
            Redis::del($pointerKey);
        }

        return $this->twiml('<Message>'.htmlspecialchars($reply, ENT_XML1).'</Message>');
    }
}
