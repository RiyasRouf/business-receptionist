<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\AiProvider;
use App\Models\Session;
use App\Models\Tenant;
use App\Modules\CorePlatform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController
{
    use ApiResponse;

    public function platform(): JsonResponse
    {
        $checks = ['database' => false, 'redis' => false];
        $latencyMs = ['database' => null, 'redis' => null];

        $start = microtime(true);
        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
            $latencyMs['database'] = round((microtime(true) - $start) * 1000, 1);
        } catch (\Throwable) {
            // stays false
        }

        $start = microtime(true);
        try {
            Redis::connection()->ping();
            $checks['redis'] = true;
            $latencyMs['redis'] = round((microtime(true) - $start) * 1000, 1);
        } catch (\Throwable) {
            // stays false
        }

        return $this->success([
            'status' => ! in_array(false, $checks, true) ? 'healthy' : 'degraded',
            'checks' => $checks,
            'latency_ms' => $latencyMs,
            'stats' => [
                'active_tenants' => Tenant::where('status', 'active')->count(),
                'sessions_today' => Session::whereDate('started_at', now()->toDateString())->count(),
                'ai_providers_active' => AiProvider::where('status', 'active')->count(),
            ],
            'checked_at' => now()->toIso8601String(),
        ]);
    }
}
