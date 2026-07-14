<?php

namespace App\Modules\LeadCapture\Services;

use App\Models\AuditLog;
use App\Models\Lead;
use App\Modules\ConversationEngine\ValueObjects\LeadField;

/**
 * F-06 (collect L-01..L-13 conversationally), F-07 (partial lead saving
 * — never discards incomplete leads).
 */
class LeadCaptureService
{
    public function getOrCreateForSession(string $tenantId, string $sessionId): Lead
    {
        $existing = Lead::where('session_id', $sessionId)->first();

        if ($existing !== null) {
            return $existing;
        }

        // tenant_id must be set before fields_json for the PII mutator
        // to have a tenant to encrypt against (see Lead::setFieldsJsonAttribute).
        return Lead::create([
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'status' => 'partial',
            'fields_json' => [],
        ]);
    }

    public function captureField(Lead $lead, LeadField $field, string $value): Lead
    {
        $fields = $lead->fields_json ?? [];
        $fields[$field->value] = $value;

        $lead->fields_json = $fields;
        $lead->status = $this->allMvpMinimumPresent($fields) ? 'complete' : 'partial';

        if ($field === LeadField::ParentPhone) {
            $lead->phone_hash = $this->hashPhone($value);
            $this->flagIfDuplicate($lead);
        }

        $lead->save();

        return $lead;
    }

    /** @return LeadField[] */
    public function missingMvpFields(Lead $lead): array
    {
        $present = array_keys($lead->fields_json ?? []);

        return array_values(array_filter(
            LeadField::mvpMinimum(),
            fn (LeadField $f) => ! in_array($f->value, $present, true)
        ));
    }

    private function allMvpMinimumPresent(array $fields): bool
    {
        foreach (LeadField::mvpMinimum() as $field) {
            if (empty($fields[$field->value])) {
                return false;
            }
        }

        return true;
    }

    /**
     * fields_json values are individually encrypted with a random nonce
     * per call — the same phone number never produces the same
     * ciphertext twice, so it can't be looked up directly (F-06/Sprint 3
     * "deduplication" AC). Normalised-and-hashed instead. Detection +
     * staff-visible flag, not automatic merging — merging duplicate
     * leads automatically risks silently dropping data from one of them.
     */
    private function hashPhone(string $rawPhone): string
    {
        $normalised = preg_replace('/[^0-9+]/', '', $rawPhone);

        return hash('sha256', $normalised);
    }

    private function flagIfDuplicate(Lead $lead): void
    {
        $duplicate = Lead::where('tenant_id', $lead->tenant_id)
            ->where('phone_hash', $lead->phone_hash)
            ->where('lead_id', '!=', $lead->lead_id ?? '')
            ->first();

        if ($duplicate === null) {
            return;
        }

        AuditLog::create([
            'tenant_id' => $lead->tenant_id,
            'action' => 'lead.possible_duplicate',
            'resource_type' => 'lead',
            'resource_id' => $lead->lead_id,
            'diff_json' => ['duplicate_of_lead_id' => $duplicate->lead_id],
        ]);
    }
}
