<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UsageAggregate extends Model
{
    use HasUuids;

    protected $table = 'usage_aggregates';

    protected $primaryKey = 'aggregate_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'allowance_type',
        'period_start',
        'period_end',
        'consumed',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'consumed' => 'integer',
        ];
    }
}
