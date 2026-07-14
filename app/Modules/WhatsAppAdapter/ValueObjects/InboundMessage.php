<?php

namespace App\Modules\WhatsAppAdapter\ValueObjects;

/**
 * Normalised inbound message — no provider-specific types cross the
 * adapter boundary (mirrors ADR-060's pattern for MessagingAdapterInterface).
 */
final class InboundMessage
{
    public function __construct(
        public readonly string $providerMessageId,
        public readonly string $from,
        public readonly string $body,
        public readonly int $timestamp,
        public readonly string $channel,
    ) {}
}
