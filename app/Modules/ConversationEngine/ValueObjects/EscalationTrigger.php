<?php

namespace App\Modules\ConversationEngine\ValueObjects;

// AI_ARCHITECTURE.md §9 Escalation Pipeline (ADR-045)
enum EscalationTrigger: string
{
    case LowConfidence = 'low_confidence';
    case ExplicitRequest = 'explicit_request';
    case GuardrailPostCheckFailure = 'guardrail_post_check_failure';
    case MaxTurnLimit = 'max_turn_limit';
    case AiTotalFailure = 'ai_total_failure';
}
