<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Outbox extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'outbox';

    protected $primaryKey = 'outbox_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_id',
        'tenant_id',
        'event_type',
        'event_version',
        'payload',
        'status',
        'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'retry_count' => 'integer',
            'created_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
