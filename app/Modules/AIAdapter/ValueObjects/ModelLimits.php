<?php

namespace App\Modules\AIAdapter\ValueObjects;

final class ModelLimits
{
    public function __construct(
        public readonly string $modelId,
        public readonly int $contextWindow,
        public readonly int $maxOutputTokens,
    ) {}
}
