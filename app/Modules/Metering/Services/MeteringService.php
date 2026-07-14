<?php

namespace App\Modules\Metering\Services;

use App\Models\UsageAggregate;
use App\Models\UsageAllowance;
use App\Models\UsageEvent;
use App\Modules\Metering\Events\UsageLimitReached;
use App\Modules\Metering\Events\UsageThresholdReached;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

class MeteringService
{
    private const THRESHOLDS = [80, 90, 100];

    /**
     * Log a billable event (ADR-018), then enforce/aggregate against the
     * tenant's allowance for that type (ADR-059) if one is configured.
     * Redis counter is the fast enforcement path (<50ms per App
     * Architecture); usage_aggregates in Postgres is the source of truth.
     */
    public function recordUsage(
        string $tenantId,
        string $allowanceType,
        int $quantity,
        string $unit,
        ?string $sessionId = null,
    ): void {
        UsageEvent::create([
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'event_type' => $allowanceType,
            'quantity' => $quantity,
            'unit' => $unit,
        ]);

        $allowance = UsageAllowance::where('tenant_id', $tenantId)
            ->where('allowance_type', $allowanceType)
            ->first();

        if ($allowance === null) {
            return;
        }

        [$periodStart, $periodEnd] = $this->periodBounds($allowance->reset_day);

        $aggregate = DB::transaction(function () use ($tenantId, $allowanceType, $quantity, $periodStart, $periodEnd) {
            $row = UsageAggregate::where('tenant_id', $tenantId)
                ->where('allowance_type', $allowanceType)
                ->where('period_start', $periodStart->toDateString())
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $row = UsageAggregate::create([
                    'tenant_id' => $tenantId,
                    'allowance_type' => $allowanceType,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'consumed' => 0,
                ]);
            }

            $before = $row->consumed;
            $row->increment('consumed', $quantity);

            return ['before' => $before, 'after' => $before + $quantity];
        });

        $redisKey = "tenant:{$tenantId}:usage:{$allowanceType}:{$periodStart->toDateString()}";
        Redis::set($redisKey, $aggregate['after'], 'EX', now()->diffInSeconds($periodEnd) + 86400);

        $this->checkThresholds($tenantId, $allowanceType, $aggregate['before'], $aggregate['after'], $allowance);
    }

    /**
     * Fast-path check for API Gateway / Voice Gateway enforcement.
     * Falls back to the DB aggregate if Redis is unavailable (ADR-057
     * degradation pattern).
     */
    public function currentUsage(string $tenantId, string $allowanceType): int
    {
        $allowance = UsageAllowance::where('tenant_id', $tenantId)
            ->where('allowance_type', $allowanceType)
            ->first();

        if ($allowance === null) {
            return 0;
        }

        [$periodStart] = $this->periodBounds($allowance->reset_day);
        $redisKey = "tenant:{$tenantId}:usage:{$allowanceType}:{$periodStart->toDateString()}";

        try {
            $cached = Redis::get($redisKey);

            if ($cached !== null) {
                return (int) $cached;
            }
        } catch (\Throwable) {
            // Redis down — fall through to DB.
        }

        return (int) UsageAggregate::where('tenant_id', $tenantId)
            ->where('allowance_type', $allowanceType)
            ->where('period_start', $periodStart->toDateString())
            ->value('consumed') ?? 0;
    }

    public function isOverLimit(string $tenantId, string $allowanceType): bool
    {
        $allowance = UsageAllowance::where('tenant_id', $tenantId)
            ->where('allowance_type', $allowanceType)
            ->first();

        if ($allowance === null) {
            return false;
        }

        return $this->currentUsage($tenantId, $allowanceType) > ($allowance->limit + $allowance->grace);
    }

    private function checkThresholds(
        string $tenantId,
        string $allowanceType,
        int $before,
        int $after,
        UsageAllowance $allowance,
    ): void {
        if ($allowance->limit <= 0) {
            return;
        }

        $beforePct = (int) floor(($before / $allowance->limit) * 100);
        $afterPct = (int) floor(($after / $allowance->limit) * 100);

        foreach (self::THRESHOLDS as $threshold) {
            if ($beforePct < $threshold && $afterPct >= $threshold) {
                Event::dispatch(new UsageThresholdReached(
                    $tenantId,
                    $allowanceType,
                    $after,
                    $allowance->limit,
                    $threshold,
                ));
            }
        }

        $limitWithGrace = $allowance->limit + $allowance->grace;

        if ($before <= $limitWithGrace && $after > $limitWithGrace) {
            Event::dispatch(new UsageLimitReached(
                $tenantId,
                $allowanceType,
                $after,
                $allowance->limit,
                $allowance->grace,
            ));
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodBounds(int $resetDay): array
    {
        $today = Carbon::today();
        $resetDay = max(1, min(28, $resetDay)); // clamp — avoids month-length edge cases

        $start = $today->copy()->day($resetDay);

        if ($start->gt($today)) {
            $start = $start->subMonthNoOverflow();
        }

        $end = $start->copy()->addMonthNoOverflow();

        return [$start, $end];
    }
}
