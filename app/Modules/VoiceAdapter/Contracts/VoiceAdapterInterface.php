<?php

namespace App\Modules\VoiceAdapter\Contracts;

use App\Modules\VoiceAdapter\ValueObjects\CallEvent;
use App\Modules\VoiceAdapter\ValueObjects\CallInstruction;

/**
 * Voice provider webhooks normalised by this adapter before reaching the
 * Voice Gateway (ADR-060). No provider-specific types cross the boundary.
 *
 * Locked interface only — no concrete implementation. Voice provider
 * selection (Twilio/Vonage, OQ-001) is held per D-013-02; WhatsApp
 * (Module 6B) proceeds independently. Implement TwilioAdapter /
 * VonageAdapter against this once the provider decision unblocks.
 */
interface VoiceAdapterInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function receiveWebhook(array $payload, array $headers): CallEvent;

    /**
     * HMAC (or provider-equivalent) signature check — first middleware,
     * request rejected before any processing if invalid (ADR-021).
     */
    public function verifySignature(string $url, array $params, string $signatureHeader): bool;

    public function say(string $text): CallInstruction;

    /**
     * @param  array<string, mixed>  $config
     */
    public function gather(string $prompt, array $config = []): CallInstruction;

    public function hangup(): CallInstruction;

    /**
     * Serialises a chain of instructions into the provider's own markup
     * (TwiML/NCCO) — the only place provider-specific format appears.
     *
     * @param  CallInstruction[]  $instructions
     */
    public function render(array $instructions): string;
}
