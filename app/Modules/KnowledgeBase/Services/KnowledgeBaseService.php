<?php

namespace App\Modules\KnowledgeBase\Services;

use App\Models\KbChunk;
use App\Models\KbDocument;
use App\Modules\KnowledgeBase\Contracts\EmbeddingProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KnowledgeBaseService
{
    public function __construct(
        private readonly ChunkingService $chunker,
        private readonly EmbeddingProviderInterface $embedder,
    ) {}

    public function ingest(string $tenantId, string $title, string $storagePath, string $rawText): KbDocument
    {
        // Immutable per ingestion batch — recorded in AI turn lineage (ADR-055).
        $snapshotId = (string) Str::uuid();

        $document = KbDocument::create([
            'tenant_id' => $tenantId,
            'title' => $title,
            'storage_path' => $storagePath,
            'status' => 'processing',
            'metadata_json' => ['knowledge_snapshot_id' => $snapshotId],
        ]);

        $chunks = $this->chunker->chunk($tenantId, $rawText);

        DB::transaction(function () use ($tenantId, $document, $chunks, $snapshotId) {
            foreach ($chunks as $content) {
                KbChunk::create([
                    'tenant_id' => $tenantId,
                    'document_id' => $document->document_id,
                    'content' => $content,
                    'embedding' => $this->embedder->embed($content),
                    'model_id' => $this->embedder->modelId(),
                    'embedding_dimension' => $this->embedder->dimension(),
                    'metadata_json' => ['knowledge_snapshot_id' => $snapshotId],
                ]);
            }
        });

        $document->update(['status' => empty($chunks) ? 'empty' : 'ready']);

        return $document->fresh();
    }

    public function delete(KbDocument $document): void
    {
        DB::transaction(function () use ($document) {
            $document->chunks()->delete();
            $document->delete();
        });
    }
}
