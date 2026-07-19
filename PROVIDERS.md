# Provider Integrations

## Architecture

- **Tenant-owned (Model B):** each tenant brings their own Twilio account for Voice + WhatsApp. Config lives in `tenant_integrations` (one row per tenant). Business Admin edits at `/business/voice`; Platform Admin can assist via `/admin/voice/{tenantId}` — same row, same live tests.
- **Platform-owned:** speech AI (Deepgram STT/TTS) is configured by Platform Admin at `/admin/voice-config` (`platform_voice_providers`). Separate from LLM providers (`/admin/ai-providers`).

## Encryption

All secrets (`voice_auth_token`, `voice_api_key`, `voice_api_secret`, `whatsapp_*` equivalents, Deepgram `api_key`) use Laravel `encrypted` casts — AES-256 via `APP_KEY`, encrypted at rest, `hidden` from every API response. The API returns `*_set` booleans instead. Blank secret on save = keep existing.

## Live connection testing

Every test hits the real provider and is recorded in `provider_test_logs` (provider, scope, action, ok, latency_ms, detail). Last result is denormalized onto the config row (`*_last_tested_at`, `*_latency_ms`, `*_last_error`) for instant status badges.

| Surface | Endpoint | What it really does |
|---|---|---|
| Voice verify | `POST /integrations/voice/verify` | `GET /2010-04-01/Accounts/{sid}.json` |
| Voice test call | `POST /integrations/voice/test-call {to}` | `POST .../Calls.json` with inline TwiML |
| Sync numbers | `POST /integrations/voice/sync-numbers` | `GET .../IncomingPhoneNumbers.json` |
| Wire webhook | `POST /integrations/voice/wire-webhook` | Updates the number's `VoiceUrl` to our webhook |
| TwiML preview | `GET /integrations/voice/twiml` | Renders greeting + optional `<Connect><Stream>` |
| WA verify | `POST /integrations/whatsapp/verify` | Account fetch with WA credentials |
| WA test send | `POST /integrations/whatsapp/test-send {to}` | `POST .../Messages.json` `To=whatsapp:...` |
| Balance | `GET /integrations/{scope}/balance` | `GET .../Balance.json` |
| History | `GET /integrations/{scope}/logs` | provider_test_logs |
| Deepgram verify | `POST /admin/voice-providers/{id}/verify` | `GET /v1/auth/token` |
| List models | `GET /admin/voice-providers/{id}/models` | `GET /v1/models` |
| STT test | `POST .../stt-test` | Real transcription of Deepgram's sample WAV via `/v1/listen` |
| TTS test | `POST .../tts-test` | Real synthesis via `/v1/speak`, reports audio bytes |
| Latency | `POST .../latency-test` | 3× auth round-trips, min/avg ms |

Platform-assist twins exist under `/admin/tenants/{tenantId}/integrations/...`.

Result envelope (always HTTP 200): `{ok, status, latency_ms, data}`. `status` values: `connected`, `authentication_failed`, `invalid_sid`, `invalid_token`, `missing_credentials`, `unreachable`, `number_not_on_account`, `error_<code>`.

## Webhooks (unauthenticated routes, per-tenant HMAC)

| Route | Purpose |
|---|---|
| `POST /api/v1/twilio/voice` | Inbound call → tenant greeting TwiML (+ Media Stream connect if enabled) |
| `POST /api/v1/twilio/status` | Call lifecycle callbacks |
| `POST /api/v1/twilio/recording` | Recording completion |
| `POST /api/v1/twilio/whatsapp/inbound` | Inbound WhatsApp → auto-reply TwiML |
| `POST /api/v1/twilio/whatsapp/status` | Delivery status |

Tenant resolved by called number (`To`). `X-Twilio-Signature` validated with that tenant's own auth token (skipped only when the tenant hasn't stored one yet). Every event logged to `webhook_logs`.

## Legacy note

360dialog sandbox (`MESSAGING_PROVIDER=360dialog`, platform-level env) still handles the existing WhatsApp conversation flow. Twilio per-tenant WhatsApp is additive; switching the conversation engine to route per-tenant via `tenant_integrations` is the next step.
