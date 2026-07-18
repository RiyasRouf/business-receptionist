<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\AuditLog;
use App\Modules\CorePlatform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with(['user:user_id,name,email', 'tenant:tenant_id,name'])
            ->orderByDesc('created_at');

        if ($tenantId = $request->query('tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }
        if ($action = $request->query('action')) {
            $query->where('action', 'like', "%{$action}%");
        }

        $logs = $query->cursorPaginate(50)->withQueryString();

        return $this->success(
            data: $logs->getCollection(),
            meta: ['next_cursor' => $logs->nextCursor()?->encode(), 'has_more' => $logs->hasMorePages()],
        );
    }
}
