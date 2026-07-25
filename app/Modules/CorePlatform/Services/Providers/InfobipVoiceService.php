<?php

namespace App\Modules\CorePlatform\Services\Providers;

use App\Models\TenantIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Infobip Calls API wrapper (Http facade — no SDK). Confirmed against
 * live docs 2026-07-25 (infobip.com/docs/api/channels/voice/calls/*),
 * same discipline as TwilioService.
 *
 * Auth: `Authorization: App {apiKey}` header, account-specific base URL
 * (stored per tenant — every Infobip account has its own subdomain).
 *
 * Unlike Twilio's synchronous TwiML response, every call action here is
 * an async REST call keyed by callId; results arrive back as webhook
 * events (see InfobipWebhookController), not inline in the response.
 * Every public method still returns the project-standard uniform result:
 * ['ok' => bool, 'status' => code-string, 'latency_ms' => int, 'data' => mixed].
 */
class InfobipVoiceService
{
    public function __construct(private readonly ProviderTestLogger $logger)
    {
    }

    private function client(TenantIntegration $i): ?PendingRequest
    {
        if (! $i->voice_api_key || ! $i->voice_base_url) {
            return null;
        }

        return Http::withHeaders(['Authorization' => 'App '.$i->voice_api_key])
            ->baseUrl(rtrim($i->voice_base_url, '/'))
            ->timeout(15);
    }

    private function classify(int $httpStatus): string
    {
        return match (true) {
            $httpStatus === 200 || $httpStatus === 201 => 'connected',
            $httpStatus === 401 => 'authentication_failed',
            $httpStatus === 403 => 'authentication_failed',
            $httpStatus === 404 => 'not_found',
            default => 'error_'.$httpStatus,
        };
    }

    private function run(string $action, TenantIntegration $i, callable $call): array
    {
        $client = $this->client($i);

        if ($client === null) {
            return ['ok' => false, 'status' => 'missing_credentials', 'latency_ms' => null, 'data' => null];
        }

        $start = hrtime(true);

        try {
            $response = $call($client);
            $latency = (int) ((hrtime(true) - $start) / 1e6);
            $body = $response->json() ?? [];
            $ok = $response->successful();
            $status = $this->classify($response->status());

            $result = ['ok' => $ok, 'status' => $status, 'latency_ms' => $latency, 'data' => $body];
        } catch (\Throwable $e) {
            $latency = (int) ((hrtime(true) - $start) / 1e6);
            $result = ['ok' => false, 'status' => 'unreachable', 'latency_ms' => $latency, 'data' => ['error' => $e->getMessage()]];
        }

        $this->logger->log('infobip', 'voice', $action, $result['ok'], $result['latency_ms'], [
            'status' => $result['status'],
            'error' => $result['ok'] ? null : ($result['data']['requestError']['serviceException']['text'] ?? $result['data']['error'] ?? null),
        ], $i->tenant_id);

        $i->forceFill([
            'voice_last_tested_at' => now(),
            'voice_latency_ms' => $result['latency_ms'],
            'voice_last_error' => $result['ok'] ? null : ($result['data']['requestError']['serviceException']['text'] ?? $result['data']['error'] ?? $result['status']),
        ])->save();

        return $result;
    }

    /** Ensure a Calls Configuration exists for this tenant, creating one on first use. */
    public function verify(TenantIntegration $i): array
    {
        if ($i->voice_account_sid) {
            $result = $this->run('verify', $i, fn ($c) => $c->get("/calls/1/configurations/{$i->voice_account_sid}"));
            if ($result['ok']) {
                return $result;
            }
            // Stored config id no longer resolves (deleted on the Infobip
            // side, or never actually existed) — fall through and recreate.
        }

        $result = $this->run('verify', $i, fn ($c) => $c->post('/calls/1/configurations', [
            'name' => 'aiwa-'.Str::slug($i->tenant_id),
        ]));

        if ($result['ok'] && ($result['data']['id'] ?? null)) {
            $i->forceFill(['voice_account_sid' => $result['data']['id']])->save();
        }

        return $result;
    }

    public function ensureWebhookSecret(TenantIntegration $i): string
    {
        if (! $i->voice_webhook_secret) {
            $i->forceFill(['voice_webhook_secret' => Str::random(40)])->save();
        }

        return $i->voice_webhook_secret;
    }

    /**
     * Programmatic webhook registration needs the Subscriptions API,
     * whose exact request schema couldn't be confirmed live (docs page
     * 404s as of 2026-07-25) — rather than guess and risk a silently
     * broken integration, this ensures the Calls Configuration exists and
     * hands back the exact webhook URL + event list for the tenant to
     * paste into the Infobip Portal (Calls Configuration > Webhook),
     * which is a documented, always-available path. Revisit once the
     * Subscriptions API create-subscription schema is verified against a
     * live account.
     */
    public function wireVoiceWebhook(TenantIntegration $i, string $webhookUrl): array
    {
        $configResult = $this->verify($i);
        if (! $configResult['ok']) {
            return $configResult;
        }

        $secret = $this->ensureWebhookSecret($i);

        return [
            'ok' => true,
            'status' => 'manual_step_required',
            'latency_ms' => $configResult['latency_ms'],
            'data' => [
                'calls_configuration_id' => $i->voice_account_sid,
                'webhook_url' => $webhookUrl.'?key='.$secret,
                'events' => ['CALL_RECEIVED', 'CALL_ESTABLISHED', 'SPEECH_CAPTURED', 'SAY_FINISHED', 'CALL_FINISHED', 'CALL_FAILED'],
                'instructions' => 'In the Infobip Portal, open this Calls Configuration and set the above URL as its webhook for the listed events. Then link your voice number to this configuration (Numbers > your number > Voice > Forward to subscription).',
            ],
        ];
    }

    public function createTestCall(TenantIntegration $i, string $to): array
    {
        $configResult = $this->verify($i);
        if (! $configResult['ok']) {
            return $configResult;
        }

        return $this->run('test_call', $i, fn ($c) => $c->post('/calls/1/calls', [
            'endpoint' => ['type' => 'PHONE', 'phoneNumber' => ltrim($to, '+')],
            'from' => ltrim((string) $i->voice_phone_number, '+'),
            'callsConfigurationId' => $i->voice_account_sid,
        ]));
    }

    public function listNumbers(TenantIntegration $i): array
    {
        // Not implemented: Infobip Numbers API forwarding-config endpoint
        // couldn't be confirmed live — number linking is the manual portal
        // step called out in wireVoiceWebhook() above.
        return ['ok' => false, 'status' => 'not_supported', 'latency_ms' => null, 'data' => null];
    }

    public function answer(TenantIntegration $i, string $callId): array
    {
        return $this->run('answer', $i, fn ($c) => $c->post("/calls/1/calls/{$callId}/answer"));
    }

    public function say(TenantIntegration $i, string $callId, string $text): array
    {
        return $this->run('say', $i, fn ($c) => $c->post("/calls/1/calls/{$callId}/say", [
            'text' => $text,
            'language' => 'en',
        ]));
    }

    public function captureSpeech(TenantIntegration $i, string $callId): array
    {
        $timeout = min(60, max(5, (int) ($i->voice_speech_timeout ?: 10)));

        return $this->run('capture_speech', $i, fn ($c) => $c->post("/calls/1/calls/{$callId}/capture/speech", [
            'language' => 'en-US',
            'timeout' => $timeout,
            'maxSilence' => 3,
            'advancedFormatting' => true,
        ]));
    }

    public function hangup(TenantIntegration $i, string $callId): array
    {
        return $this->run('hangup', $i, fn ($c) => $c->post("/calls/1/calls/{$callId}/hangup"));
    }
}
