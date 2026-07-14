<?php

namespace App\Modules\Metering\Events;

/**
 * Hard limit (including grace) reached — consumers: Voice Gateway,
 * API Gateway, Notification (App Architecture event catalogue).
 */
class UsageLimitReached
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $allowanceType,
        public readonly int $consumed,
        public readonly int $limit,
        public readonly int $grace,
    ) {}
}
