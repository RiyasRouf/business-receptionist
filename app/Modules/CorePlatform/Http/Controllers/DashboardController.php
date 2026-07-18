<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\Lead;
use App\Models\Session;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\CorePlatform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stat-card data for the Milestone 6 UI/UX Platform/Business Admin
 * dashboards. Computed from real rows (Session, Lead, Tenant, User) —
 * MeteringService/usage_aggregates exists but is never actually called
 * from the conversation/session-completion path anywhere in the
 * codebase yet, so it would only ever read zeros. Minutes-used here is
 * instead summed directly from Session.duration_seconds, which is
 * genuinely populated by ConversationEngine::completeSession.
 */
class DashboardController
{
    use ApiResponse;

    public function platform(Request $request): JsonResponse
    {
        $monthStart = now()->startOfMonth();

        $activeTenants = Tenant::where('status', 'active')->count();
        $totalUsers = User::count();
        $callsThisMonth = Session::where('started_at', '>=', $monthStart)->count();
        $completedThisMonth = Session::where('started_at', '>=', $monthStart)
            ->where('status', 'completed')->count();
        $aiAnswerRate = $callsThisMonth > 0
            ? round($completedThisMonth / $callsThisMonth * 100, 1)
            : null;

        return $this->success([
            'active_tenants' => $activeTenants,
            'total_users' => $totalUsers,
            'calls_this_month' => $callsThisMonth,
            'ai_answer_rate' => $aiAnswerRate,
        ]);
    }

    public function tenant(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('auth_tenant_id');
        $monthStart = now()->startOfMonth();

        $minutesUsed = (int) round(
            Session::where('tenant_id', $tenantId)
                ->where('started_at', '>=', $monthStart)
                ->sum('duration_seconds') / 60
        );

        $leadsThisMonth = Lead::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $monthStart)->count();

        $followedUp = Lead::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $monthStart)
            ->whereIn('status', ['contacted', 'enrolled'])
            ->count();

        $teamMembers = User::where('tenant_id', $tenantId)->count();

        $pipeline = Lead::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $monthStart)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return $this->success([
            'minutes_used' => $minutesUsed,
            'leads_this_month' => $leadsThisMonth,
            'followed_up' => $followedUp,
            'team_members' => $teamMembers,
            'pipeline' => $pipeline,
        ]);
    }
}
