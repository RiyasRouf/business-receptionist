<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI_ARCHITECTURE.md §11 (Observability, ADR-051) and §12
     * (Evaluation Lineage, ADR-054) both call for per-turn fields with
     * no home in the frozen DATA_ARCHITECTURE.md schema — usage_events
     * has no JSONB/extension column. New table, not a usage_events
     * retrofit, per IP-004 (new migrations permitted for approved
     * features; existing production migrations immutable).
     *
     * Union of both field lists (they overlap heavily — model_id,
     * prompt_version, knowledge_snapshot_id, confidence_score appear
     * in both) rather than two separate tables.
     */
    public function up(): void
    {
        Schema::create('ai_turn_lineage', function (Blueprint $table) {
            $table->uuid('lineage_id')->primary();
            $table->uuid('tenant_id')->notNull();
            $table->uuid('session_id')->notNull();
            // string, not uuid() — AddTraceId middleware propagates an
            // inbound X-Trace-ID header verbatim with no format check
            // (a caller might not use UUIDs), so this must accept
            // whatever format actually arrives.
            $table->string('trace_id')->nullable();

            // Observability (ADR-051)
            $table->integer('tokens_prompt')->nullable();
            $table->integer('tokens_completion')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->integer('kb_chunks_retrieved')->nullable();
            $table->integer('kb_chunks_injected')->nullable();
            $table->boolean('guardrail_triggered')->default(false);
            $table->string('guardrail_stage')->nullable(); // pre|post|none
            $table->string('provider_used')->nullable(); // primary|fallback

            // Evaluation lineage (ADR-054)
            $table->string('provider_id')->nullable();
            $table->string('model_id')->nullable();
            $table->string('model_version')->nullable();
            $table->string('prompt_version')->nullable();
            $table->string('kb_version')->nullable();
            $table->uuid('knowledge_snapshot_id')->nullable();
            $table->string('guardrail_version')->nullable();
            $table->string('retrieval_strategy_version')->nullable();
            $table->decimal('confidence_score', 5, 4)->nullable();

            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->cascadeOnDelete();
            $table->foreign('session_id')->references('session_id')->on('sessions')->cascadeOnDelete();
            $table->index(['tenant_id', 'session_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_turn_lineage');
    }
};
