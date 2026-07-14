<?php

namespace App\Modules\WhatsAppAdapter\Events;

use App\Modules\WhatsAppAdapter\ValueObjects\InboundMessage;

/**
 * Fired per normalised inbound message. No listeners wired yet —
 * routing to the Conversation Engine is Module 7 (Sprint 3).
 */
class WhatsAppMessageReceived
{
    public function __construct(public readonly InboundMessage $message) {}
}
