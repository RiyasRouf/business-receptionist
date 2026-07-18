<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    // Only 2 system roles. Non-admin tenant members are also
    // tenant_admin at this level — custom_role_id (null = unrestricted,
    // set = limited to tenant_roles.permissions_json) is what actually
    // differentiates access within a tenant. See EnforcePermission.
    public const ROLE_PLATFORM_ADMIN = 'platform_admin';
    public const ROLE_TENANT_ADMIN = 'tenant_admin';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'role',
        'job_title',
        'custom_role_id',
    ];

    protected $hidden = [
        'password',
        'refresh_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'locked_until' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function customRole()
    {
        return $this->belongsTo(TenantRole::class, 'custom_role_id', 'role_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
