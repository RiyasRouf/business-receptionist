<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UsageAllowance extends Model
{
    use HasUuids;

    protected $table = 'tenant_usage_allowances';

    protected $primaryKey = 'allowance_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'allowance_type',
        'limit',
        'grace',
        'warning_threshold_pct',
        'reset_period',
        'reset_day',
    ];

    protected function casts(): array
    {
        return [
            'limit' => 'integer',
            'grace' => 'integer',
            'warning_threshold_pct' => 'integer',
            'reset_day' => 'integer',
        ];
    }
}
