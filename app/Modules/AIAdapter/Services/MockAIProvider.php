<?php

namespace App\Modules\AIAdapter\Services;

use App\Modules\AIAdapter\Concerns\GeneratesDeterministicEmbeddings;
use App\Modules\AIAdapter\Contracts\AIProviderInterface;
use App\Modules\AIAdapter\ValueObjects\AIResponse;
use App\Modules\AIAdapter\ValueObjects\ModelLimits;
use App\Modules\KnowledgeBase\Contracts\EmbeddingProviderInterface;

/**
 * Scripted, deterministic, offline. Primary test double for all
 * Conversation Engine and Lead Capture tests — no real AI calls in CI
 * (module-build-order.md Key Rules). Also implements
 * EmbeddingProviderInterface so the same mock covers both concerns
 * (mirrors how a real provider adapter like GeminiAdapter does both).
 */
class MockAIProvider implements AIProviderInterface, EmbeddingProviderInterface
{
    use GeneratesDeterministicEmbeddings;

    private const EMBED_DIMENSION = 1536;

    public function complete(array $messages, array $config = []): AIResponse
    {
        $content = $this->deterministicReply($messages);

        return new AIResponse(
            content: $content,
            finishReason: 'stop',
            promptTokens: $this->estimateTokens($this->flatten($messages)),
            completionTokens: $this->estimateTokens($content),
            modelId: 'mock-completion-v1',
            provider: 'mock',
        );
    }

    public function stream(array $messages, array $config = []): iterable
    {
        $content = $this->deterministicReply($messages);
        $words = explode(' ', $content);
        $last = array_key_last($words);

        foreach ($words as $i => $word) {
            $delta = ($i === 0 ? '' : ' ').$word;

            yield new AIResponse(
                content: $delta,
                finishReason: $i === $last ? 'stop' : '',
                promptTokens: $i === 0 ? $this->estimateTokens($this->flatten($messages)) : 0,
                completionTokens: $this->estimateTokens($delta),
                modelId: 'mock-completion-v1',
                provider: 'mock',
            );
        }
    }

    public function embed(string $text, array $config = []): array
    {
        return $this->deterministicEmbedding($text, self::EMBED_DIMENSION);
    }

    public function getModelLimits(string $modelId): ModelLimits
    {
        return new ModelLimits(
            modelId: $modelId,
            contextWindow: 128_000,
            maxOutputTokens: 4_096,
        );
    }

    public function modelId(): string
    {
        return 'mock-embed-v1';
    }

    public function dimension(): int
    {
        return self::EMBED_DIMENSION;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    private function deterministicReply(array $messages): string
    {
        $lastUser = null;

        foreach (array_reverse($messages) as $message) {
            if ($message['role'] === 'user') {
                $lastUser = $message['content'];
                break;
            }
        }

        $lastUser ??= '';

        return sprintf('[mock reply to: "%s"]', mb_substr(trim($lastUser), 0, 120));
    }

    /**
     * @param  array<int, array{role: string, content: string}>|string  $input
     */
    private function estimateTokens(array|string $input): int
    {
        $text = is_array($input) ? $this->flatten($input) : $input;

        // Rough, deterministic approximation (~4 chars/token) — good enough
        // for mock usage-event testing, not meant to match a real tokenizer.
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    private function flatten(array $messages): string
    {
        return implode(' ', array_column($messages, 'content'));
    }
}
