<?php

namespace App\Modules\CorePlatform\Services\Providers;

use App\Models\ProviderTestLog;

class ProviderTestLogger
{
    public function log(string $provider, string $scope, string $action, bool $ok, ?int $latencyMs = null, array $detail = [], ?string $tenantId = null): ProviderTestLog
    {
        return ProviderTestLog::create([
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'scope' => $scope,
            'action' => $action,
            'ok' => $ok,
            'latency_ms' => $latencyMs,
            'detail_json' => $detail,
        ]);
    }

    public function history(string $scope, ?string $tenantId, int $limit = 20)
    {
        return ProviderTestLog::query()
            ->where('scope', $scope)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
