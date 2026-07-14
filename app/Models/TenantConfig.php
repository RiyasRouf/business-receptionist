<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TenantConfig extends Model
{
    use HasUuids;

    protected $table = 'tenant_config';

    protected $primaryKey = 'config_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'key',
        'value',
        'type',
    ];
}
