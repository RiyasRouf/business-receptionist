<?php

namespace App\Modules\CorePlatform\Contracts;

use App\Models\User;

interface JwtServiceInterface
{
    /**
     * Issue a short-lived access token carrying user_id, tenant_id, and role claims.
     */
    public function issueAccessToken(User $user): string;

    /**
     * Issue a long-lived, opaque refresh token. Persists its hash on the user record.
     */
    public function issueRefreshToken(User $user): string;

    /**
     * Decode and validate an access token, returning its claims.
     *
     * @return array<string, mixed>
     *
     * @throws \Firebase\JWT\ExpiredException
     * @throws \UnexpectedValueException
     */
    public function decodeAccessToken(string $token): array;
}
