<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiTurnLineage extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'ai_turn_lineage';

    protected $primaryKey = 'lineage_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'session_id',
        'trace_id',
        'tokens_prompt',
        'tokens_completion',
        'latency_ms',
        'kb_chunks_retrieved',
        'kb_chunks_injected',
        'guardrail_triggered',
        'guardrail_stage',
        'provider_used',
        'provider_id',
        'model_id',
        'model_version',
        'prompt_version',
        'kb_version',
        'knowledge_snapshot_id',
        'guardrail_version',
        'retrieval_strategy_version',
        'confidence_score',
    ];

    protected function casts(): array
    {
        return [
            'guardrail_triggered' => 'boolean',
            'confidence_score' => 'float',
            'created_at' => 'datetime',
        ];
    }
}
