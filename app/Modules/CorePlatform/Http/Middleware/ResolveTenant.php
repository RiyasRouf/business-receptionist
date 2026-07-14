<?php

namespace App\Modules\CorePlatform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves tenant_id once, from the JWT (never from client-supplied route
 * or body params), and binds it into the container for the request
 * lifecycle so repositories can inject it on every query (ADR-008, ADR-030).
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->attributes->get('auth_tenant_id');

        if ($tenantId === null) {
            return response()->json(['error' => 'No tenant context on token'], 403);
        }

        app()->instance('current_tenant_id', $tenantId);

        return $next($request);
    }
}
