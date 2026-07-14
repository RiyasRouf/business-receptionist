<?php

namespace App\Modules\VoiceAdapter\ValueObjects;

final class TranscriptChunk
{
    public function __construct(
        public readonly string $text,
        public readonly bool $isFinal,
        public readonly float $confidence,
    ) {}
}
