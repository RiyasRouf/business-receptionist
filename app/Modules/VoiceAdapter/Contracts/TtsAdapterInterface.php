<?php

namespace App\Modules\VoiceAdapter\Contracts;

/**
 * Independently configurable per tenant (ADR-062). Sentence-boundary
 * buffered by the caller (ADR-050) — this adapter just synthesises one
 * sentence at a time. Locked interface only — provider held per
 * D-013-02.
 */
interface TtsAdapterInterface
{
    /**
     * @param  array<string, mixed>  $config
     * @return string Binary audio data
     */
    public function synthesize(string $text, array $config = []): string;
}
