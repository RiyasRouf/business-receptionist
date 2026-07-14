<?php

namespace App\Modules\CorePlatform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $role = $request->attributes->get('auth_role');

        if (! in_array($role, $allowedRoles, true)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
