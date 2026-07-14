<?php

namespace App\Modules\AIAdapter\Services;

use App\Modules\AIAdapter\Contracts\AIProviderInterface;
use App\Modules\AIAdapter\ValueObjects\AIResponse;
use App\Modules\AIAdapter\ValueObjects\ModelLimits;
use App\Modules\KnowledgeBase\Contracts\EmbeddingProviderInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Default provider per D-015-01 (OQ-003). No provider-specific types
 * cross this boundary (ADR-043) — everything below normalises Gemini's
 * response shape into AIResponse/ModelLimits before returning.
 */
class GeminiAdapter implements AIProviderInterface, EmbeddingProviderInterface
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    // Output truncated via outputDimensionality to match kb_chunks'
    // fixed vector(1536) column — gemini-embedding-001 defaults to 3072.
    // Truncated vectors aren't unit-norm (confirmed against the real API,
    // ~0.70), so this class re-normalises before returning.
    private const EMBED_DIMENSION = 1536;

    // Verified against the real /v1beta/models list endpoint. Gemini
    // doesn't expose these lookups per-call cheaply enough to hit on
    // every request, so known models are hardcoded with a conservative
    // fallback for anything else.
    private const KNOWN_LIMITS = [
        'gemini-2.5-flash' => ['context' => 1_048_576, 'output' => 65_536],
        'gemini-2.5-pro' => ['context' => 1_048_576, 'output' => 65_536],
    ];

    public function complete(array $messages, array $config = []): AIResponse
    {
        $model = $config['model'] ?? Config::string('services.gemini.model');
        $payload = $this->buildPayload($messages, $config);

        $response = Http::timeout(30)
            ->post($this->url("models/{$model}:generateContent"), $payload);

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error: '.$response->body());
        }

        $data = $response->json();
        $candidate = $data['candidates'][0] ?? null;

        if ($candidate === null) {
            throw new RuntimeException('Gemini returned no candidates: '.$response->body());
        }

        $text = collect($candidate['content']['parts'] ?? [])
            ->pluck('text')
            ->implode('');

        return new AIResponse(
            content: $text,
            finishReason: strtolower($candidate['finishReason'] ?? 'stop'),
            promptTokens: $data['usageMetadata']['promptTokenCount'] ?? 0,
            completionTokens: $data['usageMetadata']['candidatesTokenCount'] ?? 0,
            modelId: $model,
            provider: 'gemini',
        );
    }

    public function stream(array $messages, array $config = []): iterable
    {
        $model = $config['model'] ?? Config::string('services.gemini.model');
        $payload = $this->buildPayload($messages, $config);

        $response = Http::timeout(60)
            ->withOptions(['stream' => true])
            ->post($this->url("models/{$model}:streamGenerateContent", ['alt' => 'sse']), $payload);

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error: '.$response->body());
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                if (! str_starts_with($event, 'data: ')) {
                    continue;
                }

                $chunk = json_decode(substr($event, 6), true);
                $candidate = $chunk['candidates'][0] ?? null;

                if ($candidate === null) {
                    continue;
                }

                $text = collect($candidate['content']['parts'] ?? [])->pluck('text')->implode('');
                $finish = $candidate['finishReason'] ?? null;

                yield new AIResponse(
                    content: $text,
                    finishReason: $finish ? strtolower($finish) : '',
                    promptTokens: $chunk['usageMetadata']['promptTokenCount'] ?? 0,
                    completionTokens: $chunk['usageMetadata']['candidatesTokenCount'] ?? 0,
                    modelId: $model,
                    provider: 'gemini',
                );
            }
        }
    }

    public function embed(string $text, array $config = []): array
    {
        $model = $config['model'] ?? Config::string('services.gemini.embedding_model');

        $response = Http::timeout(30)->post($this->url("models/{$model}:embedContent"), [
            'content' => ['parts' => [['text' => $text]]],
            'outputDimensionality' => self::EMBED_DIMENSION,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini embedding API error: '.$response->body());
        }

        $values = $response->json('embedding.values', []);

        return $this->normalise($values);
    }

    public function getModelLimits(string $modelId): ModelLimits
    {
        $limits = self::KNOWN_LIMITS[$modelId] ?? ['context' => 32_768, 'output' => 8_192];

        return new ModelLimits(
            modelId: $modelId,
            contextWindow: $limits['context'],
            maxOutputTokens: $limits['output'],
        );
    }

    public function modelId(): string
    {
        return Config::string('services.gemini.embedding_model');
    }

    public function dimension(): int
    {
        return self::EMBED_DIMENSION;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function buildPayload(array $messages, array $config): array
    {
        $contents = [];
        $systemParts = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemParts[] = ['text' => $message['content']];

                continue;
            }

            $contents[] = [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ];
        }

        $payload = ['contents' => $contents];

        if ($systemParts !== []) {
            $payload['systemInstruction'] = ['parts' => $systemParts];
        }

        $generationConfig = array_filter([
            'temperature' => $config['temperature'] ?? null,
            'maxOutputTokens' => $config['max_tokens'] ?? null,
        ], fn ($v) => $v !== null);

        if ($generationConfig !== []) {
            $payload['generationConfig'] = $generationConfig;
        }

        return $payload;
    }

    /**
     * @param  float[]  $vector
     * @return float[]
     */
    private function normalise(array $vector): array
    {
        $sumSquares = array_sum(array_map(fn ($v) => $v * $v, $vector));
        $norm = sqrt($sumSquares) ?: 1.0;

        return array_map(fn ($v) => $v / $norm, $vector);
    }

    private function url(string $path, array $query = []): string
    {
        $query['key'] = Config::string('services.gemini.key');

        return self::BASE_URL.'/'.$path.'?'.http_build_query($query);
    }
}
