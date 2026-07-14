<?php

namespace App\Modules\ConversationEngine\ValueObjects;

final class TurnResult
{
    public function __construct(
        public readonly string $response,
        public readonly ConversationState $state,
        public readonly bool $guardrailTriggered = false,
        public readonly ?string $guardrailStage = null,
    ) {}
}
