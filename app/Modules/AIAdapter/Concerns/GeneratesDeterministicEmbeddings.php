<?php

namespace App\Modules\AIAdapter\Concerns;

/**
 * Hash-derived (not global mt_rand state), deterministic pseudo-embedding.
 * Same input text always produces the same unit vector, so retrieval/
 * reranking logic is testable offline. Shared by MockAIProvider (Module 5)
 * and KnowledgeBase's MockEmbeddingProvider (Module 4) to avoid duplicating
 * the algorithm.
 */
trait GeneratesDeterministicEmbeddings
{
    protected function deterministicEmbedding(string $text, int $dimension): array
    {
        $bytesNeeded = $dimension * 4;
        $bytes = '';
        $block = 0;

        while (strlen($bytes) < $bytesNeeded) {
            $bytes .= hash('sha256', $text.':'.$block, true);
            $block++;
        }

        $vector = [];
        $sumSquares = 0.0;

        for ($i = 0; $i < $dimension; $i++) {
            $uint32 = unpack('N', substr($bytes, $i * 4, 4))[1];
            $value = ($uint32 / 4294967295) * 2 - 1; // -> [-1, 1]

            $vector[] = $value;
            $sumSquares += $value * $value;
        }

        $norm = sqrt($sumSquares) ?: 1.0;

        return array_map(fn ($v) => $v / $norm, $vector);
    }
}
