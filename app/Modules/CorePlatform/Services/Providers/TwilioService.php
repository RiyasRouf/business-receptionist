<?php

namespace App\Modules\CorePlatform\Services\Providers;

use App\Models\TenantIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin Twilio REST v2010 wrapper (Http facade — no SDK dependency).
 * Auth: API key/secret pair when present, else Account SID + auth token.
 * Every public method returns a uniform result array:
 * ['ok' => bool, 'status' => code-string, 'latency_ms' => int, 'data' => mixed].
 */
class TwilioService
{
    private const BASE = 'https://api.twilio.com/2010-04-01';

    public function __construct(private readonly ProviderTestLogger $logger)
    {
    }

    private function client(string $sid, string $secretUser, string $secretPass, ?string $region = null): PendingRequest
    {
        $base = $region && $region !== 'us1'
            ? "https://api.{$region}.twilio.com/2010-04-01"
            : self::BASE;

        return Http::withBasicAuth($secretUser, $secretPass)->baseUrl($base)->timeout(15);
    }

    /** @return array{client: PendingRequest, sid: string}|null */
    private function voiceClient(TenantIntegration $i): ?array
    {
        if (! $i->voice_account_sid) {
            return null;
        }
        if ($i->voice_api_key && $i->voice_api_secret) {
            return ['client' => $this->client($i->voice_account_sid, $i->voice_api_key, $i->voice_api_secret, $i->voice_region), 'sid' => $i->voice_account_sid];
        }
        if ($i->voice_auth_token) {
            return ['client' => $this->client($i->voice_account_sid, $i->voice_account_sid, $i->voice_auth_token, $i->voice_region), 'sid' => $i->voice_account_sid];
        }

        return null;
    }

    private function whatsappClient(TenantIntegration $i): ?array
    {
        if (! $i->whatsapp_account_sid) {
            return null;
        }
        if ($i->whatsapp_api_key && $i->whatsapp_api_secret) {
            return ['client' => $this->client($i->whatsapp_account_sid, $i->whatsapp_api_key, $i->whatsapp_api_secret), 'sid' => $i->whatsapp_account_sid];
        }
        if ($i->whatsapp_auth_token) {
            return ['client' => $this->client($i->whatsapp_account_sid, $i->whatsapp_account_sid, $i->whatsapp_auth_token), 'sid' => $i->whatsapp_account_sid];
        }

        return null;
    }

    private function classify(int $httpStatus, array $body): string
    {
        return match (true) {
            $httpStatus === 200 || $httpStatus === 201 => 'connected',
            $httpStatus === 401 => ($body['code'] ?? null) === 20404 ? 'invalid_sid' : 'authentication_failed',
            $httpStatus === 404 => 'invalid_sid',
            $httpStatus === 400 && str_contains($body['message'] ?? '', 'Token') => 'invalid_token',
            default => 'error_'.$httpStatus,
        };
    }

    private function run(string $scope, string $action, TenantIntegration $i, callable $call): array
    {
        $auth = $scope === 'voice' ? $this->voiceClient($i) : $this->whatsappClient($i);

        if ($auth === null) {
            return ['ok' => false, 'status' => 'missing_credentials', 'latency_ms' => null, 'data' => null];
        }

        $start = hrtime(true);

        try {
            $response = $call($auth['client'], $auth['sid']);
            $latency = (int) ((hrtime(true) - $start) / 1e6);
            $body = $response->json() ?? [];
            $ok = $response->successful();
            $status = $this->classify($response->status(), $body);

            $result = ['ok' => $ok, 'status' => $status, 'latency_ms' => $latency, 'data' => $body];
        } catch (\Throwable $e) {
            $latency = (int) ((hrtime(true) - $start) / 1e6);
            $result = ['ok' => false, 'status' => 'unreachable', 'latency_ms' => $latency, 'data' => ['error' => $e->getMessage()]];
        }

        $this->logger->log('twilio', $scope, $action, $result['ok'], $result['latency_ms'], [
            'status' => $result['status'],
            'error' => $result['ok'] ? null : ($result['data']['message'] ?? $result['data']['error'] ?? null),
        ], $i->tenant_id);

        $prefix = $scope === 'voice' ? 'voice' : 'whatsapp';
        $i->forceFill([
            "{$prefix}_last_tested_at" => now(),
            "{$prefix}_latency_ms" => $result['latency_ms'],
            "{$prefix}_last_error" => $result['ok'] ? null : ($result['data']['message'] ?? $result['data']['error'] ?? $result['status']),
        ])->save();

        return $result;
    }

    /** GET the account itself — validates SID + secret, returns account status/name. */
    public function verify(string $scope, TenantIntegration $i): array
    {
        $result = $this->run($scope, 'verify', $i, fn ($c, $sid) => $c->get("/Accounts/{$sid}.json"));

        if ($result['ok']) {
            $result['data'] = [
                'friendly_name' => $result['data']['friendly_name'] ?? null,
                'account_status' => $result['data']['status'] ?? null,
                'type' => $result['data']['type'] ?? null,
            ];
        }

        return $result;
    }

    /** Account balance — usable as a quota/usage indicator. */
    public function balance(string $scope, TenantIntegration $i): array
    {
        $result = $this->run($scope, 'balance', $i, fn ($c, $sid) => $c->get("/Accounts/{$sid}/Balance.json"));

        if ($result['ok']) {
            $result['data'] = ['balance' => $result['data']['balance'] ?? null, 'currency' => $result['data']['currency'] ?? null];
        }

        return $result;
    }

    public function sendTestWhatsApp(TenantIntegration $i, string $to, string $body): array
    {
        $payload = [
            'To' => 'whatsapp:'.$to,
            'Body' => $body,
        ];
        if ($i->whatsapp_messaging_service_sid) {
            $payload['MessagingServiceSid'] = $i->whatsapp_messaging_service_sid;
        } else {
            $payload['From'] = 'whatsapp:'.$i->whatsapp_number;
        }
        if ($i->whatsapp_status_callback_url) {
            $payload['StatusCallback'] = $i->whatsapp_status_callback_url;
        }

        return $this->run('whatsapp', 'send_test_message', $i,
            fn ($c, $sid) => $c->asForm()->post("/Accounts/{$sid}/Messages.json", $payload));
    }

    public function createTestCall(TenantIntegration $i, string $to, string $twiml): array
    {
        $payload = [
            'To' => $to,
            'From' => $i->voice_phone_number,
            'Twiml' => $twiml,
        ];
        if ($i->voice_machine_detection) {
            $payload['MachineDetection'] = 'Enable';
        }
        if ($i->voice_recording_enabled) {
            $payload['Record'] = 'true';
        }

        return $this->run('voice', 'test_call', $i,
            fn ($c, $sid) => $c->asForm()->post("/Accounts/{$sid}/Calls.json", $payload));
    }

    public function listNumbers(TenantIntegration $i): array
    {
        $result = $this->run('voice', 'sync_numbers', $i,
            fn ($c, $sid) => $c->get("/Accounts/{$sid}/IncomingPhoneNumbers.json", ['PageSize' => 50]));

        if ($result['ok']) {
            $result['data'] = collect($result['data']['incoming_phone_numbers'] ?? [])->map(fn ($n) => [
                'phone_number' => $n['phone_number'],
                'friendly_name' => $n['friendly_name'],
                'voice_url' => $n['voice_url'],
                'capabilities' => $n['capabilities'] ?? null,
            ])->values()->all();
        }

        return $result;
    }

    /** Point the configured Twilio number's Voice webhook at our endpoint. */
    public function wireVoiceWebhook(TenantIntegration $i, string $webhookUrl): array
    {
        $numbers = $this->listNumbers($i);
        if (! $numbers['ok']) {
            return $numbers;
        }

        $match = collect($numbers['data'])->first(fn ($n) => $n['phone_number'] === $i->voice_phone_number);
        if (! $match) {
            return ['ok' => false, 'status' => 'number_not_on_account', 'latency_ms' => null, 'data' => null];
        }

        return $this->run('voice', 'wire_webhook', $i, function ($c, $sid) use ($i, $webhookUrl) {
            $list = $c->get("/Accounts/{$sid}/IncomingPhoneNumbers.json", ['PhoneNumber' => $i->voice_phone_number])->json();
            $numberSid = $list['incoming_phone_numbers'][0]['sid'];

            return $c->asForm()->post("/Accounts/{$sid}/IncomingPhoneNumbers/{$numberSid}.json", [
                'VoiceUrl' => $webhookUrl,
                'VoiceMethod' => 'POST',
            ]);
        });
    }

    public function generateTwiml(TenantIntegration $i): string
    {
        $greeting = htmlspecialchars($i->fallback_message ?: 'Hello. This is a test call from your AI receptionist. Your voice integration is working.', ENT_XML1);

        $stream = '';
        if ($i->voice_media_streams_enabled && $i->voice_stream_url) {
            $url = htmlspecialchars($i->voice_stream_url, ENT_XML1);
            $stream = "<Connect><Stream url=\"{$url}\"/></Connect>";
        }

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?><Response><Say voice=\"Polly.Joanna\">{$greeting}</Say>{$stream}</Response>";
    }
}
