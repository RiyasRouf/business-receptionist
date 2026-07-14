<?php

namespace App\Modules\CorePlatform\Http\Middleware;

use App\Modules\CorePlatform\Contracts\JwtServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function __construct(private readonly JwtServiceInterface $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            return response()->json(['error' => 'Missing bearer token'], 401);
        }

        try {
            $claims = $this->jwt->decodeAccessToken(substr($header, 7));
        } catch (\Throwable) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        $request->attributes->set('auth_user_id', $claims['sub']);
        $request->attributes->set('auth_tenant_id', $claims['tenant_id'] ?? null);
        $request->attributes->set('auth_role', $claims['role']);

        return $next($request);
    }
}
