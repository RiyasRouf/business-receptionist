<?php

namespace App\Modules\CorePlatform\Http\Middleware;

use App\Modules\CorePlatform\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    use ApiResponse;

    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $role = $request->attributes->get('auth_role');

        if (! in_array($role, $allowedRoles, true)) {
            return $this->error('forbidden', 'Forbidden.', 403);
        }

        return $next($request);
    }
}
