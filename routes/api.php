<?php

use App\Models\User;
use App\Modules\CorePlatform\Http\Controllers\AiProviderController;
use App\Modules\CorePlatform\Http\Controllers\AuditLogController;
use App\Modules\CorePlatform\Http\Controllers\AuthController;
use App\Modules\CorePlatform\Http\Controllers\BrandingController;
use App\Modules\CorePlatform\Http\Controllers\DashboardController;
use App\Modules\CorePlatform\Http\Controllers\HealthController;
use App\Modules\CorePlatform\Http\Controllers\ReadinessController;
use App\Modules\CorePlatform\Http\Controllers\TenantController;
use App\Modules\CorePlatform\Http\Controllers\TenantIntegrationController;
use App\Modules\CorePlatform\Http\Controllers\TenantRoleController;
use App\Modules\CorePlatform\Http\Controllers\UserController;
use App\Modules\CorePlatform\Http\Controllers\IntegrationTestController;
use App\Modules\CorePlatform\Http\Controllers\VoiceProviderController;
use App\Modules\KnowledgeBase\Http\Controllers\KnowledgeBaseController;
use App\Modules\VoiceAdapter\Http\Controllers\InfobipWebhookController;
use App\Modules\VoiceAdapter\Http\Controllers\TwilioWebhookController;
use App\Modules\LeadCapture\Http\Controllers\LeadController;
use App\Modules\Media\Http\Controllers\TranscriptController;
use App\Modules\WhatsAppAdapter\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/ready', [ReadinessController::class, 'check']);

Route::prefix('v1')->group(function () {
    // Per-IP brute-force cap on top of the per-account lockout — stops
    // credential-spraying across many accounts from one source.
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('throttle:30,1');

    // Meta calls these directly — no JWT, HMAC signature is the auth
    // mechanism (ADR-021). voice_webhook-equivalent system role.
    Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
    Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'receive'])
        ->middleware('whatsapp.signature');

    // Signed URL is its own time-limited auth (ADR-064) — deliberately
    // outside jwt.auth so a link can be shared/opened without a fresh
    // token, but the 'signed' middleware rejects any tampered/expired URL.
    Route::get('/transcripts/{transcript}/download', [TranscriptController::class, 'download'])
        ->middleware('signed')
        ->name('transcripts.download');

    // Unauthenticated — the login page has no tenant/session yet and
    // only needs the platform-wide name/color/tagline/logo to render.
    Route::get('/public/branding', [BrandingController::class, 'platformShow'])->middleware('throttle:60,1');

    // Twilio webhooks — no JWT; per-tenant X-Twilio-Signature HMAC is
    // validated inside the controller (tenant resolved by called number).
    // Throttled per-IP: each inbound turn drives a real (paid) AI call, so
    // an unauthenticated flood here is a cost/DoS vector without a cap.
    Route::middleware('throttle:120,1')->group(function () {
        Route::post('/twilio/voice', [TwilioWebhookController::class, 'voice']);
        Route::post('/twilio/voice/turn', [TwilioWebhookController::class, 'voiceTurn']);
        Route::post('/twilio/status', [TwilioWebhookController::class, 'status']);
        Route::post('/twilio/recording', [TwilioWebhookController::class, 'recording']);
        Route::post('/twilio/whatsapp/inbound', [TwilioWebhookController::class, 'whatsappInbound']);
        Route::post('/twilio/whatsapp/status', [TwilioWebhookController::class, 'messageStatus']);

        // Infobip Calls API webhook — no JWT; per-tenant ?key= secret is
        // validated inside the controller (tenant resolved by
        // callsConfigurationId in the event payload).
        Route::post('/infobip/voice/events', [InfobipWebhookController::class, 'events']);
    });

    // Authenticated surface — per-user cap. Several endpoints (provider
    // verify/test-call/STT/TTS) trigger real, billable outbound API calls,
    // so even an authenticated user shouldn't be able to hammer them.
    Route::middleware(['jwt.auth', 'throttle:120,1'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Role-agnostic — every authenticated user (including
        // platform_admin, who has no tenant_id and would be rejected by
        // tenant.resolve) needs this to bootstrap the app on page load.
        Route::get('/me', function (\Illuminate\Http\Request $request) {
            $user = User::find($request->attributes->get('auth_user_id'));

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $request->attributes->get('auth_user_id'),
                    'tenant_id' => $request->attributes->get('auth_tenant_id'),
                    'role' => $request->attributes->get('auth_role'),
                    'custom_role_id' => $user?->custom_role_id,
                    'permissions' => $user?->custom_role_id
                        ? (\App\Models\TenantRole::find($user->custom_role_id)?->permissions_json ?? [])
                        : null, // null = unrestricted admin, not "no permissions"
                ],
                'trace_id' => app(\App\Modules\CorePlatform\Services\TraceContext::class)->get(),
            ]);
        });

        // Tenant-scoped — everyone in a tenant is tenant_admin at the
        // system-role level now (only 2 system roles exist). Which
        // features they can actually use is gated per-group below by
        // 'permission:X' — unrestricted for custom_role_id = null,
        // checked against tenant_roles.permissions_json otherwise.
        Route::middleware(['tenant.resolve', 'role:'.User::ROLE_TENANT_ADMIN])
            ->group(function () {
                Route::middleware('permission:knowledge_base')->group(function () {
                    Route::get('/kb/documents', [KnowledgeBaseController::class, 'index']);
                    Route::post('/kb/documents', [KnowledgeBaseController::class, 'store']);
                    Route::delete('/kb/documents/{documentId}', [KnowledgeBaseController::class, 'destroy']);
                    Route::post('/kb/search', [KnowledgeBaseController::class, 'search']);
                });

                Route::middleware('permission:leads')->group(function () {
                    Route::get('/leads', [LeadController::class, 'index']);
                    Route::get('/leads/{leadId}', [LeadController::class, 'show']);
                    Route::patch('/leads/{leadId}/status', [LeadController::class, 'updateStatus']);
                    Route::patch('/leads/{leadId}/notes', [LeadController::class, 'updateNotes']);
                });

                Route::middleware('permission:team')->group(function () {
                    Route::get('/team', [UserController::class, 'indexTeam']);
                    Route::post('/team', [UserController::class, 'storeTeam']);
                    Route::get('/roles', [TenantRoleController::class, 'index']);
                    Route::post('/roles', [TenantRoleController::class, 'store']);
                    Route::put('/roles/{roleId}', [TenantRoleController::class, 'update']);
                    Route::delete('/roles/{roleId}', [TenantRoleController::class, 'destroy']);
                });

                // Read-only aggregate stats — no dedicated permission,
                // available to anyone in the tenant.
                Route::get('/dashboard', [DashboardController::class, 'tenant']);

                // Self-service — tenant owns these settings, no separate
                // permission key exists for them yet (admin-level config,
                // same audience as /team).
                Route::get('/integrations', [TenantIntegrationController::class, 'show']);
                Route::put('/integrations/voice', [TenantIntegrationController::class, 'updateVoice']);
                Route::put('/integrations/whatsapp', [TenantIntegrationController::class, 'updateWhatsapp']);
                Route::post('/integrations/voice/verify', [IntegrationTestController::class, 'verifyVoice']);
                Route::post('/integrations/voice/test-call', [IntegrationTestController::class, 'testCall']);
                Route::post('/integrations/voice/preview-voice', [IntegrationTestController::class, 'previewVoice']);
                Route::post('/integrations/voice/sync-numbers', [IntegrationTestController::class, 'syncNumbers']);
                Route::post('/integrations/voice/wire-webhook', [IntegrationTestController::class, 'wireWebhook']);
                Route::get('/integrations/voice/twiml', [IntegrationTestController::class, 'twiml']);
                Route::post('/integrations/whatsapp/verify', [IntegrationTestController::class, 'verifyWhatsapp']);
                Route::post('/integrations/whatsapp/test-send', [IntegrationTestController::class, 'sendTestWhatsapp']);
                Route::get('/integrations/{scope}/balance', [IntegrationTestController::class, 'balance']);
                Route::get('/integrations/{scope}/logs', [IntegrationTestController::class, 'logs']);
                Route::get('/branding', [BrandingController::class, 'tenantShow']);
                Route::put('/branding', [BrandingController::class, 'tenantUpdate']);
                Route::post('/branding/logo', [BrandingController::class, 'tenantUploadLogo']);
                Route::delete('/branding/logo', [BrandingController::class, 'tenantDeleteLogo']);
            });

        // Platform-wide — platform_admin only. No tenant.resolve: a
        // platform admin has no single tenant_id of their own (F-16/F-17).
        Route::middleware('role:'.User::ROLE_PLATFORM_ADMIN)->group(function () {
            Route::get('/admin/tenants', [TenantController::class, 'index']);
            Route::post('/admin/tenants', [TenantController::class, 'store']);
            Route::get('/admin/tenants/{tenantId}', [TenantController::class, 'show']);
            Route::put('/admin/tenants/{tenantId}', [TenantController::class, 'update']);
            Route::put('/admin/tenants/{tenantId}/allowances', [TenantController::class, 'setAllowance']);
            Route::get('/admin/users', [UserController::class, 'indexAdmins']);
            Route::post('/admin/users', [UserController::class, 'storeAdmin']);
            Route::get('/admin/dashboard', [DashboardController::class, 'platform']);

            Route::get('/admin/ai-providers', [AiProviderController::class, 'index']);
            Route::post('/admin/ai-providers', [AiProviderController::class, 'store']);
            Route::put('/admin/ai-providers/{providerId}', [AiProviderController::class, 'update']);
            Route::post('/admin/ai-providers/{providerId}/models', [AiProviderController::class, 'storeModel']);
            Route::get('/admin/ai-providers/{providerId}/available-models', [AiProviderController::class, 'fetchModels']);
            Route::put('/admin/tenants/{tenantId}/ai-assignment', [AiProviderController::class, 'assignTenant']);
            Route::get('/admin/usage-cost', [AiProviderController::class, 'costs']);

            // Voice/WhatsApp assist — same tenant_integrations row the
            // tenant edits themselves, just reachable with a route
            // tenant_id instead of the caller's own auth_tenant_id.
            Route::get('/admin/integrations', [TenantIntegrationController::class, 'platformIndex']);
            Route::get('/admin/tenants/{tenantId}/integrations', [TenantIntegrationController::class, 'show']);
            Route::put('/admin/tenants/{tenantId}/integrations/voice', [TenantIntegrationController::class, 'updateVoice']);
            Route::put('/admin/tenants/{tenantId}/integrations/whatsapp', [TenantIntegrationController::class, 'updateWhatsapp']);
            Route::post('/admin/tenants/{tenantId}/integrations/voice/verify', [IntegrationTestController::class, 'verifyVoice']);
            Route::post('/admin/tenants/{tenantId}/integrations/voice/test-call', [IntegrationTestController::class, 'testCall']);
            Route::post('/admin/tenants/{tenantId}/integrations/voice/preview-voice', [IntegrationTestController::class, 'previewVoice']);
            Route::post('/admin/tenants/{tenantId}/integrations/voice/sync-numbers', [IntegrationTestController::class, 'syncNumbers']);
            Route::post('/admin/tenants/{tenantId}/integrations/voice/wire-webhook', [IntegrationTestController::class, 'wireWebhook']);
            Route::get('/admin/tenants/{tenantId}/integrations/voice/twiml', [IntegrationTestController::class, 'twiml']);
            Route::post('/admin/tenants/{tenantId}/integrations/whatsapp/verify', [IntegrationTestController::class, 'verifyWhatsapp']);
            Route::post('/admin/tenants/{tenantId}/integrations/whatsapp/test-send', [IntegrationTestController::class, 'sendTestWhatsapp']);
            Route::get('/admin/tenants/{tenantId}/integrations/{scope}/logs', [IntegrationTestController::class, 'logs']);

            // Platform-owned Voice AI (Deepgram) config + live tests
            Route::get('/admin/voice-providers', [VoiceProviderController::class, 'index']);
            Route::post('/admin/voice-providers', [VoiceProviderController::class, 'store']);
            Route::put('/admin/voice-providers/{providerId}', [VoiceProviderController::class, 'update']);
            Route::delete('/admin/voice-providers/{providerId}', [VoiceProviderController::class, 'destroy']);
            Route::post('/admin/voice-providers/{providerId}/verify', [VoiceProviderController::class, 'verify']);
            Route::get('/admin/voice-providers/{providerId}/models', [VoiceProviderController::class, 'models']);
            Route::post('/admin/voice-providers/{providerId}/stt-test', [VoiceProviderController::class, 'sttTest']);
            Route::post('/admin/voice-providers/{providerId}/tts-test', [VoiceProviderController::class, 'ttsTest']);
            Route::post('/admin/voice-providers/{providerId}/latency-test', [VoiceProviderController::class, 'latencyTest']);
            Route::get('/admin/voice-providers-logs', [VoiceProviderController::class, 'logs']);

            Route::get('/admin/branding', [BrandingController::class, 'platformShow']);
            Route::put('/admin/branding', [BrandingController::class, 'platformUpdate']);
            Route::post('/admin/branding/logo', [BrandingController::class, 'platformUploadLogo']);
            Route::delete('/admin/branding/logo', [BrandingController::class, 'platformDeleteLogo']);
            Route::get('/admin/tenants/{tenantId}/branding', [BrandingController::class, 'tenantShow']);
            Route::put('/admin/tenants/{tenantId}/branding', [BrandingController::class, 'tenantUpdate']);
            Route::post('/admin/tenants/{tenantId}/branding/logo', [BrandingController::class, 'tenantUploadLogo']);
            Route::delete('/admin/tenants/{tenantId}/branding/logo', [BrandingController::class, 'tenantDeleteLogo']);

            Route::get('/admin/health', [HealthController::class, 'platform']);
            Route::get('/admin/audit-logs', [AuditLogController::class, 'index']);
        });
    });
});
