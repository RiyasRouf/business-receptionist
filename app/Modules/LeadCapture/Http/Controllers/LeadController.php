<?php

namespace App\Modules\LeadCapture\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\Session;
use App\Models\Summary;
use App\Models\Transcript;
use App\Modules\CorePlatform\Http\ApiResponse;
use Illuminate\Http\Request;

/**
 * F-14 (Lead Status Management), F-15 (Staff Dashboard: leads list +
 * detail). Cursor pagination for this high-volume/live endpoint
 * (ADR-027) — offset pagination is for admin/config endpoints only.
 */
class LeadController
{
    use ApiResponse;

    private const VALID_STATUSES = ['partial', 'complete', 'contacted', 'enrolled', 'closed'];

    public function index(Request $request)
    {
        $tenantId = $request->attributes->get('auth_tenant_id');

        $query = Lead::where('tenant_id', $tenantId)->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $leads = $query->cursorPaginate(20)->withQueryString();

        return $this->success(
            data: $leads->getCollection()->map(fn (Lead $lead) => $this->summarise($lead)),
            meta: [
                'next_cursor' => $leads->nextCursor()?->encode(),
                'prev_cursor' => $leads->previousCursor()?->encode(),
                'has_more' => $leads->hasMorePages(),
            ],
        );
    }

    public function show(Request $request, string $leadId)
    {
        $tenantId = $request->attributes->get('auth_tenant_id');

        $lead = Lead::where('tenant_id', $tenantId)->where('lead_id', $leadId)->first();

        if ($lead === null) {
            return $this->error('lead_not_found', 'Lead not found.', 404);
        }

        $session = Session::find($lead->session_id);
        $transcript = Transcript::where('session_id', $lead->session_id)->first();
        $summary = Summary::where('session_id', $lead->session_id)->first();
        $followedUp = in_array($lead->status, ['contacted', 'enrolled', 'closed'], true);

        return $this->success([
            'lead_id' => $lead->lead_id,
            'session_id' => $lead->session_id,
            'status' => $lead->status,
            'fields' => $lead->fields_json,
            'staff_notes' => $lead->staff_notes,
            'transcript' => $transcript?->content,
            'summary' => $summary?->content,
            'action_items' => $summary?->action_items_json,
            'created_at' => $lead->created_at?->toIso8601String(),
            // Real events from actual data, not simulated — each entry
            // only appears once its underlying row/state actually exists.
            'timeline' => array_values(array_filter([
                $session?->started_at ? ['label' => 'Call received', 'at' => $session->started_at->toIso8601String(), 'done' => true] : null,
                ['label' => 'Lead captured', 'at' => $lead->created_at?->toIso8601String(), 'done' => true],
                $transcript ? ['label' => 'Transcript generated', 'at' => $transcript->created_at?->toIso8601String(), 'done' => true] : ['label' => 'Transcript generated', 'at' => null, 'done' => false],
                $summary ? ['label' => 'Summary written', 'at' => $summary->created_at?->toIso8601String(), 'done' => true] : ['label' => 'Summary written', 'at' => null, 'done' => false],
                $followedUp
                    ? ['label' => 'Followed up', 'at' => $lead->updated_at?->toIso8601String(), 'done' => true]
                    : ['label' => 'Awaiting follow-up', 'at' => null, 'done' => false],
            ])),
        ]);
    }

    public function updateNotes(Request $request, string $leadId)
    {
        $tenantId = $request->attributes->get('auth_tenant_id');
        $validated = $request->validate(['notes' => ['required', 'string', 'max:5000']]);

        $lead = Lead::where('tenant_id', $tenantId)->where('lead_id', $leadId)->first();

        if ($lead === null) {
            return $this->error('lead_not_found', 'Lead not found.', 404);
        }

        $lead->staff_notes = $validated['notes'];
        $lead->save();

        return $this->success(['lead_id' => $lead->lead_id, 'staff_notes' => $lead->staff_notes]);
    }

    public function updateStatus(Request $request, string $leadId)
    {
        $tenantId = $request->attributes->get('auth_tenant_id');
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', self::VALID_STATUSES)],
        ]);

        $lead = Lead::where('tenant_id', $tenantId)->where('lead_id', $leadId)->first();

        if ($lead === null) {
            return $this->error('lead_not_found', 'Lead not found.', 404);
        }

        $oldStatus = $lead->status;
        $lead->status = $validated['status'];
        $lead->save();

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $request->attributes->get('auth_user_id'),
            'action' => 'lead.status_changed',
            'resource_type' => 'lead',
            'resource_id' => $lead->lead_id,
            'diff_json' => ['from' => $oldStatus, 'to' => $validated['status']],
        ]);

        return $this->success(['lead_id' => $lead->lead_id, 'status' => $lead->status]);
    }

    private function summarise(Lead $lead): array
    {
        $fields = $lead->fields_json ?? [];

        return [
            'lead_id' => $lead->lead_id,
            'session_id' => $lead->session_id,
            'status' => $lead->status,
            'parent_name' => $fields['parent_name'] ?? null,
            'child_name' => $fields['child_name'] ?? null,
            'created_at' => $lead->created_at?->toIso8601String(),
        ];
    }
}
