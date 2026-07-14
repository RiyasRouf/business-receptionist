<?php

namespace App\Models;

use App\Modules\KnowledgeBase\Casts\VectorCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class KbChunk extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $primaryKey = 'chunk_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'document_id',
        'content',
        'embedding',
        'model_id',
        'embedding_dimension',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'embedding' => VectorCast::class,
            'created_at' => 'datetime',
        ];
    }

    public function document()
    {
        return $this->belongsTo(KbDocument::class, 'document_id', 'document_id');
    }
}
