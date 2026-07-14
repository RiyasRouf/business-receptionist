<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\Tenant;
use App\Models\UsageAllowance;
use App\Modules\CorePlatform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F-16/F-17 (Platform Admin: tenant management, usage allowance
 * assignment). platform_admin only — this is a global, cross-tenant
 * view, gated separately from the tenant.resolve group everything
 * else uses (a platform admin has no single tenant_id of their own).
 */
class TenantController
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::orderByDesc('created_at')->paginate(20);

        return $this->success(
            $tenants->items(),
            meta: [
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
                'total' => $tenants->total(),
            ],
        );
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug'],
            'status' => ['sometimes', 'string', 'in:active,suspended'],
        ]);

        $tenant = Tenant::create([
            'slug' => $validated['slug'],
            'status' => $validated['status'] ?? 'active',
        ]);

        return $this->success($tenant, status: 201);
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

        return $this->success($allowance);
    }
}
