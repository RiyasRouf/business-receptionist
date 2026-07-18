<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TenantRole extends Model
{
    use HasUuids;

    protected $primaryKey = 'role_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['tenant_id', 'name', 'description', 'permissions_json'];

    protected function casts(): array
    {
        return ['permissions_json' => 'array'];
    }

    public function users()
    {
        return $this->hasMany(User::class, 'custom_role_id', 'role_id');
    }
}
