<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\TenantIntegration;
use App\Modules\CorePlatform\Http\ApiResponse;
use App\Modules\CorePlatform\Http\LogsAudit;
use App\Modules\CorePlatform\Services\Providers\ProviderTestLogger;
use App\Modules\CorePlatform\Services\Providers\TwilioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live provider actions for tenant Voice/WhatsApp — every endpoint hits
 * Twilio for real and logs to provider_test_logs. Reachable both as
 * tenant self-service and platform-assist (route tenant_id).
 */
class IntegrationTestController
{
    use ApiResponse;
    use LogsAudit;

    public function __construct(
        private readonly TwilioService $twilio,
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
        return $this->respond($this->twilio->verify('voice', $this->integration($request, $tenantId)));
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

        $result = $this->twilio->createTestCall($i, $validated['to'], $this->twilio->generateTwiml($i));
        $this->audit($request, 'integration.test_call', 'tenant_integration', $i->integration_id, ['to' => $validated['to'], 'ok' => $result['ok']], $i->tenant_id);

        return $this->respond($result);
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
        return $this->respond($this->twilio->listNumbers($this->integration($request, $tenantId)));
    }

    public function wireWebhook(Request $request, ?string $tenantId = null): JsonResponse
    {
        $i = $this->integration($request, $tenantId);
        $result = $this->twilio->wireVoiceWebhook($i, url('/api/v1/twilio/voice'));
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
