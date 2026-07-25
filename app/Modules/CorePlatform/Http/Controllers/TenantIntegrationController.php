<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Modules\CorePlatform\Http\ApiResponse;
use App\Modules\CorePlatform\Http\LogsAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Voice & WhatsApp — Model B: each tenant owns their own Twilio/Vonage
 * and Meta account, entered here. Platform Admin can "assist" any
 * tenant by hitting the same endpoints with a route tenant_id instead
 * of relying on auth_tenant_id. Not wired into the actual call/message
 * routing yet — this is the config surface only.
 */
class TenantIntegrationController
{
    use ApiResponse;
    use LogsAudit;

    private function tenantId(Request $request, ?string $tenantId): string
    {
        return $tenantId ?? $request->attributes->get('auth_tenant_id');
    }

    public function show(Request $request, ?string $tenantId = null): JsonResponse
    {
        $tid = $this->tenantId($request, $tenantId);
        $integration = TenantIntegration::firstOrCreate(['tenant_id' => $tid]);
        $tenant = Tenant::find($tid);

        return $this->success([
            'integration' => $integration->toApi(),
            'voice_webhook_url' => url('/api/v1/twilio/voice'),
            'voice_status_callback_url' => url('/api/v1/twilio/status'),
            'recording_callback_url' => url('/api/v1/twilio/recording'),
            'whatsapp_webhook_url' => url('/api/v1/twilio/whatsapp/inbound'),
            'whatsapp_status_callback_url' => url('/api/v1/twilio/whatsapp/status'),
        ]);
    }

    public function platformIndex(Request $request): JsonResponse
    {
        $tenants = Tenant::with('integration')->orderBy('name')->get();

        return $this->success($tenants->map(fn (Tenant $t) => [
            'tenant_id' => $t->tenant_id,
            'name' => $t->name,
            'industry' => $t->industry,
            'voice_provider' => $t->integration?->voice_provider,
            'voice_phone_number' => $t->integration?->voice_phone_number,
            'voice_status' => $t->integration?->voice_status ?? 'not_configured',
            'whatsapp_number' => $t->integration?->whatsapp_number,
            'whatsapp_status' => $t->integration?->whatsapp_status ?? 'not_configured',
        ]));
    }

    public function updateVoice(Request $request, ?string $tenantId = null): JsonResponse
    {
        $tid = $this->tenantId($request, $tenantId);

        $validated = $request->validate([
            'voice_provider' => ['required', 'string', 'in:twilio,vonage,infobip'],
            'voice_account_sid' => ['sometimes', 'nullable', 'string', 'max:255'],
            'voice_auth_token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'voice_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'voice_api_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'voice_app_sid' => ['sometimes', 'nullable', 'string', 'max:255'],
            'voice_region' => ['sometimes', 'nullable', 'string', 'in:us1,ie1,au1'],
            'voice_base_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'voice_phone_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'voice_recording_enabled' => ['sometimes', 'boolean'],
            'voice_speech_timeout' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:60'],
            'voice_machine_detection' => ['sometimes', 'boolean'],
            'voice_media_streams_enabled' => ['sometimes', 'boolean'],
            'voice_stream_url' => ['sometimes', 'nullable', 'string', 'max:500', 'starts_with:wss://'],
            'voice_tts_voice' => ['sometimes', 'string', 'in:'.implode(',', array_keys(\App\Modules\CorePlatform\Services\Providers\TwilioService::VOICES))],
            'call_forwarding_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'business_hours' => ['sometimes', 'nullable', 'string', 'max:128'],
            'fallback_message' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        // Blank secrets mean "keep existing" — never blank out on save.
        foreach (['voice_auth_token', 'voice_api_key', 'voice_api_secret'] as $secret) {
            if (blank($validated[$secret] ?? null)) {
                unset($validated[$secret]);
            }
        }

        $validated['voice_status'] = 'configured';

        $integration = TenantIntegration::firstOrCreate(['tenant_id' => $tid]);
        $integration->update($validated);

        $this->audit($request, 'integration.voice_updated', 'tenant_integration', $integration->integration_id, ['voice_provider' => $validated['voice_provider']], $tid);

        return $this->success($integration->toApi());
    }

    public function updateWhatsapp(Request $request, ?string $tenantId = null): JsonResponse
    {
        $tid = $this->tenantId($request, $tenantId);

        $validated = $request->validate([
            'whatsapp_provider' => ['sometimes', 'string', 'in:twilio,meta,360dialog'],
            'whatsapp_account_sid' => ['sometimes', 'nullable', 'string', 'max:255'],
            'whatsapp_auth_token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'whatsapp_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'whatsapp_api_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'whatsapp_messaging_service_sid' => ['sometimes', 'nullable', 'string', 'max:255'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'whatsapp_display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'whatsapp_phone_number_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'whatsapp_token' => ['sometimes', 'nullable', 'string', 'max:500'],
            'whatsapp_greeting' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'whatsapp_sandbox' => ['sometimes', 'boolean'],
            'whatsapp_media_enabled' => ['sometimes', 'boolean'],
            'whatsapp_interactive_enabled' => ['sometimes', 'boolean'],
            'whatsapp_status_callback_url' => ['sometimes', 'nullable', 'string', 'max:500', 'url'],
        ]);

        foreach (['whatsapp_auth_token', 'whatsapp_api_key', 'whatsapp_api_secret', 'whatsapp_token'] as $secret) {
            if (blank($validated[$secret] ?? null)) {
                unset($validated[$secret]);
            }
        }

        $validated['whatsapp_status'] = ($validated['whatsapp_sandbox'] ?? true) ? 'sandbox' : 'production';

        $integration = TenantIntegration::firstOrCreate(['tenant_id' => $tid]);
        $integration->update($validated);

        $this->audit($request, 'integration.whatsapp_updated', 'tenant_integration', $integration->integration_id, [], $tid);

        return $this->success($integration->toApi());
    }
}
