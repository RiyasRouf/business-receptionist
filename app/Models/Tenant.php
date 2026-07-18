<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $primaryKey = 'tenant_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'slug',
        'name',
        'industry',
        'country',
        'plan_id',
        'status',
        'limits_json',
    ];

    protected function casts(): array
    {
        return [
            'limits_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class, 'tenant_id', 'tenant_id');
    }
}
