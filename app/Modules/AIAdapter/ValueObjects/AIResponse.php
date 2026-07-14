<?php

namespace App\Modules\AIAdapter\ValueObjects;

/**
 * All provider responses normalised to this before returning to the
 * Conversation Engine — no provider-specific types cross the adapter
 * boundary (ADR-043).
 */
final class AIResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $finishReason,
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly string $modelId,
        public readonly string $provider,
    ) {}

    public function totalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }
}
