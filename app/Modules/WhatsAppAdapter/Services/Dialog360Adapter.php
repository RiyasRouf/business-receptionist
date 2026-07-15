<?php

namespace App\Modules\WhatsAppAdapter\Services;

use App\Modules\WhatsAppAdapter\Contracts\MessagingAdapterInterface;
use App\Modules\WhatsAppAdapter\ValueObjects\InboundMessage;
use App\Modules\WhatsAppAdapter\ValueObjects\OutboundResult;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * 360dialog WhatsApp Business API adapter — alternative to WhatsAppAdapter
 * (direct Meta Cloud API) for teams whose Meta Developer/business
 * verification is blocked or pending. Selected via MESSAGING_PROVIDER
 * (see AppServiceProvider), same pattern as AI_PROVIDER.
 *
 * 360dialog is a Meta Business Solution Provider — inbound webhook
 * payloads use the identical entry[].changes[].value.messages[] shape
 * as Meta's Cloud API, so receiveWebhook() is unchanged from
 * WhatsAppAdapter. Outbound send and webhook auth differ: send uses a
 * static D360-API-KEY header (no phone_number_id in the URL — the key
 * is already scoped to one WABA/number), and there is no Meta-style
 * X-Hub-Signature-256 HMAC — 360dialog secures webhooks with HTTP
 * Basic Auth credentials configured in their Hub UI, checked here via
 * the same "signature header" slot the interface already has (the
 * middleware passes whichever header the active provider expects; see
 * VerifyWhatsAppSignature).
 */
class Dialog360Adapter implements MessagingAdapterInterface
{
    public function receiveWebhook(array $payload): array
    {
        $messages = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['messages'] ?? [] as $raw) {
                    $body = $raw['text']['body'] ?? $raw['button']['text'] ?? '';

                    $messages[] = new InboundMessage(
                        providerMessageId: $raw['id'],
                        from: $raw['from'],
                        body: $body,
                        timestamp: (int) $raw['timestamp'],
                        channel: 'whatsapp',
                    );
                }
            }
        }

        return $messages;
    }

    public function send(string $to, string $body, array $config = []): OutboundResult
    {
        $baseUrl = rtrim(Config::string('services.dialog360.base_url'), '/');

        $response = Http::withHeaders(['D360-API-KEY' => Config::string('services.dialog360.api_key')])
            ->timeout(15)
            ->post("{$baseUrl}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $body],
            ]);

        if ($response->failed()) {
            return new OutboundResult(
                success: false,
                providerMessageId: null,
                error: $response->json('error.message') ?? $response->body(),
            );
        }

        return new OutboundResult(
            success: true,
            providerMessageId: $response->json('messages.0.id'),
            error: null,
        );
    }

    /**
     * No-op — 360dialog webhooks are registered directly in the Hub UI,
     * not via Meta's hub.challenge GET handshake. Nothing calls this for
     * a 360dialog-configured endpoint in practice, but it's implemented
     * for interface compliance rather than left throwing.
     */
    public function verifyWebhookChallenge(string $mode, string $verifyToken, string $challenge): ?string
    {
        return null;
    }

    /**
     * 360dialog has no HMAC-over-body scheme — it sends HTTP Basic Auth
     * credentials (configured in their Hub) on every webhook request.
     * $rawBody is unused here (kept for interface compatibility); the
     * "signature header" is actually the raw Authorization header value,
     * e.g. "Basic base64(user:pass)".
     */
    public function verifySignature(string $rawBody, string $signatureHeader): bool
    {
        $expectedUser = Config::string('services.dialog360.webhook_user');
        $expectedPass = Config::string('services.dialog360.webhook_pass');
        $expected = 'Basic '.base64_encode("{$expectedUser}:{$expectedPass}");

        return hash_equals($expected, $signatureHeader);
    }
}
