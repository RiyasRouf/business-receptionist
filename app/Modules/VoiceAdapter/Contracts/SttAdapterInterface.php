<?php

namespace App\Modules\VoiceAdapter\Contracts;

use App\Modules\VoiceAdapter\ValueObjects\TranscriptChunk;

/**
 * Independently configurable per tenant (ADR-062). First chunk target
 * < 300ms. Locked interface only — provider held per D-013-02.
 */
interface SttAdapterInterface
{
    /**
     * @param  array<string, mixed>  $config
     * @return iterable<TranscriptChunk>
     */
    public function streamTranscribe(string $audioStreamUrl, array $config = []): iterable;
}
