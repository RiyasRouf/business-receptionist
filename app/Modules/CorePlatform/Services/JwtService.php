<?php

namespace App\Modules\CorePlatform\Services;

use App\Models\User;
use App\Modules\CorePlatform\Contracts\JwtServiceInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class JwtService implements JwtServiceInterface
{
    public function issueAccessToken(User $user): string
    {
        $now = time();

        $claims = [
            'sub' => $user->user_id,
            'tenant_id' => $user->tenant_id,
            'role' => $user->role,
            'iat' => $now,
            'exp' => $now + (Config::integer('jwt.ttl') * 60),
        ];

        return JWT::encode($claims, Config::string('jwt.secret'), Config::string('jwt.algo'));
    }

    public function issueRefreshToken(User $user): string
    {
        $token = Str::random(64);

        $user->refresh_token_hash = hash('sha256', $token);
        $user->refresh_token_expires_at = now()->addMinutes(Config::integer('jwt.refresh_ttl'));
        $user->save();

        return $token;
    }

    public function decodeAccessToken(string $token): array
    {
        $decoded = JWT::decode($token, new Key(Config::string('jwt.secret'), Config::string('jwt.algo')));

        return (array) $decoded;
    }
}
