<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\CorePlatform\Http\ApiResponse;
use App\Modules\CorePlatform\Http\LogsAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Team management — was a full gap before Milestone 6 UI/UX: no
 * endpoint let a tenant_admin create or list staff within their own
 * tenant, and no endpoint let a platform_admin create a tenant_admin
 * for an already-existing tenant (only at tenant-creation time, via
 * TenantController::store).
 *
 * No mail transport configured — same temp-password-in-response
 * pattern as TenantController::store, documented there.
 */
class UserController
{
    use ApiResponse;
    use LogsAudit;

    /**
     * Tenant-scoped — tenant_admin lists their own tenant's team.
     * Matches Business Admin "Team" screen.
     */
    public function indexTeam(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('auth_tenant_id');

        $team = User::where('tenant_id', $tenantId)
            ->with('customRole:role_id,name')
            ->orderByDesc('created_at')
            ->get(['user_id', 'name', 'email', 'role', 'job_title', 'custom_role_id', 'locked_until', 'created_at']);

        return $this->success($team);
    }

    /**
     * Tenant-scoped — a business_admin (custom_role_id = null on their
     * own account) adds a team member with a custom role. The system
     * `role` field is never client-supplied here — it's hardcoded to
     * tenant_admin, and custom_role_id is required. Only platform_admin
     * (storeAdmin, below) can create an unrestricted admin
     * (custom_role_id = null) — business_admin cannot grant that via
     * this endpoint, by design.
     */
    public function storeTeam(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('auth_tenant_id');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Scoped to this tenant — otherwise a role_id belonging to a
            // different tenant would pass validation and, since
            // EnforcePermission looks the role up by ID alone, silently
            // grant that other tenant's permission set.
            'custom_role_id' => [
                'required', 'uuid',
                Rule::exists('tenant_roles', 'role_id')->where('tenant_id', $tenantId),
            ],
        ]);

        $tempPassword = Str::password(16);

        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($tempPassword),
            'role' => User::ROLE_TENANT_ADMIN,
            'job_title' => $validated['job_title'] ?? null,
            'custom_role_id' => $validated['custom_role_id'],
            'email_verified_at' => now(),
        ]);

        $this->audit($request, 'team.created', 'user', $user->user_id, ['email' => $user->email, 'custom_role_id' => $user->custom_role_id]);

        return $this->success([
            'user' => $user,
            'temporary_password' => $tempPassword,
        ], status: 201);
    }

    /**
     * Platform-wide — platform_admin lists users, filtered by role
     * (defaults to platform_admin — the accounts with no other screen
     * to view them on, rather than dumping every tenant's users by
     * default) and optionally by tenant. Matches Platform Admin
     * "Users" screen.
     */
    public function indexAdmins(Request $request): JsonResponse
    {
        $role = $request->query('role', User::ROLE_PLATFORM_ADMIN);
        $tenantId = $request->query('tenant_id');

        $query = User::where('role', $role)
            ->with('tenant:tenant_id,name,industry')
            ->orderByDesc('created_at');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $admins = $query->get(['user_id', 'tenant_id', 'name', 'email', 'job_title', 'locked_until', 'created_at']);

        return $this->success($admins);
    }

    /**
     * Platform-wide — platform_admin creates a tenant_admin for an
     * already-existing tenant (separate from the combined
     * create-tenant-plus-admin flow in TenantController::store).
     */
    public function storeAdmin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'tenant_id' => ['required', 'uuid', 'exists:tenants,tenant_id'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $tenant = Tenant::find($validated['tenant_id']);

        if ($tenant === null) {
            return $this->error('tenant_not_found', 'Tenant not found.', 404);
        }

        $tempPassword = Str::password(16);

        $user = User::create([
            'tenant_id' => $tenant->tenant_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($tempPassword),
            'role' => User::ROLE_TENANT_ADMIN,
            'job_title' => $validated['job_title'] ?? null,
            'email_verified_at' => now(),
        ]);

        $this->audit($request, 'admin.created', 'user', $user->user_id, ['email' => $user->email], $tenant->tenant_id);

        return $this->success([
            'user' => $user,
            'temporary_password' => $tempPassword,
        ], status: 201);
    }
}
