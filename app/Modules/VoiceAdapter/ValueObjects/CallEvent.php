<?php

namespace App\Modules\VoiceAdapter\ValueObjects;

/**
 * Normalised inbound call/status event — no provider-specific types
 * cross the adapter boundary (ADR-011, mirrors ADR-060's pattern).
 */
final class CallEvent
{
    public function __construct(
        public readonly string $providerCallId,
        public readonly string $from,
        public readonly string $to,
        public readonly string $status,
        public readonly ?string $digits = null,
        public readonly ?string $speechResult = null,
    ) {}
}
