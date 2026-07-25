<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\TenantIntegration;
use App\Modules\ConversationEngine\Services\ConversationEngine;
use App\Modules\CorePlatform\Http\ApiResponse;
use App\Modules\CorePlatform\Http\LogsAudit;
use App\Modules\CorePlatform\Services\Providers\InfobipVoiceService;
use App\Modules\CorePlatform\Services\Providers\ProviderTestLogger;
use App\Modules\CorePlatform\Services\Providers\TwilioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

/**
 * Live provider actions for tenant Voice/WhatsApp — every endpoint hits
 * the real provider (Twilio, or Infobip for voice) and logs to
 * provider_test_logs. Reachable both as tenant self-service and
 * platform-assist (route tenant_id). Voice methods dispatch on
 * $integration->voice_provider; WhatsApp is Twilio-only for now.
 */
class IntegrationTestController
{
    use ApiResponse;
    use LogsAudit;

    private const INFOBIP_CALL_PTR_TTL = 14400;

    public function __construct(
        private readonly TwilioService $twilio,
        private readonly InfobipVoiceService $infobip,
        private readonly ConversationEngine $engine,
        private readonly ProviderTestLogger $logger,
    ) {
    }

    private function integration(Request $request, ?string $tenantId): TenantIntegration
    {
        $tid = $tenantId ?? $request->attributes->get('auth_tenant_id');

        return TenantIntegration::firstOrCreate(['tenant_id' => $tid]);
    }

    // Always 200 with ok/status inside — a failed provider test is a
    // successful API call; the envelope's error path is for our own faults.
    private function respond(array $result): JsonResponse
    {
        return $this->success($result);
    }

    public function verifyVoice(Request $request, ?string $tenantId = null): JsonResponse
    {
        $i = $this->integration($request, $tenantId);
        if ($i->voice_provider === 'infobip') {
            return $this->respond($this->infobip->verify($i));
        }

        return $this->respond($this->twilio->verify('voice', $i));
    }

    public function verifyWhatsapp(Request $request, ?string $tenantId = null): JsonResponse
    {
        return $this->respond($this->twilio->verify('whatsapp', $this->integration($request, $tenantId)));
    }

    public function balance(Request $request, string $scope, ?string $tenantId = null): JsonResponse
    {
        abort_unless(in_array($scope, ['voice', 'whatsapp'], true), 404);

        return $this->respond($this->twilio->balance($scope, $this->integration($request, $tenantId)));
    }

    public function testCall(Request $request, ?string $tenantId = null): JsonResponse
    {
        $validated = $request->validate(['to' => ['required', 'string', 'max:32']]);
        $i = $this->integration($request, $tenantId);

        $result = $i->voice_provider === 'infobip'
            ? $this->infobipTestCall($i, $validated['to'])
            : $this->twilio->createTestCall($i, $validated['to'], $this->twilio->generateTwiml($i));

        $this->audit($request, 'integration.test_call', 'tenant_integration', $i->integration_id, ['to' => $validated['to'], 'ok' => $result['ok']], $i->tenant_id);

        return $this->respond($result);
    }

    /**
     * Infobip has no per-call flow-fetch URL like Twilio's TwiML Url — an
     * outbound call only gets webhook events once answered, so the engine
     * session has to be bootstrapped here (same pointer scheme
     * InfobipWebhookController uses for real inbound calls) before the
     * call is placed, keyed by the callId Infobip hands back.
     */
    private function infobipTestCall(TenantIntegration $i, string $to): array
    {
        $result = $this->infobip->createTestCall($i, $to);
        $callId = $result['data']['id'] ?? $result['data']['callId'] ?? null;

        if ($result['ok'] && $callId) {
            $session = $this->engine->startSession($i->tenant_id, 'voice', $i->voice_phone_number ?: 'test-call');
            Redis::setex("infobip:call:{$callId}", self::INFOBIP_CALL_PTR_TTL, $session->session_id);
        }

        return $result;
    }

    public function previewVoice(Request $request, ?string $tenantId = null): JsonResponse
    {
        $validated = $request->validate([
            'to' => ['required', 'string', 'max:32'],
            'voice' => ['required', 'string', 'in:'.implode(',', array_keys(\App\Modules\CorePlatform\Services\Providers\TwilioService::VOICES))],
        ]);
        $i = $this->integration($request, $tenantId);

        return $this->respond($this->twilio->previewVoice($i, $validated['to'], $validated['voice']));
    }

    public function sendTestWhatsapp(Request $request, ?string $tenantId = null): JsonResponse
    {
        $validated = $request->validate([
            'to' => ['required', 'string', 'max:32'],
            'body' => ['sometimes', 'string', 'max:500'],
        ]);
        $i = $this->integration($request, $tenantId);

        $result = $this->twilio->sendTestWhatsApp($i, $validated['to'], $validated['body'] ?? 'Test message from your AI receptionist — WhatsApp integration is working.');
        $this->audit($request, 'integration.test_whatsapp', 'tenant_integration', $i->integration_id, ['to' => $validated['to'], 'ok' => $result['ok']], $i->tenant_id);

        return $this->respond($result);
    }

    public function syncNumbers(Request $request, ?string $tenantId = null): JsonResponse
    {
        $i = $this->integration($request, $tenantId);
        if ($i->voice_provider === 'infobip') {
            return $this->respond($this->infobip->listNumbers($i));
        }

        return $this->respond($this->twilio->listNumbers($i));
    }

    public function wireWebhook(Request $request, ?string $tenantId = null): JsonResponse
    {
        $i = $this->integration($request, $tenantId);
        $result = $i->voice_provider === 'infobip'
            ? $this->infobip->wireVoiceWebhook($i, url('/api/v1/infobip/voice/events'))
            : $this->twilio->wireVoiceWebhook($i, url('/api/v1/twilio/voice'));
        $this->audit($request, 'integration.webhook_wired', 'tenant_integration', $i->integration_id, ['ok' => $result['ok']], $i->tenant_id);

        return $this->respond($result);
    }

    public function twiml(Request $request, ?string $tenantId = null): JsonResponse
    {
        $i = $this->integration($request, $tenantId);

        return $this->success(['twiml' => $this->twilio->generateTwiml($i)]);
    }

    public function logs(Request $request, string $scope, ?string $tenantId = null): JsonResponse
    {
        abort_unless(in_array($scope, ['voice', 'whatsapp'], true), 404);
        $tid = $tenantId ?? $request->attributes->get('auth_tenant_id');

        return $this->success($this->logger->history($scope, $tid));
    }
}
