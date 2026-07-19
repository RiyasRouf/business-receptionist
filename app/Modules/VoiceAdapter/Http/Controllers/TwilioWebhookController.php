<?php

namespace App\Modules\VoiceAdapter\Http\Controllers;

use App\Models\TenantIntegration;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Twilio webhook surface. Signature check (X-Twilio-Signature, HMAC-SHA1
 * over URL + sorted POST params with the tenant's auth token) is done
 * inline per-tenant: the tenant is resolved from the called number, and
 * each tenant has their own auth token (Model B — tenant-owned accounts).
 */
class TwilioWebhookController
{
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

        $url = $request->fullUrl();
        $params = $request->post();
        ksort($params);

        $payload = $url;
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

    /** Inbound voice call — answer with the tenant's greeting TwiML. */
    public function voice(Request $request): Response
    {
        $integration = $this->resolveTenant($request);

        if ($integration && ! $this->validSignature($request, $integration->voice_auth_token)) {
            Log::warning('twilio.voice signature rejected', ['tenant_id' => $integration->tenant_id]);

            return response('<Response/>', 403)->header('Content-Type', 'text/xml');
        }

        $this->log($integration?->tenant_id, 'voice.inbound_call', $request);

        $greeting = htmlspecialchars(
            $integration?->fallback_message ?: 'Hello, thank you for calling. Our AI receptionist is being set up. Please try again soon.',
            ENT_XML1
        );

        $stream = '';
        if ($integration?->voice_media_streams_enabled && $integration->voice_stream_url) {
            $url = htmlspecialchars($integration->voice_stream_url, ENT_XML1);
            $stream = "<Connect><Stream url=\"{$url}\"/></Connect>";
        }

        $twiml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><Response><Say voice=\"Polly.Joanna\">{$greeting}</Say>{$stream}</Response>";

        return response($twiml, 200)->header('Content-Type', 'text/xml');
    }

    /** Call status lifecycle callbacks (initiated/ringing/answered/completed). */
    public function status(Request $request): Response
    {
        $integration = $this->resolveTenant($request);
        $this->log($integration?->tenant_id, 'voice.status.'.($request->input('CallStatus') ?? 'unknown'), $request);

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

    /** Inbound WhatsApp message via Twilio. */
    public function whatsappInbound(Request $request): Response
    {
        $integration = $this->resolveTenant($request);

        if ($integration && ! $this->validSignature($request, $integration->whatsapp_auth_token)) {
            Log::warning('twilio.whatsapp signature rejected', ['tenant_id' => $integration->tenant_id]);

            return response('<Response/>', 403)->header('Content-Type', 'text/xml');
        }

        $this->log($integration?->tenant_id, 'whatsapp.inbound_message', $request);

        $greeting = htmlspecialchars(
            $integration?->whatsapp_greeting ?: 'Thanks for your message! Our AI receptionist will reply shortly.',
            ENT_XML1
        );

        return response("<?xml version=\"1.0\" encoding=\"UTF-8\"?><Response><Message>{$greeting}</Message></Response>", 200)
            ->header('Content-Type', 'text/xml');
    }
}
