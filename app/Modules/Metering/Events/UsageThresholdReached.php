<?php

namespace App\Modules\Metering\Events;

/**
 * Warning threshold crossed (80/90/100% of allowance) — App Architecture
 * event catalogue. No listeners wired yet; Notification delivery is
 * Phase 2 (deferred per Milestone 5 MVP scope boundary).
 */
class UsageThresholdReached
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $allowanceType,
        public readonly int $consumed,
        public readonly int $limit,
        public readonly int $thresholdPct,
    ) {}
}
