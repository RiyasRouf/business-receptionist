<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UsageEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'usage_events';

    protected $primaryKey = 'usage_event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'session_id',
        'event_type',
        'quantity',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }
}
