<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_chunks', function (Blueprint $table) {
            $table->uuid('chunk_id')->primary();
            $table->uuid('tenant_id')->notNull();
            $table->uuid('document_id')->notNull();
            $table->text('content')->notNull();
            $table->string('model_id')->notNull();
            $table->integer('embedding_dimension')->notNull();
            $table->jsonb('metadata_json')->notNull();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->cascadeOnDelete();
            $table->foreign('document_id')->references('document_id')->on('kb_documents')->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at']);
        });

        DB::statement('ALTER TABLE kb_chunks ADD COLUMN embedding vector(1536)');
        DB::statement('CREATE INDEX kb_chunks_embedding_hnsw_idx ON kb_chunks USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_chunks');
    }
};
