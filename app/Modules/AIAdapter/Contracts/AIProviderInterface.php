<?php

namespace App\Modules\AIAdapter\Contracts;

use App\Modules\AIAdapter\ValueObjects\AIResponse;
use App\Modules\AIAdapter\ValueObjects\ModelLimits;

/**
 * Provider-swappable per tenant via config (ADR-002). No provider-specific
 * types cross this boundary (ADR-043) — every implementation normalises
 * to AIResponse/ModelLimits.
 *
 * @phpstan-type Message array{role: 'system'|'user'|'assistant', content: string}
 */
interface AIProviderInterface
{
    /**
     * @param  Message[]  $messages
     * @param  array<string, mixed>  $config  e.g. ['model' => ..., 'temperature' => ..., 'max_tokens' => ...]
     */
    public function complete(array $messages, array $config = []): AIResponse;

    /**
     * @param  Message[]  $messages
     * @param  array<string, mixed>  $config
     * @return iterable<AIResponse>
     */
    public function stream(array $messages, array $config = []): iterable;

    /**
     * @param  array<string, mixed>  $config
     * @return float[]
     */
    public function embed(string $text, array $config = []): array;

    public function getModelLimits(string $modelId): ModelLimits;
}
