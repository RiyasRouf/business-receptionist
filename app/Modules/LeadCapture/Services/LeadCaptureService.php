<?php

namespace App\Modules\LeadCapture\Services;

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
}
