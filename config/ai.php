<?php

return [
    // Platform-level default only. Per-tenant provider/model selection is
    // via tenant_config.ai_model_version (ADR-053), set through Platform
    // Admin — not this env var.
    'provider' => env('AI_PROVIDER', 'mock'),
];
