<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\CorePlatform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

    /**
     * Tenant-scoped — tenant_admin lists their own tenant's team
     * (tenant_admin + staff). Matches Business Admin "Team" screen.
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
     * Tenant-scoped — tenant_admin creates a staff (or additional
     * tenant_admin) user within their own tenant.
     */
    public function storeTeam(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('auth_tenant_id');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', 'in:staff,tenant_admin'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'custom_role_id' => ['sometimes', 'nullable', 'uuid', 'exists:tenant_roles,role_id'],
        ]);

        $tempPassword = Str::password(16);

        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($tempPassword),
            'role' => $validated['role'],
            'job_title' => $validated['job_title'] ?? null,
            'custom_role_id' => $validated['custom_role_id'] ?? null,
            'email_verified_at' => now(),
        ]);

        return $this->success([
            'user' => $user,
            'temporary_password' => $tempPassword,
        ], status: 201);
    }

    /**
     * Platform-wide — platform_admin lists all tenant_admin users
     * across every tenant. Matches Platform Admin "Users" screen.
     */
    public function indexAdmins(Request $request): JsonResponse
    {
        $admins = User::where('role', User::ROLE_TENANT_ADMIN)
            ->with('tenant:tenant_id,name,industry')
            ->orderByDesc('created_at')
            ->get(['user_id', 'tenant_id', 'name', 'email', 'job_title', 'locked_until', 'created_at']);

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

        return $this->success([
            'user' => $user,
            'temporary_password' => $tempPassword,
        ], status: 201);
    }
}
