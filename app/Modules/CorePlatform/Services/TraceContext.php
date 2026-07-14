<?php

namespace App\Modules\CorePlatform\Services;

/**
 * Request-scoped trace_id holder (ADR-013: session_id + tenant_id +
 * trace_id propagated in every log line, metric, trace span). Bound as
 * a singleton per request by AddTraceId middleware — not persisted
 * beyond the request/CLI invocation that created it.
 */
class TraceContext
{
    private ?string $traceId = null;

    public function set(string $traceId): void
    {
        $this->traceId = $traceId;
    }

    public function get(): string
    {
        return $this->traceId ??= (string) \Illuminate\Support\Str::uuid();
    }
}
