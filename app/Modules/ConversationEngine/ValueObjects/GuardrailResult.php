<?php

namespace App\Modules\ConversationEngine\ValueObjects;

final class GuardrailResult
{
    public function __construct(
        public readonly bool $passed,
        public readonly ?string $reason = null,
        public readonly ?string $fallbackResponse = null,
        public readonly bool $shouldEscalate = false,
    ) {}

    public static function pass(): self
    {
        return new self(passed: true);
    }

    public static function fail(string $reason, string $fallbackResponse, bool $shouldEscalate = false): self
    {
        return new self(passed: false, reason: $reason, fallbackResponse: $fallbackResponse, shouldEscalate: $shouldEscalate);
    }
}
