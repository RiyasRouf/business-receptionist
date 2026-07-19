import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell } from '@/components/Shell'
import { PLATFORM_NAV } from '@/lib/nav'

interface TestResult { ok: boolean; status: string; latency_ms: number | null; data: unknown }

interface Integration {
  voice_provider: string | null; voice_account_sid: string | null; voice_phone_number: string | null
  voice_status: string; call_forwarding_type: string | null; business_hours: string | null; fallback_message: string | null
  whatsapp_number: string | null; whatsapp_display_name: string | null; whatsapp_phone_number_id: string | null
  whatsapp_status: string
}
interface IntegrationResponse { integration: Integration; voice_webhook_url: string; whatsapp_webhook_url: string }

async function fetchIntegration(tenantId: string): Promise<IntegrationResponse> {
  return (await api.get<ApiSuccess<IntegrationResponse>>(`/admin/tenants/${tenantId}/integrations`)).data.data
}

export function PlatformAdminVoiceTenant() {
  const navigate = useNavigate()
  const { tenantId } = useParams<{ tenantId: string }>()
  const queryClient = useQueryClient()
  const { data } = useQuery({ queryKey: ['admin', 'integrations', tenantId], queryFn: () => fetchIntegration(tenantId!), enabled: !!tenantId })

  const [provider, setProvider] = useState<'twilio' | 'vonage'>('twilio')
  const [voice, setVoice] = useState({ voice_account_sid: '', voice_auth_token: '', voice_phone_number: '', call_forwarding_type: 'Always Forward', business_hours: '', fallback_message: '' })
  const [wa, setWa] = useState({ whatsapp_number: '', whatsapp_display_name: '', whatsapp_phone_number_id: '', whatsapp_token: '', whatsapp_greeting: '' })

  const saveVoice = useMutation({
    mutationFn: () => {
      if (!window.confirm("Save this tenant's voice configuration?")) return Promise.reject('cancelled')
      return api.put(`/admin/tenants/${tenantId}/integrations/voice`, { voice_provider: provider, ...voice })
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'integrations'] }),
  })

  const saveWa = useMutation({
    mutationFn: () => {
      if (!window.confirm("Save this tenant's WhatsApp configuration?")) return Promise.reject('cancelled')
      return api.put(`/admin/tenants/${tenantId}/integrations/whatsapp`, wa)
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'integrations'] }),
  })

  const [testResult, setTestResult] = useState<TestResult | null>(null)
  const [testing, setTesting] = useState(false)

  async function runTest(path: string, body: Record<string, unknown> = {}) {
    setTesting(true); setTestResult(null)
    try {
      setTestResult((await api.post<ApiSuccess<TestResult>>(`/admin/tenants/${tenantId}${path}`, body)).data.data)
    } catch (e) {
      setTestResult({ ok: false, status: isAxiosError(e) ? (e.response?.data?.message ?? 'request_failed') : 'request_failed', latency_ms: null, data: null })
    }
    setTesting(false)
    queryClient.invalidateQueries({ queryKey: ['admin', 'integrations'] })
  }

  return (
    <Shell role="platform" logo="B" roleLabel="Platform Admin" navItems={PLATFORM_NAV} activePath="/admin/voice"
      title="Setup Assist" topbarActions={<div className="bdg b-wn">Credentials belong to this tenant only</div>}>
      <button className="btn bgh" style={{ padding: '5px 0', marginBottom: 14 }} onClick={() => navigate('/admin/voice')}>← Back</button>

      <div className="warn-box"><span>🔒</span><span>You are entering this tenant's own Twilio/Vonage credentials on their behalf. Stored isolated to this tenant only.</span></div>

      <div className="card" style={{ marginBottom: 20 }}>
        <div className="ct">Voice Provider — Tenant's Own Account</div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, marginBottom: 14 }}>
          <div className={`prov-card ${provider === 'twilio' ? 'active' : ''}`} onClick={() => setProvider('twilio')} style={{ cursor: 'pointer' }}>
            <div className="prov-icon">📞</div><div style={{ fontSize: 13, fontWeight: 700, marginBottom: 6 }}>Twilio</div>
            <div className="fg" style={{ marginBottom: 8 }}><label className="fl">Account SID</label><input placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" value={voice.voice_account_sid} onChange={(e) => setVoice((f) => ({ ...f, voice_account_sid: e.target.value }))} /></div>
            <div className="fg" style={{ marginBottom: 8 }}><label className="fl">Auth Token</label><input type="password" placeholder="Tenant's auth token" value={voice.voice_auth_token} onChange={(e) => setVoice((f) => ({ ...f, voice_auth_token: e.target.value }))} /></div>
            <div className="fg" style={{ margin: 0 }}><label className="fl">Phone Number</label><input placeholder="+971 4 123 4567" value={voice.voice_phone_number} onChange={(e) => setVoice((f) => ({ ...f, voice_phone_number: e.target.value }))} /></div>
          </div>
          <div className={`prov-card ${provider === 'vonage' ? 'active' : ''}`} onClick={() => setProvider('vonage')} style={{ cursor: 'pointer' }}>
            <div className="prov-icon">📱</div><div style={{ fontSize: 13, fontWeight: 700, marginBottom: 6 }}>Vonage</div>
            <div className="fg" style={{ marginBottom: 8 }}><label className="fl">API Key</label><input placeholder="Tenant's Vonage API key" value={voice.voice_account_sid} onChange={(e) => setVoice((f) => ({ ...f, voice_account_sid: e.target.value }))} /></div>
            <div className="fg" style={{ marginBottom: 8 }}><label className="fl">API Secret</label><input type="password" placeholder="Tenant's API secret" value={voice.voice_auth_token} onChange={(e) => setVoice((f) => ({ ...f, voice_auth_token: e.target.value }))} /></div>
            <div className="fg" style={{ margin: 0 }}><label className="fl">Virtual Number</label><input placeholder="+971 4 000 0000" value={voice.voice_phone_number} onChange={(e) => setVoice((f) => ({ ...f, voice_phone_number: e.target.value }))} /></div>
          </div>
        </div>
        <div className="fg"><label className="fl">Webhook URL — tenant must paste this in their Twilio/Vonage console</label><input value={data?.voice_webhook_url ?? ''} readOnly style={{ background: '#F9FAFB', color: 'var(--t3)', fontFamily: 'monospace', fontSize: 11 }} /></div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
          <div className="fg"><label className="fl">Call Forwarding Type</label>
            <select value={voice.call_forwarding_type} onChange={(e) => setVoice((f) => ({ ...f, call_forwarding_type: e.target.value }))}>
              <option>Always Forward</option><option>Forward When Busy</option><option>After Hours Only</option>
            </select>
          </div>
          <div className="fg"><label className="fl">Business Hours</label><input placeholder="Sun–Thu 08:00–17:00" value={voice.business_hours} onChange={(e) => setVoice((f) => ({ ...f, business_hours: e.target.value }))} /></div>
        </div>
        <div className="fg"><label className="fl">Fallback Message</label><textarea value={voice.fallback_message} onChange={(e) => setVoice((f) => ({ ...f, fallback_message: e.target.value }))} placeholder="Sorry, I wasn't able to help. Please call back during office hours." /></div>
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <button className="btn bp" disabled={saveVoice.isPending} onClick={() => saveVoice.mutate()}>{saveVoice.isPending ? 'Saving…' : 'Save Voice Config'}</button>
          <button className="btn bs" disabled={testing} onClick={() => runTest('/integrations/voice/verify')}>Verify Credentials</button>
          <button className="btn bs" disabled={testing} onClick={() => runTest('/integrations/voice/sync-numbers')}>Sync Numbers</button>
        </div>
      </div>

      <div className="card">
        <div className="ct">WhatsApp — Tenant's Own Account</div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
          <div>
            <div className="fg"><label className="fl">WhatsApp Business Number</label><input placeholder="+971 50 000 0000" value={wa.whatsapp_number} onChange={(e) => setWa((f) => ({ ...f, whatsapp_number: e.target.value }))} /></div>
            <div className="fg"><label className="fl">Display Name</label><input placeholder="Tenant's Business Name" value={wa.whatsapp_display_name} onChange={(e) => setWa((f) => ({ ...f, whatsapp_display_name: e.target.value }))} /></div>
            <div className="fg" style={{ margin: 0 }}><label className="fl">Phone Number ID (Meta)</label><input placeholder="Meta phone number ID" value={wa.whatsapp_phone_number_id} onChange={(e) => setWa((f) => ({ ...f, whatsapp_phone_number_id: e.target.value }))} /></div>
          </div>
          <div>
            <div className="fg"><label className="fl">WABA Token</label><input type="password" placeholder="Tenant's WhatsApp token" value={wa.whatsapp_token} onChange={(e) => setWa((f) => ({ ...f, whatsapp_token: e.target.value }))} /></div>
            <div className="fg"><label className="fl">Webhook URL — paste in Meta Developer Console</label><input value={data?.whatsapp_webhook_url ?? ''} readOnly style={{ background: '#F9FAFB', color: 'var(--t3)', fontFamily: 'monospace', fontSize: 11 }} /></div>
            <div className="fg" style={{ margin: 0 }}><label className="fl">Greeting Message</label><textarea value={wa.whatsapp_greeting} onChange={(e) => setWa((f) => ({ ...f, whatsapp_greeting: e.target.value }))} placeholder="Hello! Welcome. How can I help?" /></div>
          </div>
        </div>
        <div style={{ display: 'flex', gap: 8, marginTop: 14, alignItems: 'center', flexWrap: 'wrap' }}>
          <button className="btn bp" disabled={saveWa.isPending} onClick={() => saveWa.mutate()}>{saveWa.isPending ? 'Saving…' : 'Save WhatsApp Config'}</button>
          <button className="btn bs" disabled={testing} onClick={() => runTest('/integrations/whatsapp/verify')}>Test Connection</button>
          <div className="bdg b-wn">⚠️ Sandbox — Production needs Meta approval</div>
        </div>
      </div>

      {testing && <div className="info-box" style={{ marginTop: 14 }}><span>⏳</span><span>Testing against provider…</span></div>}
      {testResult && !testing && (
        <div className={testResult.ok ? 'info-box' : 'warn-box'} style={{ marginTop: 14 }}>
          <span>{testResult.ok ? '✅' : '⚠️'}</span>
          <span><b>{testResult.ok ? 'Connected' : testResult.status}</b>{testResult.latency_ms != null && <> · {testResult.latency_ms}ms</>}
            {testResult.data != null && <> · <code style={{ fontSize: 10 }}>{JSON.stringify(testResult.data).slice(0, 200)}</code></>}</span>
        </div>
      )}
    </Shell>
  )
}
