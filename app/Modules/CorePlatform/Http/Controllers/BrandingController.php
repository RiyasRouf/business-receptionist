<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Modules\CorePlatform\Http\ApiResponse;
use App\Modules\CorePlatform\Http\LogsAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandingController
{
    use ApiResponse;
    use LogsAudit;

    public function platformShow(): JsonResponse
    {
        return $this->success(PlatformSetting::current());
    }

    public function platformUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:7'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $settings = PlatformSetting::current();
        $settings->update($validated);

        $this->audit($request, 'branding.platform_updated', 'platform_setting', (string) $settings->id, $validated);

        return $this->success($settings);
    }

    public function tenantShow(Request $request, ?string $tenantId = null): JsonResponse
    {
        $tid = $tenantId ?? $request->attributes->get('auth_tenant_id');
        $tenant = Tenant::find($tid);

        if ($tenant === null) {
            return $this->error('tenant_not_found', 'Tenant not found.', 404);
        }

        return $this->success([
            'tenant_id' => $tenant->tenant_id,
            'brand_name' => $tenant->brand_name,
            'brand_color' => $tenant->brand_color,
            'brand_tagline' => $tenant->brand_tagline,
        ]);
    }

    public function tenantUpdate(Request $request, ?string $tenantId = null): JsonResponse
    {
        $tid = $tenantId ?? $request->attributes->get('auth_tenant_id');
        $tenant = Tenant::find($tid);

        if ($tenant === null) {
            return $this->error('tenant_not_found', 'Tenant not found.', 404);
        }

        $validated = $request->validate([
            'brand_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'brand_color' => ['sometimes', 'nullable', 'string', 'max:7'],
            'brand_tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $tenant->update($validated);

        $this->audit($request, 'branding.tenant_updated', 'tenant', $tenant->tenant_id, $validated, $tid);

        return $this->success($tenant);
    }
}
