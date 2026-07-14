<?php

namespace App\Modules\CorePlatform\Http\Middleware;

use App\Modules\CorePlatform\Services\TraceContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * X-Trace-ID + X-API-Version on every response (ADR-010, ADR-013).
 * Accepts an inbound X-Trace-ID (propagated from an upstream caller)
 * rather than always minting a fresh one, so a trace can span multiple
 * hops.
 */
class AddTraceId
{
    private const API_VERSION = 'v1';

    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $request->header('X-Trace-ID') ?: (string) Str::uuid();

        app(TraceContext::class)->set($traceId);

        $response = $next($request);
        $response->headers->set('X-Trace-ID', $traceId);
        $response->headers->set('X-API-Version', self::API_VERSION);

        return $response;
    }
}
