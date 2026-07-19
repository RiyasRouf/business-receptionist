import { useEffect, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell } from '@/components/Shell'
import { confirmAction } from '@/components/confirm'
import { BUSINESS_NAV } from '@/lib/nav'

// Mirrors TwilioService::VOICES (source of truth) — this is what callers
// actually hear via <Say>.
const VOICES: Record<string, string> = {
  'Polly.Joanna': 'American English (female)',
  'Polly.Joanna-Neural': 'American English (female, neural)',
  'Polly.Matthew': 'American English (male)',
  'Polly.Matthew-Neural': 'American English (male, neural)',
  'Polly.Kendra-Neural': 'American English (female, neural, alt)',
  'Polly.Joey-Neural': 'American English (male, neural, alt)',
  'Polly.Salli': 'American English (female, alt)',
  'Polly.Justin': 'American English (male, child)',
  'Polly.Amy-Neural': 'British English (female, neural)',
  'Polly.Brian-Neural': 'British English (male, neural)',
  'Polly.Emma-Neural': 'British English (female, neural)',
  'Polly.Arthur-Neural': 'British English (male, neural, alt)',
  'Polly.Kajal-Neural': 'Indian English (female, neural)',
  'Polly.Aditi': 'Indian English (female)',
  'Polly.Olivia-Neural': 'Australian English (female, neural)',
  'Polly.Russell': 'Australian English (male)',
  'Polly.Niamh-Neural': 'Irish English (female, neural)',
  'Polly.Geraint': 'Welsh English (male)',
  'Polly.Ayanda-Neural': 'South African English (female, neural)',
  'Polly.Aria-Neural': 'New Zealand English (female, neural)',
}

// BCP-47 tag per accent so the browser's own TTS engine (Web Speech API)
// picks a locale-matched voice for an instant, free, no-call preview.
// Approximate accent only — the real call still uses the exact Polly
// voice above; this is just for auditioning without dialing anyone.
const VOICE_LANG: Record<string, string> = {
  'Polly.Amy-Neural': 'en-GB', 'Polly.Brian-Neural': 'en-GB', 'Polly.Emma-Neural': 'en-GB', 'Polly.Arthur-Neural': 'en-GB',
  'Polly.Kajal-Neural': 'en-IN', 'Polly.Aditi': 'en-IN',
  'Polly.Olivia-Neural': 'en-AU', 'Polly.Russell': 'en-AU',
  'Polly.Niamh-Neural': 'en-IE', 'Polly.Geraint': 'en-GB',
  'Polly.Ayanda-Neural': 'en-ZA', 'Polly.Aria-Neural': 'en-NZ',
}

function speakPreview(voiceId: string) {
  if (!('speechSynthesis' in window)) return
  const lang = VOICE_LANG[voiceId] ?? 'en-US'
  const utter = new SpeechSynthesisUtterance(`Hello! This is a preview of the ${VOICES[voiceId] ?? voiceId} voice for your AI receptionist.`)
  const voices = window.speechSynthesis.getVoices()
  utter.voice = voices.find((v) => v.lang === lang) ?? voices.find((v) => v.lang.startsWith(lang.slice(0, 2))) ?? null
  utter.lang = lang
  window.speechSynthesis.cancel()
  window.speechSynthesis.speak(utter)
}

interface Integration {
  voice_provider: string | null; voice_account_sid: string | null; voice_phone_number: string | null
  voice_api_key: string | null; voice_app_sid: string | null; voice_region: string | null
  voice_recording_enabled: boolean; voice_speech_timeout: number | null; voice_machine_detection: boolean; voice_tts_voice: string
  voice_media_streams_enabled: boolean; voice_stream_url: string | null
  voice_status: string; voice_last_tested_at: string | null; voice_latency_ms: number | null; voice_last_error: string | null
  voice_auth_token_set: boolean; voice_api_secret_set: boolean
  call_forwarding_type: string | null; business_hours: string | null; fallback_message: string | null
  whatsapp_provider: string; whatsapp_account_sid: string | null; whatsapp_messaging_service_sid: string | null
  whatsapp_number: string | null; whatsapp_display_name: string | null
  whatsapp_sandbox: boolean; whatsapp_media_enabled: boolean; whatsapp_interactive_enabled: boolean
  whatsapp_status: string; whatsapp_last_tested_at: string | null; whatsapp_latency_ms: number | null; whatsapp_last_error: string | null
  whatsapp_auth_token_set: boolean; whatsapp_api_secret_set: boolean
  whatsapp_greeting: string | null; whatsapp_status_callback_url: string | null
}
interface IntegrationResponse {
  integration: Integration
  voice_webhook_url: string; voice_status_callback_url: string; recording_callback_url: string
  whatsapp_webhook_url: string; whatsapp_status_callback_url: string
}
interface TestResult { ok: boolean; status: string; latency_ms: number | null; data: unknown }
interface TestLog { log_id: string; action: string; ok: boolean; latency_ms: number | null; detail_json: { status?: string; error?: string | null } | null; created_at: string }

async function fetchIntegration(): Promise<IntegrationResponse> {
  return (await api.get<ApiSuccess<IntegrationResponse>>('/integrations')).data.data
}

function statusBadge(status: string | undefined, lastError: string | null | undefined) {
  if (lastError) return <span className="bdg b-er">✗ {lastError.slice(0, 60)}</span>
  if (status === 'configured' || status === 'production') return <span className="bdg b-ok">✓ {status === 'production' ? 'Production Active' : 'Configured'}</span>
  if (status === 'sandbox') return <span className="bdg b-wn">Sandbox Active</span>
  return <span className="bdg b-gy">Not Configured</span>
}

function TestPanel({ result, pending }: { result: TestResult | null; pending: boolean }) {
  if (pending) return <div className="info-box" style={{ marginTop: 12, marginBottom: 0 }}><span>⏳</span><span>Testing against provider…</span></div>
  if (!result) return null
  return (
    <div className={result.ok ? 'info-box' : 'warn-box'} style={{ marginTop: 12, marginBottom: 0 }}>
      <span>{result.ok ? '✅' : '⚠️'}</span>
      <span>
        <b>{result.ok ? 'Connected' : ({ authentication_failed: 'Authentication Failed', invalid_sid: 'Invalid SID', invalid_token: 'Invalid Token', missing_credentials: 'Missing credentials — save SID + token first', unreachable: 'Provider unreachable', number_not_on_account: 'Phone number not found on this account' }[result.status] ?? result.status)}</b>
        {result.latency_ms != null && <> · {result.latency_ms}ms</>}
        {result.ok && result.data != null && <> · <code style={{ fontSize: 10 }}>{JSON.stringify(result.data).slice(0, 200)}</code></>}
      </span>
    </div>
  )
}

function LogsTable({ logs }: { logs: TestLog[] | undefined }) {
  if (!logs?.length) return null
  return (
    <div className="tw" style={{ marginTop: 14 }}><table>
      <thead><tr><th>Time</th><th>Action</th><th>Result</th><th>Latency</th><th>Detail</th></tr></thead>
      <tbody>{logs.slice(0, 8).map((l) => (
        <tr key={l.log_id}>
          <td>{new Date(l.created_at).toLocaleString()}</td><td>{l.action}</td>
          <td>{l.ok ? <span className="bdg b-ok">OK</span> : <span className="bdg b-er">Fail</span>}</td>
          <td>{l.latency_ms != null ? `${l.latency_ms}ms` : '—'}</td>
          <td style={{ fontSize: 11, color: 'var(--t2)' }}>{l.detail_json?.error ?? l.detail_json?.status ?? '—'}</td>
        </tr>
      ))}</tbody>
    </table></div>
  )
}

function useTest(url: string, onDone: () => void) {
  const [result, setResult] = useState<TestResult | null>(null)
  const m = useMutation({
    mutationFn: async (body?: Record<string, unknown>) => (await api.post<ApiSuccess<TestResult>>(url, body ?? {})).data.data,
    onSuccess: (r) => { setResult(r); onDone() },
    onError: (e) => {
      setResult({ ok: false, status: isAxiosError(e) ? (e.response?.data?.message ?? 'request_failed') : 'request_failed', latency_ms: null, data: null })
      onDone()
    },
  })
  return { result, m }
}

export function BusinessAdminVoice() {
  const { pathname } = useLocation()
  const queryClient = useQueryClient()
  const { data } = useQuery({ queryKey: ['integrations'], queryFn: fetchIntegration })
  const { data: voiceLogs } = useQuery({ queryKey: ['integrations', 'voice-logs'], queryFn: async () => (await api.get<ApiSuccess<TestLog[]>>('/integrations/voice/logs')).data.data })
  const { data: waLogs } = useQuery({ queryKey: ['integrations', 'wa-logs'], queryFn: async () => (await api.get<ApiSuccess<TestLog[]>>('/integrations/whatsapp/logs')).data.data })

  const [voice, setVoice] = useState({ voice_account_sid: '', voice_auth_token: '', voice_api_key: '', voice_api_secret: '', voice_app_sid: '', voice_region: 'us1', voice_phone_number: '', voice_recording_enabled: false, voice_speech_timeout: 5, voice_machine_detection: false, voice_media_streams_enabled: false, voice_stream_url: '', voice_tts_voice: 'Polly.Joanna', call_forwarding_type: 'Always Forward', business_hours: '', fallback_message: '' })
  const [wa, setWa] = useState({ whatsapp_account_sid: '', whatsapp_auth_token: '', whatsapp_api_key: '', whatsapp_api_secret: '', whatsapp_messaging_service_sid: '', whatsapp_number: '', whatsapp_display_name: '', whatsapp_sandbox: true, whatsapp_media_enabled: true, whatsapp_interactive_enabled: true, whatsapp_greeting: '', whatsapp_status_callback_url: '' })
  const [testCallTo, setTestCallTo] = useState('')
  const [testWaTo, setTestWaTo] = useState('')
  const [twiml, setTwiml] = useState<string | null>(null)

  const i = data?.integration
  useEffect(() => {
    if (!i) return
    setVoice((f) => ({ ...f, voice_account_sid: i.voice_account_sid ?? '', voice_api_key: i.voice_api_key ?? '', voice_app_sid: i.voice_app_sid ?? '', voice_region: i.voice_region ?? 'us1', voice_phone_number: i.voice_phone_number ?? '', voice_recording_enabled: i.voice_recording_enabled, voice_speech_timeout: i.voice_speech_timeout ?? 5, voice_machine_detection: i.voice_machine_detection, voice_media_streams_enabled: i.voice_media_streams_enabled, voice_stream_url: i.voice_stream_url ?? '', voice_tts_voice: i.voice_tts_voice ?? 'Polly.Joanna', call_forwarding_type: i.call_forwarding_type ?? 'Always Forward', business_hours: i.business_hours ?? '', fallback_message: i.fallback_message ?? '' }))
    setWa((f) => ({ ...f, whatsapp_account_sid: i.whatsapp_account_sid ?? '', whatsapp_messaging_service_sid: i.whatsapp_messaging_service_sid ?? '', whatsapp_number: i.whatsapp_number ?? '', whatsapp_display_name: i.whatsapp_display_name ?? '', whatsapp_sandbox: i.whatsapp_sandbox, whatsapp_media_enabled: i.whatsapp_media_enabled, whatsapp_interactive_enabled: i.whatsapp_interactive_enabled, whatsapp_greeting: i.whatsapp_greeting ?? '', whatsapp_status_callback_url: i.whatsapp_status_callback_url ?? '' }))
  }, [i])

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['integrations'] })
  }

  const saveVoice = useMutation({
    mutationFn: () => api.put('/integrations/voice', { voice_provider: 'twilio', ...voice }),
    onSuccess: refresh,
  })
  const saveWa = useMutation({
    mutationFn: () => api.put('/integrations/whatsapp', { whatsapp_provider: 'twilio', ...wa }),
    onSuccess: refresh,
  })

  const vVerify = useTest('/integrations/voice/verify', refresh)
  const vCall = useTest('/integrations/voice/test-call', refresh)
  const vSync = useTest('/integrations/voice/sync-numbers', refresh)
  const vWire = useTest('/integrations/voice/wire-webhook', refresh)
  const wVerify = useTest('/integrations/whatsapp/verify', refresh)
  const wSend = useTest('/integrations/whatsapp/test-send', refresh)

  const fetchTwiml = async () => setTwiml((await api.get<ApiSuccess<{ twiml: string }>>('/integrations/voice/twiml')).data.data.twiml)

  const roField = { background: '#F9FAFB', color: 'var(--t3)', fontFamily: 'monospace', fontSize: 11 }

  return (
    <Shell role="business" logo="B" roleLabel="Business Admin" navItems={BUSINESS_NAV} activePath={pathname}
      title="Voice & WhatsApp" subtitle="Your own Twilio account · live-tested against the provider">

      <div className="info-box"><span>ℹ️</span><span>You own your Twilio account and are billed directly. Credentials are encrypted at rest. Your Platform Admin can also assist with this setup.</span></div>

      {/* ── VOICE ── */}
      <div className="card" style={{ marginBottom: 20 }}>
        <div className="sh"><div>
          <div className="ct">Voice — Twilio</div>
          <div className="cs">Status: {statusBadge(i?.voice_status, i?.voice_last_error)}
            {i?.voice_last_tested_at && <span style={{ marginLeft: 8, fontSize: 11, color: 'var(--t3)' }}>Last tested {new Date(i.voice_last_tested_at).toLocaleString()}{i.voice_latency_ms != null && ` · ${i.voice_latency_ms}ms`}</span>}
          </div>
        </div></div>

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
          <div className="fg"><label className="fl">Account SID</label><input placeholder="ACxxxxxxxx" value={voice.voice_account_sid} onChange={(e) => setVoice((f) => ({ ...f, voice_account_sid: e.target.value }))} /></div>
          <div className="fg"><label className="fl">Auth Token {i?.voice_auth_token_set && <span className="bdg b-ok" style={{ marginLeft: 6 }}>set</span>}</label><input type="password" placeholder={i?.voice_auth_token_set ? '•••••••• (saved — leave blank to keep)' : 'Twilio auth token'} value={voice.voice_auth_token} onChange={(e) => setVoice((f) => ({ ...f, voice_auth_token: e.target.value }))} /></div>
          <div className="fg"><label className="fl">API Key (optional)</label><input placeholder="SKxxxxxxxx" value={voice.voice_api_key} onChange={(e) => setVoice((f) => ({ ...f, voice_api_key: e.target.value }))} /></div>
          <div className="fg"><label className="fl">API Secret {i?.voice_api_secret_set && <span className="bdg b-ok" style={{ marginLeft: 6 }}>set</span>}</label><input type="password" placeholder={i?.voice_api_secret_set ? '•••••••• (saved)' : 'API secret'} value={voice.voice_api_secret} onChange={(e) => setVoice((f) => ({ ...f, voice_api_secret: e.target.value }))} /></div>
          <div className="fg"><label className="fl">Application SID (optional)</label><input placeholder="APxxxxxxxx" value={voice.voice_app_sid} onChange={(e) => setVoice((f) => ({ ...f, voice_app_sid: e.target.value }))} /></div>
          <div className="fg"><label className="fl">Voice Region</label>
            <select value={voice.voice_region} onChange={(e) => setVoice((f) => ({ ...f, voice_region: e.target.value }))}>
              <option value="us1">US1 (default)</option><option value="ie1">IE1 (Ireland)</option><option value="au1">AU1 (Australia)</option>
            </select></div>
          <div className="fg"><label className="fl">Phone Number</label><input placeholder="+14155551234" value={voice.voice_phone_number} onChange={(e) => setVoice((f) => ({ ...f, voice_phone_number: e.target.value }))} /></div>
          <div className="fg"><label className="fl">Speech Timeout (s)</label><input type="number" min={1} max={60} value={voice.voice_speech_timeout} onChange={(e) => setVoice((f) => ({ ...f, voice_speech_timeout: Number(e.target.value) }))} /></div>
          <div className="fg"><label className="fl">AI Voice Accent</label>
            <div style={{ display: 'flex', gap: 8 }}>
              <select value={voice.voice_tts_voice} onChange={(e) => setVoice((f) => ({ ...f, voice_tts_voice: e.target.value }))} style={{ flex: 1 }}>
                {Object.entries(VOICES).map(([id, label]) => <option key={id} value={id}>{label}</option>)}
              </select>
              <button type="button" className="btn bs bsm" onClick={() => speakPreview(voice.voice_tts_voice)}>🔊 Test Voice</button>
            </div>
            <div className="cs" style={{ margin: '4px 0 0' }}>Instant in-browser preview — no call placed</div>
          </div>
        </div>

        <div style={{ display: 'flex', gap: 18, margin: '4px 0 14px', flexWrap: 'wrap' }}>
          {([['voice_recording_enabled', 'Call Recording'], ['voice_machine_detection', 'Machine Detection'], ['voice_media_streams_enabled', 'Media Streams (realtime audio)']] as const).map(([k, label]) => (
            <label key={k} style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12, cursor: 'pointer' }}>
              <div className={`toggle ${voice[k] ? 'on' : ''}`} onClick={() => setVoice((f) => ({ ...f, [k]: !f[k] }))} />{label}
            </label>
          ))}
        </div>
        {voice.voice_media_streams_enabled && (
          <div className="fg"><label className="fl">Stream URL (wss://)</label><input placeholder="wss://your-stream-endpoint" value={voice.voice_stream_url} onChange={(e) => setVoice((f) => ({ ...f, voice_stream_url: e.target.value }))} /></div>
        )}

        <div className="fg"><label className="fl">Voice Webhook URL — auto-wired via button below, or paste into Twilio Console</label><input value={data?.voice_webhook_url ?? ''} readOnly style={roField} /></div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
          <div className="fg"><label className="fl">Status Callback URL</label><input value={data?.voice_status_callback_url ?? ''} readOnly style={roField} /></div>
          <div className="fg"><label className="fl">Recording Callback URL</label><input value={data?.recording_callback_url ?? ''} readOnly style={roField} /></div>
        </div>
        <div className="fg"><label className="fl">Fallback / Greeting Message</label><textarea value={voice.fallback_message} onChange={(e) => setVoice((f) => ({ ...f, fallback_message: e.target.value }))} placeholder="Hello! Thanks for calling…" /></div>

        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
          <button className="btn bp" disabled={saveVoice.isPending} onClick={() => saveVoice.mutate()}>{saveVoice.isPending ? 'Saving…' : 'Save'}</button>
          <button className="btn bs" disabled={vVerify.m.isPending} onClick={() => vVerify.m.mutate(undefined)}>Verify Credentials</button>
          <button className="btn bs" disabled={vSync.m.isPending} onClick={() => vSync.m.mutate(undefined)}>Sync Numbers</button>
          <button className="btn bs" disabled={vWire.m.isPending} onClick={() => confirmAction({ title: 'Wire webhook?', message: 'Points your Twilio number webhook at this platform.', confirmText: 'Wire' }).then((ok) => ok && vWire.m.mutate(undefined))}>Wire Webhook</button>
          <button className="btn bs" onClick={fetchTwiml}>Generate TwiML</button>
          <input placeholder="+9715xxxxxxx" value={testCallTo} onChange={(e) => setTestCallTo(e.target.value)} style={{ width: 150 }} />
          <button className="btn bok bsm" disabled={vCall.m.isPending || !testCallTo} onClick={() => confirmAction({ title: 'Place test call?', message: `A real call will be placed to ${testCallTo}.`, confirmText: 'Call' }).then((ok) => ok && vCall.m.mutate({ to: testCallTo }))}>Create Test Call</button>
        </div>
        <TestPanel result={vVerify.result ?? vSync.result ?? vWire.result ?? vCall.result} pending={vVerify.m.isPending || vSync.m.isPending || vWire.m.isPending || vCall.m.isPending} />
        {twiml && <div className="fg" style={{ marginTop: 12 }}><label className="fl">Generated TwiML</label><textarea readOnly value={twiml} style={{ ...roField, minHeight: 80 }} /></div>}
        <LogsTable logs={voiceLogs} />
      </div>

      {/* ── WHATSAPP ── */}
      <div className="card">
        <div className="sh"><div>
          <div className="ct">WhatsApp — Twilio</div>
          <div className="cs">Status: {statusBadge(i?.whatsapp_status, i?.whatsapp_last_error)}
            {i?.whatsapp_last_tested_at && <span style={{ marginLeft: 8, fontSize: 11, color: 'var(--t3)' }}>Last tested {new Date(i.whatsapp_last_tested_at).toLocaleString()}{i.whatsapp_latency_ms != null && ` · ${i.whatsapp_latency_ms}ms`}</span>}
          </div>
        </div></div>

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
          <div className="fg"><label className="fl">Account SID</label><input placeholder="ACxxxxxxxx" value={wa.whatsapp_account_sid} onChange={(e) => setWa((f) => ({ ...f, whatsapp_account_sid: e.target.value }))} /></div>
          <div className="fg"><label className="fl">Auth Token {i?.whatsapp_auth_token_set && <span className="bdg b-ok" style={{ marginLeft: 6 }}>set</span>}</label><input type="password" placeholder={i?.whatsapp_auth_token_set ? '•••••••• (saved — leave blank to keep)' : 'Twilio auth token'} value={wa.whatsapp_auth_token} onChange={(e) => setWa((f) => ({ ...f, whatsapp_auth_token: e.target.value }))} /></div>
          <div className="fg"><label className="fl">API Key (optional)</label><input placeholder="SKxxxxxxxx" value={wa.whatsapp_api_key} onChange={(e) => setWa((f) => ({ ...f, whatsapp_api_key: e.target.value }))} /></div>
          <div className="fg"><label className="fl">API Secret {i?.whatsapp_api_secret_set && <span className="bdg b-ok" style={{ marginLeft: 6 }}>set</span>}</label><input type="password" placeholder={i?.whatsapp_api_secret_set ? '•••••••• (saved)' : 'API secret'} value={wa.whatsapp_api_secret} onChange={(e) => setWa((f) => ({ ...f, whatsapp_api_secret: e.target.value }))} /></div>
          <div className="fg"><label className="fl">Messaging Service SID (optional)</label><input placeholder="MGxxxxxxxx" value={wa.whatsapp_messaging_service_sid} onChange={(e) => setWa((f) => ({ ...f, whatsapp_messaging_service_sid: e.target.value }))} /></div>
          <div className="fg"><label className="fl">WhatsApp Number</label><input placeholder="+14155238886 (sandbox) or your number" value={wa.whatsapp_number} onChange={(e) => setWa((f) => ({ ...f, whatsapp_number: e.target.value }))} /></div>
          <div className="fg"><label className="fl">Business Name</label><input value={wa.whatsapp_display_name} onChange={(e) => setWa((f) => ({ ...f, whatsapp_display_name: e.target.value }))} /></div>
          <div className="fg"><label className="fl">Mode</label>
            <select value={wa.whatsapp_sandbox ? 'sandbox' : 'production'} onChange={(e) => setWa((f) => ({ ...f, whatsapp_sandbox: e.target.value === 'sandbox' }))}>
              <option value="sandbox">Sandbox</option><option value="production">Production</option>
            </select></div>
        </div>

        <div style={{ display: 'flex', gap: 18, margin: '4px 0 14px', flexWrap: 'wrap' }}>
          {([['whatsapp_media_enabled', 'Media Support'], ['whatsapp_interactive_enabled', 'Interactive Messages']] as const).map(([k, label]) => (
            <label key={k} style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12, cursor: 'pointer' }}>
              <div className={`toggle ${wa[k] ? 'on' : ''}`} onClick={() => setWa((f) => ({ ...f, [k]: !f[k] }))} />{label}
            </label>
          ))}
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
          <div className="fg"><label className="fl">Incoming Webhook — paste into Twilio WhatsApp sandbox/sender config</label><input value={data?.whatsapp_webhook_url ?? ''} readOnly style={roField} /></div>
          <div className="fg"><label className="fl">Status Callback</label><input value={data?.whatsapp_status_callback_url ?? ''} readOnly style={roField} /></div>
        </div>
        <div className="fg"><label className="fl">Greeting Message</label><textarea value={wa.whatsapp_greeting} onChange={(e) => setWa((f) => ({ ...f, whatsapp_greeting: e.target.value }))} placeholder="Hello! How can I help you today?" /></div>

        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
          <button className="btn bp" disabled={saveWa.isPending} onClick={() => saveWa.mutate()}>{saveWa.isPending ? 'Saving…' : 'Save'}</button>
          <button className="btn bs" disabled={wVerify.m.isPending} onClick={() => wVerify.m.mutate(undefined)}>Test Connection</button>
          <input placeholder="+9715xxxxxxx" value={testWaTo} onChange={(e) => setTestWaTo(e.target.value)} style={{ width: 150 }} />
          <button className="btn bok bsm" disabled={wSend.m.isPending || !testWaTo} onClick={() => confirmAction({ title: 'Send test message?', message: `A real WhatsApp message will be sent to ${testWaTo}.`, confirmText: 'Send' }).then((ok) => ok && wSend.m.mutate({ to: testWaTo }))}>Send Test WhatsApp</button>
        </div>
        <TestPanel result={wVerify.result ?? wSend.result} pending={wVerify.m.isPending || wSend.m.isPending} />
        <LogsTable logs={waLogs} />
      </div>
    </Shell>
  )
}
