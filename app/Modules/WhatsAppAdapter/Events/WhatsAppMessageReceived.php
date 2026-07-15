<?php

namespace App\Modules\WhatsAppAdapter\Events;

use App\Modules\WhatsAppAdapter\ValueObjects\InboundMessage;

/**
 * Fired per normalised inbound message. Handled by
 * App\Listeners\ProcessWhatsAppTurn, which routes it into the
 * Conversation Engine.
 */
class WhatsAppMessageReceived
{
    public function __construct(public readonly InboundMessage $message) {}
}
