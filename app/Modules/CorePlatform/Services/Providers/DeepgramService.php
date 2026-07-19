<?php

namespace App\Modules\CorePlatform\Services\Providers;

use App\Models\PlatformVoiceProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Deepgram REST wrapper (v1). Uniform result shape identical to
 * TwilioService: ['ok','status','latency_ms','data'].
 */
class DeepgramService
{
    private const STT_SAMPLE_URL = 'https://dpgr.am/spacewalk.wav';

    public function __construct(private readonly ProviderTestLogger $logger)
    {
    }

    private function client(PlatformVoiceProvider $p): PendingRequest
    {
        return Http::withToken($p->api_key, 'Token')
            ->baseUrl(rtrim($p->rest_endpoint ?: 'https://api.deepgram.com', '/'))
            ->timeout(30);
    }

    private function run(PlatformVoiceProvider $p, string $action, callable $call): array
    {
        if (blank($p->api_key)) {
            return ['ok' => false, 'status' => 'missing_credentials', 'latency_ms' => null, 'data' => null];
        }

        $start = hrtime(true);

        try {
            $response = $call($this->client($p));
            $latency = (int) ((hrtime(true) - $start) / 1e6);
            $ok = $response->successful();
            $status = match (true) {
                $ok => 'connected',
                $response->status() === 401 => 'authentication_failed',
                $response->status() === 403 => 'forbidden',
                default => 'error_'.$response->status(),
            };
            $result = ['ok' => $ok, 'status' => $status, 'latency_ms' => $latency, 'data' => $response->json() ?? ['raw_bytes' => strlen($response->body())]];
        } catch (\Throwable $e) {
            $latency = (int) ((hrtime(true) - $start) / 1e6);
            $result = ['ok' => false, 'status' => 'unreachable', 'latency_ms' => $latency, 'data' => ['error' => $e->getMessage()]];
        }

        $this->logger->log('deepgram', 'ai_voice', $action, $result['ok'], $result['latency_ms'], [
            'status' => $result['status'],
            'error' => $result['ok'] ? null : ($result['data']['err_msg'] ?? $result['data']['error'] ?? null),
        ]);

        $p->forceFill([
            'last_tested_at' => now(),
            'latency_ms' => $result['latency_ms'],
            'last_error' => $result['ok'] ? null : ($result['data']['err_msg'] ?? $result['data']['error'] ?? $result['status']),
            'status' => $result['ok'] ? 'connected' : 'error',
        ])->save();

        return $result;
    }

    /** Token introspection — cheapest full auth check. */
    public function verify(PlatformVoiceProvider $p): array
    {
        $result = $this->run($p, 'verify', fn ($c) => $c->get('/v1/auth/token'));

        if ($result['ok']) {
            $result['data'] = [
                'api_key_id' => $result['data']['api_key_id'] ?? null,
                'scopes' => $result['data']['scopes'] ?? [],
            ];
        }

        return $result;
    }

    public function listModels(PlatformVoiceProvider $p): array
    {
        $result = $this->run($p, 'list_models', fn ($c) => $c->get('/v1/models'));

        if ($result['ok']) {
            $result['data'] = [
                'stt' => collect($result['data']['stt'] ?? [])->map(fn ($m) => ['name' => $m['name'] ?? null, 'canonical_name' => $m['canonical_name'] ?? null, 'languages' => $m['languages'] ?? []])->values()->all(),
                'tts' => collect($result['data']['tts'] ?? [])->map(fn ($m) => ['name' => $m['name'] ?? null, 'canonical_name' => $m['canonical_name'] ?? null, 'language' => $m['language'] ?? null])->values()->all(),
            ];
        }

        return $result;
    }

    /** Real prerecorded transcription against Deepgram's public sample file. */
    public function sttTest(PlatformVoiceProvider $p): array
    {
        $query = array_filter([
            'model' => $p->speech_model,
            'language' => $p->language,
            'smart_format' => $p->smart_formatting ? 'true' : null,
            'diarize' => $p->diarization ? 'true' : null,
            'punctuate' => $p->punctuation ? 'true' : null,
            'profanity_filter' => $p->profanity_filter ? 'true' : null,
        ]);

        $result = $this->run($p, 'stt_test', fn ($c) => $c
            ->post('/v1/listen?'.http_build_query($query), ['url' => self::STT_SAMPLE_URL]));

        if ($result['ok']) {
            $alt = $result['data']['results']['channels'][0]['alternatives'][0] ?? [];
            $result['data'] = [
                'transcript' => $alt['transcript'] ?? '',
                'confidence' => $alt['confidence'] ?? null,
                'model' => $result['data']['metadata']['model_info'] ?? null,
                'duration' => $result['data']['metadata']['duration'] ?? null,
            ];
        }

        return $result;
    }

    /** Real TTS synthesis; success = audio bytes returned. */
    public function ttsTest(PlatformVoiceProvider $p): array
    {
        $query = http_build_query(array_filter([
            'model' => $p->tts_voice,
            'encoding' => $p->audio_encoding === 'linear16' ? 'linear16' : $p->audio_encoding,
            'sample_rate' => $p->audio_encoding === 'linear16' ? $p->sample_rate : null,
        ]));

        $result = $this->run($p, 'tts_test', fn ($c) => $c
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('/v1/speak?'.$query, ['text' => 'Your text to speech integration is working correctly.']));

        if ($result['ok']) {
            $result['data'] = ['audio_bytes' => $result['data']['raw_bytes'] ?? 0, 'voice' => $p->tts_voice];
        }

        return $result;
    }

    /** Round-trip latency: token introspection measured 3x, report min/avg. */
    public function latencyTest(PlatformVoiceProvider $p): array
    {
        $samples = [];
        $last = null;

        for ($n = 0; $n < 3; $n++) {
            $last = $this->run($p, 'latency_test', fn ($c) => $c->get('/v1/auth/token'));
            if (! $last['ok']) {
                return $last;
            }
            $samples[] = $last['latency_ms'];
        }

        $last['data'] = ['samples_ms' => $samples, 'min_ms' => min($samples), 'avg_ms' => (int) (array_sum($samples) / count($samples))];

        return $last;
    }
}
