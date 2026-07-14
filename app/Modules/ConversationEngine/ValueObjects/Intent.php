<?php

namespace App\Modules\ConversationEngine\ValueObjects;

// AI_ARCHITECTURE.md §2: enquiry, lead_capture, escalation, out_of_scope
enum Intent: string
{
    case Enquiry = 'enquiry';
    case LeadCapture = 'lead_capture';
    case Escalation = 'escalation';
    case OutOfScope = 'out_of_scope';
}
