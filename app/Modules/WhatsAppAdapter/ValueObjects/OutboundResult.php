<?php

namespace App\Modules\WhatsAppAdapter\ValueObjects;

final class OutboundResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerMessageId,
        public readonly ?string $error,
    ) {}
}
