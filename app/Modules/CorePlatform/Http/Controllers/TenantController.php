<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\Tenant;
use App\Models\UsageAllowance;
use App\Models\User;
use App\Modules\CorePlatform\Http\ApiResponse;
use App\Modules\CorePlatform\Http\LogsAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * F-16/F-17 (Platform Admin: tenant management, usage allowance
 * assignment). platform_admin only — this is a global, cross-tenant
 * view, gated separately from the tenant.resolve group everything
 * else uses (a platform admin has no single tenant_id of their own).
 */
class TenantController
{
    use ApiResponse;
    use LogsAudit;

    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::with('aiModel.provider')->orderByDesc('created_at')->paginate(20);

        return $this->success(
            $tenants->items(),
            meta: [
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
                'total' => $tenants->total(),
            ],
        );
    }

    public function update(Request $request, string $tenantId): JsonResponse
    {
        $tenant = Tenant::find($tenantId);

        if ($tenant === null) {
            return $this->error('tenant_not_found', 'Tenant not found.', 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:128'],
            'country' => ['sometimes', 'nullable', 'string', 'max:64'],
            'status' => ['sometimes', 'string', 'in:active,suspended'],
        ]);

        $tenant->update($validated);

        $this->audit($request, 'tenant.updated', 'tenant', $tenant->tenant_id, $validated, $tenant->tenant_id);

        return $this->success($tenant);
    }

    public function show(string $tenantId): JsonResponse
    {
        $tenant = Tenant::find($tenantId);

        if ($tenant === null) {
            return $this->error('tenant_not_found', 'Tenant not found.', 404);
        }

        $allowances = UsageAllowance::where('tenant_id', $tenantId)->get([
            'allowance_id', 'allowance_type', 'limit', 'grace', 'warning_threshold_pct', 'reset_period',
        ]);

        return $this->success([
            'tenant' => $tenant,
            'allowances' => $allowances,
        ]);
    }

    /**
     * Creates the tenant, its initial call-minutes allowance, and its
     * first tenant_admin user in one transaction — matches the Milestone
     * 6 UI/UX spec's "Add New Tenant" flow (one form, one submit).
     *
     * No mail transport is configured yet (MAIL_MAILER=log, no Mailable
     * classes exist) — rather than silently pretend an invite email was
     * sent, the generated temporary password is returned once in the
     * response for the Platform Admin to relay manually. Real invite
     * email is follow-up work once SMTP is set up.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'string', 'in:active,suspended'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'monthly_allowance_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $tempPassword = Str::password(16);

        $result = DB::transaction(function () use ($validated, $tempPassword) {
            $tenant = Tenant::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'industry' => $validated['industry'] ?? null,
                'country' => $validated['country'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            $admin = User::create([
                'tenant_id' => $tenant->tenant_id,
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => Hash::make($tempPassword),
                'role' => User::ROLE_TENANT_ADMIN,
                'email_verified_at' => now(),
            ]);

            $allowance = null;

            if (! empty($validated['monthly_allowance_minutes'])) {
                $allowance = UsageAllowance::create([
                    'tenant_id' => $tenant->tenant_id,
                    'allowance_type' => 'call_minutes',
                    'limit' => $validated['monthly_allowance_minutes'],
                    'grace' => 0,
                    'warning_threshold_pct' => 80,
                    'reset_period' => 'monthly',
                    'reset_day' => 1,
                ]);
            }

            return [$tenant, $admin, $allowance];
        });

        [$tenant, $admin, $allowance] = $result;

        $this->audit($request, 'tenant.created', 'tenant', $tenant->tenant_id, ['name' => $tenant->name], $tenant->tenant_id);

        return $this->success([
            'tenant' => $tenant,
            'admin' => $admin,
            'allowance' => $allowance,
            'temporary_password' => $tempPassword,
        ], status: 201);
    }

    public function setAllowance(Request $request, string $tenantId): JsonResponse
    {
        $tenant = Tenant::find($tenantId);

        if ($tenant === null) {
            return $this->error('tenant_not_found', 'Tenant not found.', 404);
        }

        $validated = $request->validate([
            'allowance_type' => ['required', 'string', 'max:255'],
            'limit' => ['required', 'integer', 'min:0'],
            'grace' => ['sometimes', 'integer', 'min:0'],
            'warning_threshold_pct' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'reset_period' => ['sometimes', 'string', 'in:monthly'],
            'reset_day' => ['sometimes', 'integer', 'min:1', 'max:28'],
        ]);

        $allowance = UsageAllowance::updateOrCreate(
            ['tenant_id' => $tenantId, 'allowance_type' => $validated['allowance_type']],
            [
                'limit' => $validated['limit'],
                'grace' => $validated['grace'] ?? 0,
                'warning_threshold_pct' => $validated['warning_threshold_pct'] ?? 80,
                'reset_period' => $validated['reset_period'] ?? 'monthly',
                'reset_day' => $validated['reset_day'] ?? 1,
            ],
        );

        $this->audit($request, 'tenant.allowance_updated', 'usage_allowance', (string) $allowance->allowance_id, ['limit' => $validated['limit']], $tenantId);

        return $this->success($allowance);
    }
}
