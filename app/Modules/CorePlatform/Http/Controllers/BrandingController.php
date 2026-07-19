<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Modules\CorePlatform\Http\ApiResponse;
use App\Modules\CorePlatform\Http\LogsAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandingController
{
    use ApiResponse;
    use LogsAudit;

    private const LOGO_RULES = ['mimes:png,jpg,jpeg,jpe,gif,bmp,svg,webp,ico,tiff,tif,avif,heic,heif', 'max:5120'];

    public function platformShow(): JsonResponse
    {
        return $this->success($this->withLogoUrl(PlatformSetting::current()->toArray()));
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

        return $this->success($this->withLogoUrl($settings->toArray()));
    }

    public function platformUploadLogo(Request $request): JsonResponse
    {
        $request->validate(['logo' => ['required', ...self::LOGO_RULES]]);

        $settings = PlatformSetting::current();
        $this->replaceLogo($settings, $request->file('logo'), 'logo_path', 'branding/platform');

        $this->audit($request, 'branding.platform_logo_uploaded', 'platform_setting', (string) $settings->id, ['logo_path' => $settings->logo_path]);

        return $this->success($this->withLogoUrl($settings->toArray()));
    }

    public function platformDeleteLogo(Request $request): JsonResponse
    {
        $settings = PlatformSetting::current();
        $this->clearLogo($settings, 'logo_path');

        $this->audit($request, 'branding.platform_logo_removed', 'platform_setting', (string) $settings->id, []);

        return $this->success($this->withLogoUrl($settings->toArray()));
    }

    public function tenantShow(Request $request, ?string $tenantId = null): JsonResponse
    {
        $tenant = $this->resolveTenant($request, $tenantId);

        if ($tenant === null) {
            return $this->error('tenant_not_found', 'Tenant not found.', 404);
        }

        return $this->success($this->withLogoUrl([
            'tenant_id' => $tenant->tenant_id,
            'brand_name' => $tenant->brand_name ?: $tenant->name,
            'brand_color' => $tenant->brand_color,
            'brand_tagline' => $tenant->brand_tagline,
            'brand_logo_path' => $tenant->brand_logo_path,
        ], 'brand_logo_path'));
    }

    public function tenantUpdate(Request $request, ?string $tenantId = null): JsonResponse
    {
        $tenant = $this->resolveTenant($request, $tenantId);

        if ($tenant === null) {
            return $this->error('tenant_not_found', 'Tenant not found.', 404);
        }

        $validated = $request->validate([
            'brand_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'brand_color' => ['sometimes', 'nullable', 'string', 'max:7'],
            'brand_tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        // Single source of truth: the tenant's display name IS brand_name.
        // Keep the legacy `name` column (shown in platform tenant list,
        // dashboards) in sync so one edit updates every surface.
        if (array_key_exists('brand_name', $validated) && filled($validated['brand_name'])) {
            $validated['name'] = $validated['brand_name'];
        }

        $tenant->update($validated);

        $this->audit($request, 'branding.tenant_updated', 'tenant', $tenant->tenant_id, $validated, $tenant->tenant_id);

        return $this->success($this->withLogoUrl($tenant->toArray(), 'brand_logo_path'));
    }

    public function tenantUploadLogo(Request $request, ?string $tenantId = null): JsonResponse
    {
        $tenant = $this->resolveTenant($request, $tenantId);

        if ($tenant === null) {
            return $this->error('tenant_not_found', 'Tenant not found.', 404);
        }

        $request->validate(['logo' => ['required', ...self::LOGO_RULES]]);

        $this->replaceLogo($tenant, $request->file('logo'), 'brand_logo_path', 'branding/tenants');

        $this->audit($request, 'branding.tenant_logo_uploaded', 'tenant', $tenant->tenant_id, ['brand_logo_path' => $tenant->brand_logo_path], $tenant->tenant_id);

        return $this->success($this->withLogoUrl($tenant->toArray(), 'brand_logo_path'));
    }

    public function tenantDeleteLogo(Request $request, ?string $tenantId = null): JsonResponse
    {
        $tenant = $this->resolveTenant($request, $tenantId);

        if ($tenant === null) {
            return $this->error('tenant_not_found', 'Tenant not found.', 404);
        }

        $this->clearLogo($tenant, 'brand_logo_path');

        $this->audit($request, 'branding.tenant_logo_removed', 'tenant', $tenant->tenant_id, [], $tenant->tenant_id);

        return $this->success($this->withLogoUrl($tenant->toArray(), 'brand_logo_path'));
    }

    private function resolveTenant(Request $request, ?string $tenantId): ?Tenant
    {
        $tid = $tenantId ?? $request->attributes->get('auth_tenant_id');

        return Tenant::find($tid);
    }

    private function replaceLogo(Tenant|PlatformSetting $model, $file, string $column, string $dir): void
    {
        $this->clearLogo($model, $column);

        $path = $file->store($dir, 'public');
        $model->update([$column => $path]);
    }

    private function clearLogo(Tenant|PlatformSetting $model, string $column): void
    {
        if ($model->{$column}) {
            Storage::disk('public')->delete($model->{$column});
        }

        $model->update([$column => null]);
    }

    private function withLogoUrl(array $data, string $column = 'logo_path'): array
    {
        $data['logo_url'] = $data[$column] ? Storage::disk('public')->url($data[$column]) : null;

        return $data;
    }
}
