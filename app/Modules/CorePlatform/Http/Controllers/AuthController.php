<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\User;
use App\Modules\CorePlatform\Contracts\JwtServiceInterface;
use App\Modules\CorePlatform\Http\ApiResponse;
use App\Modules\CorePlatform\Services\AccountLockoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController
{
    use ApiResponse;

    public function __construct(
        private readonly JwtServiceInterface $jwt,
        private readonly AccountLockoutService $lockout,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        if ($user->isLocked()) {
            return $this->error('account_locked', 'Account locked. Try again later.', 423);
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            $this->lockout->recordFailedAttempt($user);

            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        $this->lockout->recordSuccessfulLogin($user);

        return $this->issueTokenResponse($user);
    }

    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->cookie('refresh_token');

        if (! $refreshToken) {
            return $this->error('missing_refresh_token', 'Missing refresh token.', 401);
        }

        $hash = hash('sha256', $refreshToken);
        $user = User::where('refresh_token_hash', $hash)->first();

        if (! $user || $user->refresh_token_expires_at === null || $user->refresh_token_expires_at->isPast()) {
            return $this->error('invalid_refresh_token', 'Invalid or expired refresh token.', 401);
        }

        return $this->issueTokenResponse($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $userId = $request->attributes->get('auth_user_id');

        if ($userId) {
            User::where('user_id', $userId)->update([
                'refresh_token_hash' => null,
                'refresh_token_expires_at' => null,
            ]);
        }

        return $this->success(['message' => 'Logged out'])
            ->withCookie(cookie()->forget('refresh_token'));
    }

    private function issueTokenResponse(User $user): JsonResponse
    {
        $accessToken = $this->jwt->issueAccessToken($user);
        $refreshToken = $this->jwt->issueRefreshToken($user);

        $cookie = cookie(
            name: 'refresh_token',
            value: $refreshToken,
            minutes: Config::integer('jwt.refresh_ttl'),
            path: '/',
            domain: null,
            // Driven by the actual request scheme, not hardcoded — staging
            // currently has no SSL/domain (D-014-03), so a hardcoded true
            // makes the browser silently refuse to store this cookie at
            // all, breaking session persistence across refreshes. Self-
            // corrects once staging/production get HTTPS, no code change
            // needed then.
            secure: request()->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: 'strict',
        );

        return $this->success([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => Config::integer('jwt.ttl') * 60,
            'user' => [
                'user_id' => $user->user_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
                'custom_role_id' => $user->custom_role_id,
                // null = unrestricted admin (not "no permissions") —
                // frontend must distinguish the two, not treat both as [].
                'permissions' => $user->custom_role_id
                    ? ($user->customRole?->permissions_json ?? [])
                    : null,
            ],
        ])->withCookie($cookie);
    }
}
