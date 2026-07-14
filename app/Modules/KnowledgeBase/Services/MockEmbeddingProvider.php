<?php

namespace App\Modules\KnowledgeBase\Services;

use App\Modules\AIAdapter\Concerns\GeneratesDeterministicEmbeddings;
use App\Modules\KnowledgeBase\Contracts\EmbeddingProviderInterface;

/**
 * Deterministic, offline embedding provider. Same input text always
 * produces the same vector, so retrieval/reranking logic is testable
 * without a real AI provider — mirrors the project's MockAIProvider
 * pattern (IP-003) until OQ-003 (primary AI model) is resolved.
 */
class MockEmbeddingProvider implements EmbeddingProviderInterface
{
    use GeneratesDeterministicEmbeddings;

    private const DIMENSION = 1536;

    public function embed(string $text): array
    {
        return $this->deterministicEmbedding($text, self::DIMENSION);
    }

    public function modelId(): string
    {
        return 'mock-embed-v1';
    }

    public function dimension(): int
    {
        return self::DIMENSION;
    }
}
