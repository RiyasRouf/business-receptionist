<?php

namespace App\Modules\WhatsAppAdapter\Contracts;

use App\Modules\WhatsAppAdapter\ValueObjects\InboundMessage;
use App\Modules\WhatsAppAdapter\ValueObjects\OutboundResult;

/**
 * No provider-specific types cross this boundary — mirrors the
 * VoiceAdapterInterface pattern (ADR-060), applied to messaging channels.
 */
interface MessagingAdapterInterface
{
    /**
     * @param  array<string, mixed>  $payload  Raw provider webhook body
     * @return InboundMessage[]
     */
    public function receiveWebhook(array $payload): array;

    public function send(string $to, string $body, array $config = []): OutboundResult;

    /**
     * Provider's webhook subscription handshake (e.g. Meta's hub.challenge
     * GET verification). Returns the challenge string to echo back, or
     * null if the verify token didn't match.
     */
    public function verifyWebhookChallenge(string $mode, string $verifyToken, string $challenge): ?string;

    /**
     * HMAC (or equivalent) signature check — first middleware, request
     * rejected before any processing if invalid (ADR-021).
     */
    public function verifySignature(string $rawBody, string $signatureHeader): bool;
}
