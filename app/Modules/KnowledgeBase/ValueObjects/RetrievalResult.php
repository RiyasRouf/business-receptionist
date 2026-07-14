<?php

namespace App\Modules\KnowledgeBase\ValueObjects;

final class RetrievalResult
{
    /**
     * @param  array<int, array{chunk_id: string, content: string, similarity: float, rerank_score: float}>  $chunks
     */
    public function __construct(
        public readonly array $chunks,
        public readonly int $retrievedCount, // before threshold filter (AI_ARCHITECTURE.md §11 kb_chunks_retrieved)
        public readonly int $injectedCount,  // after threshold filter (kb_chunks_injected)
    ) {}
}
