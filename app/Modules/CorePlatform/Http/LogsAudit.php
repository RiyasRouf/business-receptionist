<?php

namespace App\Modules\CorePlatform\Http;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * One-line audit trail helper for controllers — resource_type/id and
 * diff are the only per-call detail; tenant/user are pulled from the
 * request unless a platform-admin action targets another tenant.
 */
trait LogsAudit
{
    protected function audit(Request $request, string $action, ?string $resourceType = null, ?string $resourceId = null, array $diff = [], ?string $tenantId = null): void
    {
        AuditLog::create([
            'tenant_id' => $tenantId ?? $request->attributes->get('auth_tenant_id'),
            'user_id' => $request->attributes->get('auth_user_id'),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'diff_json' => $diff,
        ]);
    }
}
