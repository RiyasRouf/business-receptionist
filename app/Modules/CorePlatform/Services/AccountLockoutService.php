<?php

namespace App\Modules\CorePlatform\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Config;

class AccountLockoutService
{
    public function recordFailedAttempt(User $user): void
    {
        $user->failed_login_attempts++;

        $threshold = Config::integer('auth_security.lockout_threshold');

        if ($user->failed_login_attempts >= $threshold) {
            $user->locked_until = now()->addMinutes(Config::integer('auth_security.lockout_minutes'));

            AuditLog::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->user_id,
                'action' => 'auth.account_locked',
                'resource_type' => 'user',
                'resource_id' => $user->user_id,
                'diff_json' => ['failed_attempts' => $user->failed_login_attempts, 'threshold' => $threshold],
            ]);
        }

        $user->save();

        AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->user_id,
            'action' => 'auth.failed_attempt',
            'resource_type' => 'user',
            'resource_id' => $user->user_id,
            'diff_json' => ['failed_attempts' => $user->failed_login_attempts],
        ]);
    }

    public function recordSuccessfulLogin(User $user): void
    {
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->save();

        AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->user_id,
            'action' => 'auth.login_success',
            'resource_type' => 'user',
            'resource_id' => $user->user_id,
        ]);
    }
}
