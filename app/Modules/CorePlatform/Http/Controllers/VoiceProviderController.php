<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\PlatformVoiceProvider;
use App\Modules\CorePlatform\Http\ApiResponse;
use App\Modules\CorePlatform\Http\LogsAudit;
use App\Modules\CorePlatform\Services\Providers\DeepgramService;
use App\Modules\CorePlatform\Services\Providers\ProviderTestLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform-owned Voice AI configuration (Deepgram STT/TTS). platform_admin only.
 */
class VoiceProviderController
{
    use ApiResponse;
    use LogsAudit;

    private const RULES = [
        'name' => ['required', 'string', 'max:255'],
        'provider' => ['sometimes', 'string', 'in:deepgram'],
        'api_key' => ['sometimes', 'nullable', 'string', 'max:500'],
        'region' => ['sometimes', 'nullable', 'string', 'max:64'],
        'realtime_endpoint' => ['sometimes', 'string', 'max:255'],
        'rest_endpoint' => ['sometimes', 'string', 'max:255'],
        'speech_model' => ['sometimes', 'string', 'max:64'],
        'language' => ['sometimes', 'string', 'max:16'],
        'streaming_enabled' => ['sometimes', 'boolean'],
        'diarization' => ['sometimes', 'boolean'],
        'smart_formatting' => ['sometimes', 'boolean'],
        'keywords' => ['sometimes', 'nullable', 'string', 'max:2000'],
        'punctuation' => ['sometimes', 'boolean'],
        'profanity_filter' => ['sometimes', 'boolean'],
        'endpointing_ms' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000'],
        'tts_voice' => ['sometimes', 'string', 'max:64'],
        'tts_model' => ['sometimes', 'string', 'max:64'],
        'sample_rate' => ['sometimes', 'integer', 'in:8000,16000,24000,48000'],
        'audio_encoding' => ['sometimes', 'string', 'in:linear16,mulaw,alaw,mp3,opus,flac,aac'],
    ];

    public function __construct(
        private readonly DeepgramService $deepgram,
        private readonly ProviderTestLogger $logger,
    ) {
    }

    public function index(): JsonResponse
    {
        return $this->success(PlatformVoiceProvider::orderBy('created_at')->get()->map->toApi());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(self::RULES);
        $provider = PlatformVoiceProvider::create($validated);

        $this->audit($request, 'voice_provider.created', 'platform_voice_provider', $provider->provider_id, ['name' => $provider->name]);

        return $this->success($provider->toApi());
    }

    public function update(Request $request, string $providerId): JsonResponse
    {
        $provider = PlatformVoiceProvider::findOrFail($providerId);
        $validated = $request->validate(self::RULES);

        // Blank api_key means "keep existing" — never force re-entry.
        if (blank($validated['api_key'] ?? null)) {
            unset($validated['api_key']);
        }

        $provider->update($validated);
        $this->audit($request, 'voice_provider.updated', 'platform_voice_provider', $provider->provider_id, ['name' => $provider->name]);

        return $this->success($provider->toApi());
    }

    public function destroy(Request $request, string $providerId): JsonResponse
    {
        $provider = PlatformVoiceProvider::findOrFail($providerId);
        $provider->delete();
        $this->audit($request, 'voice_provider.deleted', 'platform_voice_provider', $providerId, []);

        return $this->success(['deleted' => true]);
    }

    // Always 200 with ok/status inside — a failed provider test is a
    // successful API call; the envelope's error path is for our own faults.
    private function respond(array $result): JsonResponse
    {
        return $this->success($result);
    }

    public function verify(string $providerId): JsonResponse
    {
        return $this->respond($this->deepgram->verify(PlatformVoiceProvider::findOrFail($providerId)));
    }

    public function models(string $providerId): JsonResponse
    {
        return $this->respond($this->deepgram->listModels(PlatformVoiceProvider::findOrFail($providerId)));
    }

    public function sttTest(string $providerId): JsonResponse
    {
        return $this->respond($this->deepgram->sttTest(PlatformVoiceProvider::findOrFail($providerId)));
    }

    public function ttsTest(string $providerId): JsonResponse
    {
        return $this->respond($this->deepgram->ttsTest(PlatformVoiceProvider::findOrFail($providerId)));
    }

    public function latencyTest(string $providerId): JsonResponse
    {
        return $this->respond($this->deepgram->latencyTest(PlatformVoiceProvider::findOrFail($providerId)));
    }

    public function logs(): JsonResponse
    {
        return $this->success($this->logger->history('ai_voice', null, 30));
    }
}
