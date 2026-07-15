<?php

namespace App\Modules\WhatsAppAdapter\Http\Controllers;

use App\Modules\WhatsAppAdapter\Contracts\MessagingAdapterInterface;
use App\Modules\WhatsAppAdapter\Events\WhatsAppMessageReceived;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class WhatsAppWebhookController
{
    public function __construct(private readonly MessagingAdapterInterface $adapter) {}

    /**
     * Meta's subscription handshake — GET with hub.mode/hub.verify_token/
     * hub.challenge. Must echo the challenge back verbatim on success.
     */
    public function verify(Request $request): Response
    {
        $challenge = $this->adapter->verifyWebhookChallenge(
            $request->query('hub_mode', $request->query('hub.mode', '')),
            $request->query('hub_verify_token', $request->query('hub.verify_token', '')),
            $request->query('hub_challenge', $request->query('hub.challenge', '')),
        );

        if ($challenge === null) {
            return response('Forbidden', 403);
        }

        return response($challenge, 200);
    }

    /**
     * Signature already validated by VerifyWhatsAppSignature middleware
     * (ADR-021). Dedupes by provider message id (ADR-024) before
     * dispatching, since Meta retries webhooks on any non-2xx response.
     */
    public function receive(Request $request): Response
    {
        $messages = $this->adapter->receiveWebhook($request->json()->all());

        foreach ($messages as $message) {
            $dedupeKey = "whatsapp:processed:{$message->providerMessageId}";

            if (! Redis::set($dedupeKey, 1, 'EX', 86400, 'NX')) {
                continue; // Already processed — Meta retry, no-op (ADR-024)
            }

            Log::info('whatsapp.message_received', [
                'from' => $message->from,
                'message_id' => $message->providerMessageId,
            ]);

            // ProcessWhatsAppTurn (app/Listeners) routes this into the
            // ConversationEngine and sends the reply — synchronous, since
            // Meta's webhook timeout comfortably covers one AI turn and
            // this avoids depending on a queue worker being up on staging.
            Event::dispatch(new WhatsAppMessageReceived($message));
        }

        return response('EVENT_RECEIVED', 200);
    }
}
