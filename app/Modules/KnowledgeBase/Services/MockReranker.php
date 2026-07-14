<?php

namespace App\Modules\KnowledgeBase\Services;

use App\Modules\KnowledgeBase\Contracts\RerankerInterface;

/**
 * Pass-through reranker: keeps vector-similarity order and reuses the
 * similarity score as the rerank score. Real cross-encoder / RRF
 * reranking (ADR-049) is deferred until a real AI provider is chosen
 * (OQ-003) — this keeps the retrieval → rerank → threshold pipeline
 * fully wired and testable in the meantime.
 */
class MockReranker implements RerankerInterface
{
    public function rerank(string $query, array $candidates): array
    {
        return array_map(
            fn (array $c) => $c + ['rerank_score' => $c['similarity']],
            $candidates
        );
    }
}
