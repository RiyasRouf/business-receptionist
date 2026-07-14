<?php

namespace App\Modules\ConversationEngine\ValueObjects;

/**
 * 12-state conversation lifecycle. PA-03 (the originally approved
 * artifact per PD-007) was not present in the docs folder — this is
 * derived from the J1 user journey (PRODUCT_REQUIREMENTS.md) plus the
 * guardrail/escalation/intent requirements already binding via ADR-041,
 * ADR-045, ADR-046, ADR-048. Flagged for founder sign-off; not a
 * silent assumption.
 */
enum ConversationState: string
{
    case Initiated = 'initiated';
    case Greeting = 'greeting';
    case Listening = 'listening';
    case GuardrailCheck = 'guardrail_check';
    case IntentClassification = 'intent_classification';
    case KbRetrieval = 'kb_retrieval';
    case Responding = 'responding';
    case LeadCapture = 'lead_capture';
    case Confirming = 'confirming';
    case Escalating = 'escalating';
    case Completing = 'completing';
    case Ended = 'ended';

    public function isTerminal(): bool
    {
        return $this === self::Ended;
    }
}
