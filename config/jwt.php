<?php

return [
    // ADR-058: access token 15 min (memory), refresh token 7 days (HttpOnly cookie)
    'secret' => env('JWT_SECRET'),
    'ttl' => (int) env('JWT_TTL', 15),
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 10080),
    'algo' => 'HS256',
];
