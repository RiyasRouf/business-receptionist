<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'audit_log';

    protected $primaryKey = 'audit_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'resource_type',
        'resource_id',
        'diff_json',
    ];

    protected function casts(): array
    {
        return [
            'diff_json' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
