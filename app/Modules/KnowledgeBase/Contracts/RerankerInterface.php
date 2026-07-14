<?php

namespace App\Modules\KnowledgeBase\Contracts;

interface RerankerInterface
{
    /**
     * @param  array<int, array{chunk_id: string, content: string, similarity: float}>  $candidates
     * @return array<int, array{chunk_id: string, content: string, similarity: float, rerank_score: float}>
     */
    public function rerank(string $query, array $candidates): array;
}
