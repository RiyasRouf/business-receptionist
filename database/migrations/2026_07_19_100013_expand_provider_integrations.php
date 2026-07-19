<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_integrations', function (Blueprint $table) {
            // Voice (Twilio) — full production config
            $table->text('voice_api_key')->nullable();
            $table->text('voice_api_secret')->nullable();
            $table->string('voice_app_sid')->nullable();
            $table->string('voice_region')->nullable();
            $table->boolean('voice_recording_enabled')->default(false);
            $table->unsignedSmallInteger('voice_speech_timeout')->nullable();
            $table->boolean('voice_machine_detection')->default(false);
            $table->boolean('voice_media_streams_enabled')->default(false);
            $table->string('voice_stream_url')->nullable();
            $table->timestampTz('voice_last_tested_at')->nullable();
            $table->unsignedInteger('voice_latency_ms')->nullable();
            $table->text('voice_last_error')->nullable();

            // WhatsApp via Twilio
            $table->string('whatsapp_provider')->default('twilio');
            $table->string('whatsapp_account_sid')->nullable();
            $table->text('whatsapp_auth_token')->nullable();
            $table->text('whatsapp_api_key')->nullable();
            $table->text('whatsapp_api_secret')->nullable();
            $table->string('whatsapp_messaging_service_sid')->nullable();
            $table->boolean('whatsapp_sandbox')->default(true);
            $table->boolean('whatsapp_media_enabled')->default(true);
            $table->boolean('whatsapp_interactive_enabled')->default(true);
            $table->string('whatsapp_status_callback_url')->nullable();
            $table->timestampTz('whatsapp_last_tested_at')->nullable();
            $table->unsignedInteger('whatsapp_latency_ms')->nullable();
            $table->text('whatsapp_last_error')->nullable();
        });

        // Secrets move to encrypted casts — re-encrypt existing plaintext
        // values in place so the model's 'encrypted' cast can read them.
        DB::table('tenant_integrations')->get()->each(function ($row) {
            $updates = [];
            foreach (['voice_auth_token', 'whatsapp_token'] as $col) {
                if (! empty($row->{$col})) {
                    $updates[$col] = Crypt::encryptString($row->{$col});
                }
            }
            if ($updates) {
                DB::table('tenant_integrations')->where('integration_id', $row->integration_id)->update($updates);
            }
        });

        // Column length: encrypted payloads exceed varchar(255)
        DB::statement('ALTER TABLE tenant_integrations ALTER COLUMN voice_auth_token TYPE text');
        DB::statement('ALTER TABLE tenant_integrations ALTER COLUMN whatsapp_token TYPE text');

        Schema::create('platform_voice_providers', function (Blueprint $table) {
            $table->uuid('provider_id')->primary();
            $table->string('name');
            $table->string('provider')->default('deepgram');
            $table->text('api_key')->nullable();
            $table->string('region')->nullable();
            $table->string('realtime_endpoint')->default('wss://api.deepgram.com/v1/listen');
            $table->string('rest_endpoint')->default('https://api.deepgram.com');
            $table->string('speech_model')->default('nova-3');
            $table->string('language')->default('en');
            $table->boolean('streaming_enabled')->default(true);
            $table->boolean('diarization')->default(false);
            $table->boolean('smart_formatting')->default(true);
            $table->text('keywords')->nullable();
            $table->boolean('punctuation')->default(true);
            $table->boolean('profanity_filter')->default(false);
            $table->unsignedSmallInteger('endpointing_ms')->nullable();
            $table->string('tts_voice')->default('aura-2-thalia-en');
            $table->string('tts_model')->default('aura-2');
            $table->unsignedInteger('sample_rate')->default(16000);
            $table->string('audio_encoding')->default('linear16');
            $table->string('status')->default('not_configured');
            $table->timestampTz('last_tested_at')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
        });

        Schema::create('provider_test_logs', function (Blueprint $table) {
            $table->uuid('log_id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->string('provider');
            $table->string('scope');
            $table->string('action');
            $table->boolean('ok');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->jsonb('detail_json')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->uuid('log_id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->string('provider');
            $table->string('event');
            $table->jsonb('payload_json')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('provider_test_logs');
        Schema::dropIfExists('platform_voice_providers');
        Schema::table('tenant_integrations', function (Blueprint $table) {
            $table->dropColumn([
                'voice_api_key', 'voice_api_secret', 'voice_app_sid', 'voice_region',
                'voice_recording_enabled', 'voice_speech_timeout', 'voice_machine_detection',
                'voice_media_streams_enabled', 'voice_stream_url', 'voice_last_tested_at',
                'voice_latency_ms', 'voice_last_error',
                'whatsapp_provider', 'whatsapp_account_sid', 'whatsapp_auth_token',
                'whatsapp_api_key', 'whatsapp_api_secret', 'whatsapp_messaging_service_sid',
                'whatsapp_sandbox', 'whatsapp_media_enabled', 'whatsapp_interactive_enabled',
                'whatsapp_status_callback_url', 'whatsapp_last_tested_at', 'whatsapp_latency_ms',
                'whatsapp_last_error',
            ]);
        });
    }
};
