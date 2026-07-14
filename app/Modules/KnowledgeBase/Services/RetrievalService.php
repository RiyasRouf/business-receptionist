<?php

namespace App\Modules\KnowledgeBase\Services;

use App\Models\TenantConfig;
use App\Modules\KnowledgeBase\Contracts\EmbeddingProviderInterface;
use App\Modules\KnowledgeBase\Contracts\RerankerInterface;
use Illuminate\Support\Facades\DB;

class RetrievalService
{
    private const DEFAULT_TOP_K = 10;

    private const DEFAULT_RELEVANCE_THRESHOLD = 0.7;

    public function __construct(
        private readonly EmbeddingProviderInterface $embedder,
        private readonly RerankerInterface $reranker,
    ) {}

    /**
     * Top-K HNSW similarity → rerank → relevance threshold (ADR-049).
     * Always filtered by tenant_id AND model_id — cross-tenant or
     * cross-model-generation retrieval is impossible at the query
     * layer (ADR-044, ADR-036).
     *
     * @return array<int, array{chunk_id: string, content: string, similarity: float, rerank_score: float}>
     */
    public function retrieve(string $tenantId, string $query, ?int $topK = null): array
    {
        $topK ??= self::DEFAULT_TOP_K;
        $threshold = $this->relevanceThreshold($tenantId);

        $queryVector = $this->embedder->embed($query);
        $vectorLiteral = '['.implode(',', $queryVector).']';

        $rows = DB::select(
            'SELECT chunk_id, content, 1 - (embedding <=> ?::vector) AS similarity
             FROM kb_chunks
             WHERE tenant_id = ? AND model_id = ?
             ORDER BY embedding <=> ?::vector
             LIMIT ?',
            [$vectorLiteral, $tenantId, $this->embedder->modelId(), $vectorLiteral, $topK]
        );

        $candidates = array_map(fn ($row) => [
            'chunk_id' => $row->chunk_id,
            'content' => $row->content,
            'similarity' => (float) $row->similarity,
        ], $rows);

        $reranked = $this->reranker->rerank($query, $candidates);

        return array_values(array_filter(
            $reranked,
            fn (array $c) => $c['rerank_score'] >= $threshold
        ));
    }

    private function relevanceThreshold(string $tenantId): float
    {
        $value = TenantConfig::where('tenant_id', $tenantId)
            ->where('key', 'kb.relevance_threshold')
            ->value('value');

        return $value !== null ? (float) $value : self::DEFAULT_RELEVANCE_THRESHOLD;
    }
}
