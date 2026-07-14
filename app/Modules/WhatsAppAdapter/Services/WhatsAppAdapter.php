<?php

namespace App\Modules\WhatsAppAdapter\Services;

use App\Modules\WhatsAppAdapter\Contracts\MessagingAdapterInterface;
use App\Modules\WhatsAppAdapter\ValueObjects\InboundMessage;
use App\Modules\WhatsAppAdapter\ValueObjects\OutboundResult;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Business (Meta Cloud API) sandbox adapter. D-013-01.
 */
class WhatsAppAdapter implements MessagingAdapterInterface
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
        $phoneNumberId = $config['phone_number_id'] ?? Config::string('services.whatsapp.phone_number_id');
        $version = Config::string('services.whatsapp.api_version');

        $response = Http::withToken(Config::string('services.whatsapp.access_token'))
            ->timeout(15)
            ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
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

    public function verifyWebhookChallenge(string $mode, string $verifyToken, string $challenge): ?string
    {
        if ($mode !== 'subscribe') {
            return null;
        }

        return hash_equals(Config::string('services.whatsapp.verify_token'), $verifyToken)
            ? $challenge
            : null;
    }

    public function verifySignature(string $rawBody, string $signatureHeader): bool
    {
        if (! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, Config::string('services.whatsapp.app_secret'));
        $provided = substr($signatureHeader, 7);

        return hash_equals($expected, $provided);
    }
}
