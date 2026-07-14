<?php

namespace App\Modules\CorePlatform\Http;

use App\Modules\CorePlatform\Services\TraceContext;
use Illuminate\Http\JsonResponse;

/**
 * Standard success (ADR-029) and error (ADR-026) response contracts.
 * Controllers use this instead of ad-hoc response()->json() so every
 * endpoint shares the same envelope shape.
 */
trait ApiResponse
{
    protected function success(mixed $data = null, ?array $meta = null, int $status = 200): JsonResponse
    {
        $body = array_filter([
            'success' => true,
            'data' => $data,
            'meta' => $meta,
            'trace_id' => app(TraceContext::class)->get(),
        ], fn ($v, $k) => $k !== 'meta' || $v !== null, ARRAY_FILTER_USE_BOTH);

        return response()->json($body, $status);
    }

    protected function error(string $errorCode, string $message, int $status = 400, ?string $sessionId = null): JsonResponse
    {
        return response()->json(array_filter([
            'error_code' => $errorCode,
            'message' => $message,
            'trace_id' => app(TraceContext::class)->get(),
            'session_id' => $sessionId,
            'timestamp' => now()->toIso8601String(),
        ], fn ($v) => $v !== null), $status);
    }
}
