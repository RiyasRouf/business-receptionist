<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * ADR-028: app layer requires Postgres and Redis healthy before
 * accepting traffic — 503 until ready. Distinct from nginx's static
 * /health (always 200, just confirms nginx itself is up) and Laravel's
 * /up (confirms the app booted, not that its dependencies are live).
 */
class ReadinessController
{
    public function check(): JsonResponse
    {
        $checks = ['database' => false, 'redis' => false];

        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
        } catch (\Throwable) {
            // stays false
        }

        try {
            Redis::connection()->ping();
            $checks['redis'] = true;
        } catch (\Throwable) {
            // stays false
        }

        $ready = ! in_array(false, $checks, true);

        return response()->json(['ready' => $ready, 'checks' => $checks], $ready ? 200 : 503);
    }
}
