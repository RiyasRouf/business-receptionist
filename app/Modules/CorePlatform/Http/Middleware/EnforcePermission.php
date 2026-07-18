<?php

namespace App\Modules\CorePlatform\Http\Middleware;

use App\Models\TenantRole;
use App\Models\User;
use App\Modules\CorePlatform\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only 2 system roles exist (platform_admin, tenant_admin) — every
 * tenant member is tenant_admin at that level, so route-level role
 * checks alone can no longer distinguish "the business admin" from
 * "a team member with limited access". A user with custom_role_id =
 * null is unrestricted (always passes). One with it set is checked
 * against their tenant_roles.permissions_json for the permission this
 * route requires — a real per-request DB check, not a JWT claim, so a
 * role edit takes effect immediately without forcing re-login.
 */
class EnforcePermission
{
    use ApiResponse;

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $userId = $request->attributes->get('auth_user_id');
        $user = User::find($userId);

        if ($user === null) {
            return $this->error('forbidden', 'Forbidden.', 403);
        }

        if ($user->custom_role_id === null) {
            return $next($request);
        }

        $role = TenantRole::find($user->custom_role_id);

        if ($role === null || ! in_array($permission, $role->permissions_json ?? [], true)) {
            return $this->error('forbidden', "Missing permission: {$permission}.", 403);
        }

        return $next($request);
    }
}
