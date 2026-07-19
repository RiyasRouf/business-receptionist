<?php

namespace Tests\Feature;

use App\Models\PlatformVoiceProvider;
use App\Models\ProviderTestLog;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Modules\CorePlatform\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function tenantToken(): array
    {
        $tenant = Tenant::create(['slug' => 'test-co', 'name' => 'Test Co', 'status' => 'active']);
        $user = User::create([
            'tenant_id' => $tenant->tenant_id,
            'email' => 'admin@test.co',
            'password' => bcrypt('password'),
            'name' => 'Admin',
            'role' => User::ROLE_TENANT_ADMIN,
            'status' => 'active',
        ]);

        $token = app(JwtService::class)->issueAccessToken($user);

        return [$tenant, ['Authorization' => "Bearer {$token}"]];
    }

    public function test_voice_verify_hits_twilio_and_logs_result(): void
    {
        [$tenant, $headers] = $this->tenantToken();

        TenantIntegration::create([
            'tenant_id' => $tenant->tenant_id,
            'voice_account_sid' => 'ACtest',
            'voice_auth_token' => 'secret-token',
        ]);

        Http::fake([
            'api.twilio.com/*' => Http::response(['friendly_name' => 'Test', 'status' => 'active', 'type' => 'Full'], 200),
        ]);

        $response = $this->postJson('/api/v1/integrations/voice/verify', [], $headers);

        $response->assertOk()->assertJsonPath('data.ok', true)->assertJsonPath('data.status', 'connected');
        $this->assertDatabaseHas('provider_test_logs', ['provider' => 'twilio', 'scope' => 'voice', 'action' => 'verify', 'ok' => true]);
        $this->assertNotNull(TenantIntegration::first()->voice_last_tested_at);
    }

    public function test_voice_verify_reports_auth_failure(): void
    {
        [$tenant, $headers] = $this->tenantToken();

        TenantIntegration::create([
            'tenant_id' => $tenant->tenant_id,
            'voice_account_sid' => 'ACtest',
            'voice_auth_token' => 'bad-token',
        ]);

        Http::fake(['api.twilio.com/*' => Http::response(['code' => 20003, 'message' => 'Authenticate'], 401)]);

        $response = $this->postJson('/api/v1/integrations/voice/verify', [], $headers);

        $response->assertOk()->assertJsonPath('data.ok', false)->assertJsonPath('data.status', 'authentication_failed');
        $this->assertDatabaseHas('provider_test_logs', ['ok' => false]);
    }

    public function test_verify_without_credentials_fails_cleanly(): void
    {
        [, $headers] = $this->tenantToken();

        $response = $this->postJson('/api/v1/integrations/voice/verify', [], $headers);

        $response->assertOk()->assertJsonPath('data.ok', false)->assertJsonPath('data.status', 'missing_credentials');
    }

    public function test_secrets_are_encrypted_at_rest_and_hidden_from_api(): void
    {
        [$tenant, $headers] = $this->tenantToken();

        $this->putJson('/api/v1/integrations/voice', [
            'voice_provider' => 'twilio',
            'voice_account_sid' => 'ACtest',
            'voice_auth_token' => 'super-secret',
        ], $headers)->assertOk();

        $raw = \DB::table('tenant_integrations')->where('tenant_id', $tenant->tenant_id)->value('voice_auth_token');
        $this->assertNotSame('super-secret', $raw);
        $this->assertSame('super-secret', TenantIntegration::first()->voice_auth_token);

        $show = $this->getJson('/api/v1/integrations', $headers)->json('data.integration');
        $this->assertArrayNotHasKey('voice_auth_token', $show);
        $this->assertTrue($show['voice_auth_token_set']);
    }

    public function test_twilio_voice_webhook_returns_twiml_and_logs(): void
    {
        $tenant = Tenant::create(['slug' => 'hook-co', 'name' => 'Hook Co', 'status' => 'active']);
        TenantIntegration::create([
            'tenant_id' => $tenant->tenant_id,
            'voice_phone_number' => '+15550001111',
            'fallback_message' => 'Welcome to Hook Co',
        ]);

        // No auth token stored -> signature check skipped for this tenant
        $response = $this->post('/api/v1/twilio/voice', ['To' => '+15550001111', 'From' => '+15559998888', 'CallSid' => 'CAx']);

        $response->assertOk();
        $this->assertStringContainsString('Welcome to Hook Co', $response->getContent());
        $this->assertDatabaseHas('webhook_logs', ['provider' => 'twilio', 'event' => 'voice.inbound_call', 'tenant_id' => $tenant->tenant_id]);
    }

    public function test_deepgram_verify_and_log(): void
    {
        $admin = User::create([
            'email' => 'pa@test.co', 'password' => bcrypt('x'), 'name' => 'PA',
            'role' => User::ROLE_PLATFORM_ADMIN, 'status' => 'active',
        ]);
        $token = app(JwtService::class)->issueAccessToken($admin);
        $headers = ['Authorization' => "Bearer {$token}"];

        $provider = PlatformVoiceProvider::create(['name' => 'DG', 'api_key' => 'dg-key']);

        Http::fake(['api.deepgram.com/*' => Http::response(['api_key_id' => 'k1', 'scopes' => ['usage:read']], 200)]);

        $response = $this->postJson("/api/v1/admin/voice-providers/{$provider->provider_id}/verify", [], $headers);

        $response->assertOk()->assertJsonPath('data.ok', true);
        $this->assertSame('connected', $provider->fresh()->status);
        $this->assertSame(1, ProviderTestLog::where('provider', 'deepgram')->count());
    }
}
