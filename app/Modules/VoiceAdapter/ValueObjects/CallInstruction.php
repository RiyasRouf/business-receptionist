<?php

namespace App\Modules\VoiceAdapter\ValueObjects;

/**
 * Abstract call-control instruction. A concrete adapter (Twilio/Vonage —
 * held per D-013-02) translates this into its own markup (TwiML/NCCO)
 * at the boundary — the rest of the app never sees provider markup.
 */
final class CallInstruction
{
    public const TYPE_SAY = 'say';

    public const TYPE_GATHER = 'gather';

    public const TYPE_HANGUP = 'hangup';

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public readonly string $type,
        public readonly array $params = [],
    ) {}
}
