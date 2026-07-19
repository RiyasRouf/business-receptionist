<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlatformVoiceProvider extends Model
{
    use HasUuids;

    protected $primaryKey = 'provider_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name', 'provider', 'api_key', 'region', 'realtime_endpoint', 'rest_endpoint',
        'speech_model', 'language', 'streaming_enabled', 'diarization', 'smart_formatting',
        'keywords', 'punctuation', 'profanity_filter', 'endpointing_ms',
        'tts_voice', 'tts_model', 'sample_rate', 'audio_encoding',
        'status', 'last_tested_at', 'latency_ms', 'last_error',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'streaming_enabled' => 'boolean',
            'diarization' => 'boolean',
            'smart_formatting' => 'boolean',
            'punctuation' => 'boolean',
            'profanity_filter' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public function toApi(): array
    {
        return array_merge($this->toArray(), ['api_key_set' => filled($this->api_key)]);
    }
}
