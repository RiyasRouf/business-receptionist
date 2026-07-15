<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Primary AI model (OQ-003 resolved, D-015-01). Platform-level default
    // only — per-tenant selection is via tenant_config (ADR-053).
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001'),
    ],

    // Active WhatsApp adapter — 'meta' (direct Cloud API, WhatsAppAdapter)
    // or '360dialog' (Dialog360Adapter). Same pattern as AI_PROVIDER.
    'messaging_provider' => env('MESSAGING_PROVIDER', 'meta'),

    // WhatsApp Business API sandbox (D-013-01) — Module 6B
    'whatsapp' => [
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    ],

    // 360dialog — alternative WhatsApp BSP, selectable via
    // MESSAGING_PROVIDER when Meta's own Developer/business
    // verification is blocked or pending.
    'dialog360' => [
        'api_key' => env('DIALOG360_API_KEY'),
        'base_url' => env('DIALOG360_BASE_URL', 'https://waba-v2.360dialog.io'),
        'webhook_user' => env('DIALOG360_WEBHOOK_USER'),
        'webhook_pass' => env('DIALOG360_WEBHOOK_PASS'),
    ],

];
