<?php

namespace App\Modules\KnowledgeBase\Services;

use App\Modules\KnowledgeBase\Contracts\EmbeddingProviderInterface;

/**
 * Deterministic, offline embedding provider. Same input text always
 * produces the same vector, so retrieval/reranking logic is testable
 * without a real AI provider — mirrors the project's MockAIProvider
 * pattern (IP-003) until OQ-003 (primary AI model) is resolved.
 */
class MockEmbeddingProvider implements EmbeddingProviderInterface
{
    private const DIMENSION = 1536;

    public function embed(string $text): array
    {
        // Hash-derived (not global mt_rand state) so this stays pure and
        // side-effect-free regardless of what else runs in the request.
        // Expand via counter-mode SHA-256 until we have enough bytes.
        $bytesNeeded = self::DIMENSION * 4;
        $bytes = '';
        $block = 0;

        while (strlen($bytes) < $bytesNeeded) {
            $bytes .= hash('sha256', $text.':'.$block, true);
            $block++;
        }

        $vector = [];
        $sumSquares = 0.0;

        for ($i = 0; $i < self::DIMENSION; $i++) {
            $uint32 = unpack('N', substr($bytes, $i * 4, 4))[1];
            $value = ($uint32 / 4294967295) * 2 - 1; // -> [-1, 1]

            $vector[] = $value;
            $sumSquares += $value * $value;
        }

        $norm = sqrt($sumSquares) ?: 1.0;

        return array_map(fn ($v) => $v / $norm, $vector);
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
