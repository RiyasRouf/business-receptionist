<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\TenantRole;
use App\Modules\CorePlatform\Http\ApiResponse;
use App\Modules\CorePlatform\Http\LogsAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Custom staff roles, scoped per tenant — the "Roles & Permissions"
 * screen. Permissions are a free-form string list (e.g. "leads",
 * "knowledge_base", "live_calls", "transcripts") persisted on the
 * role and assignable to team members via User.custom_role_id.
 *
 * Scope note: this is real, persisted, tenant-isolated CRUD — a
 * genuine gap-fill, not decorative. It is NOT yet wired into
 * per-endpoint authorization (the existing role:staff/tenant_admin
 * middleware still gates access) — enforcing permissions_json at each
 * endpoint is real additional work, intentionally out of scope here
 * per the phasing already agreed, not hidden.
 */
class TenantRoleController
{
    use ApiResponse;
    use LogsAudit;

    private const AVAILABLE_PERMISSIONS = ['leads', 'knowledge_base', 'live_calls', 'transcripts', 'team'];

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('auth_tenant_id');

        $roles = TenantRole::where('tenant_id', $tenantId)
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return $this->success([
            'roles' => $roles,
            'available_permissions' => self::AVAILABLE_PERMISSIONS,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('auth_tenant_id');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', self::AVAILABLE_PERMISSIONS)],
        ]);

        $role = TenantRole::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'permissions_json' => $validated['permissions'],
        ]);

        $this->audit($request, 'role.created', 'tenant_role', $role->role_id, ['name' => $role->name, 'permissions' => $role->permissions_json]);

        return $this->success($role, status: 201);
    }

    public function update(Request $request, string $roleId): JsonResponse
    {
        $tenantId = $request->attributes->get('auth_tenant_id');
        $role = TenantRole::where('tenant_id', $tenantId)->where('role_id', $roleId)->first();

        if ($role === null) {
            return $this->error('role_not_found', 'Role not found.', 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', self::AVAILABLE_PERMISSIONS)],
        ]);

        if (isset($validated['permissions'])) {
            $validated['permissions_json'] = $validated['permissions'];
            unset($validated['permissions']);
        }

        $role->update($validated);

        $this->audit($request, 'role.updated', 'tenant_role', $role->role_id, $validated);

        return $this->success($role);
    }

    public function destroy(Request $request, string $roleId): JsonResponse
    {
        $tenantId = $request->attributes->get('auth_tenant_id');
        $role = TenantRole::where('tenant_id', $tenantId)->where('role_id', $roleId)->first();

        if ($role === null) {
            return $this->error('role_not_found', 'Role not found.', 404);
        }

        $this->audit($request, 'role.deleted', 'tenant_role', $role->role_id, ['name' => $role->name]);

        $role->delete();

        return $this->success(['message' => 'Deleted']);
    }
}
