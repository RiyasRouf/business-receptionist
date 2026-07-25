<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TenantIntegration extends Model
{
    use HasUuids;

    protected $primaryKey = 'integration_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'voice_provider', 'voice_account_sid', 'voice_auth_token', 'voice_phone_number',
        'voice_status', 'call_forwarding_type', 'business_hours', 'fallback_message',
        'voice_api_key', 'voice_api_secret', 'voice_app_sid', 'voice_region',
        'voice_base_url', 'voice_webhook_secret',
        'voice_recording_enabled', 'voice_speech_timeout', 'voice_machine_detection',
        'voice_media_streams_enabled', 'voice_stream_url', 'voice_last_tested_at',
        'voice_latency_ms', 'voice_last_error', 'voice_tts_voice',
        'whatsapp_number', 'whatsapp_display_name', 'whatsapp_phone_number_id', 'whatsapp_token',
        'whatsapp_greeting', 'whatsapp_status',
        'whatsapp_provider', 'whatsapp_account_sid', 'whatsapp_auth_token', 'whatsapp_api_key',
        'whatsapp_api_secret', 'whatsapp_messaging_service_sid', 'whatsapp_sandbox',
        'whatsapp_media_enabled', 'whatsapp_interactive_enabled', 'whatsapp_status_callback_url',
        'whatsapp_last_tested_at', 'whatsapp_latency_ms', 'whatsapp_last_error',
    ];

    protected $hidden = [
        'voice_auth_token', 'voice_api_secret', 'voice_api_key', 'voice_webhook_secret',
        'whatsapp_token', 'whatsapp_auth_token', 'whatsapp_api_secret', 'whatsapp_api_key',
    ];

    protected function casts(): array
    {
        return [
            'voice_auth_token' => 'encrypted',
            'voice_api_key' => 'encrypted',
            'voice_api_secret' => 'encrypted',
            'voice_webhook_secret' => 'encrypted',
            'whatsapp_token' => 'encrypted',
            'whatsapp_auth_token' => 'encrypted',
            'whatsapp_api_key' => 'encrypted',
            'whatsapp_api_secret' => 'encrypted',
            'voice_recording_enabled' => 'boolean',
            'voice_machine_detection' => 'boolean',
            'voice_media_streams_enabled' => 'boolean',
            'whatsapp_sandbox' => 'boolean',
            'whatsapp_media_enabled' => 'boolean',
            'whatsapp_interactive_enabled' => 'boolean',
            'voice_last_tested_at' => 'datetime',
            'whatsapp_last_tested_at' => 'datetime',
        ];
    }

    /** Serialized shape for the admin UI: secrets stay hidden, presence flags added. */
    public function toApi(): array
    {
        return array_merge($this->toArray(), [
            'voice_auth_token_set' => filled($this->voice_auth_token),
            'voice_api_secret_set' => filled($this->voice_api_secret),
            'voice_webhook_secret_set' => filled($this->voice_webhook_secret),
            'whatsapp_auth_token_set' => filled($this->whatsapp_auth_token),
            'whatsapp_api_secret_set' => filled($this->whatsapp_api_secret),
        ]);
    }
}
