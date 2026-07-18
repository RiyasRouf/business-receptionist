import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell } from '@/components/Shell'
import { BUSINESS_NAV } from '@/lib/nav'

interface Integration {
  voice_provider: string | null; voice_account_sid: string | null; voice_phone_number: string | null
  voice_status: string; call_forwarding_type: string | null; business_hours: string | null; fallback_message: string | null
  whatsapp_number: string | null; whatsapp_display_name: string | null; whatsapp_phone_number_id: string | null
  whatsapp_greeting: string | null; whatsapp_status: string
}
interface IntegrationResponse { integration: Integration; voice_webhook_url: string; whatsapp_webhook_url: string }

async function fetchIntegration(): Promise<IntegrationResponse> {
  return (await api.get<ApiSuccess<IntegrationResponse>>('/integrations')).data.data
}

export function BusinessAdminVoice() {
  const { pathname } = useLocation()
  const queryClient = useQueryClient()
  const { data } = useQuery({ queryKey: ['integrations'], queryFn: fetchIntegration })

  const [provider, setProvider] = useState<'twilio' | 'vonage'>('twilio')
  const [voice, setVoice] = useState({ voice_account_sid: '', voice_auth_token: '', voice_phone_number: '', call_forwarding_type: 'Always Forward', business_hours: '', fallback_message: '' })
  const [wa, setWa] = useState({ whatsapp_number: '', whatsapp_display_name: '', whatsapp_phone_number_id: '', whatsapp_token: '', whatsapp_greeting: '' })

  const saveVoice = useMutation({
    mutationFn: () => {
      if (!window.confirm('Save voice configuration? This updates your live call routing setup.')) return Promise.reject('cancelled')
      return api.put('/integrations/voice', { voice_provider: provider, ...voice })
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['integrations'] }),
  })

  const saveWa = useMutation({
    mutationFn: () => {
      if (!window.confirm('Save WhatsApp configuration?')) return Promise.reject('cancelled')
      return api.put('/integrations/whatsapp', wa)
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['integrations'] }),
  })

  const i = data?.integration

  return (
    <Shell role="business" logo="B" roleLabel="Business Admin" navItems={BUSINESS_NAV} activePath={pathname}
      title="Voice & WhatsApp" subtitle="Your own provider account · billed directly to you by Twilio/Vonage">

      <div className="info-box"><span>ℹ️</span><span>You own your Twilio or Vonage account. The provider bills you directly. Enter your credentials below to connect. Your Platform Admin can also assist with this setup.</span></div>

      <div className="card" style={{ marginBottom: 20 }}>
        <div className="ct">Voice — Your Provider Credentials</div>
        <div className="cs">Current status: <span className={`bdg ${i?.voice_status === 'configured' ? 'b-ok' : 'b-er'}`}>{i?.voice_status === 'configured' ? '✓ Configured' : 'Not Configured'}</span></div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, marginBottom: 14 }}>
          <div className={`prov-card ${provider === 'twilio' ? 'active' : ''}`} onClick={() => setProvider('twilio')} style={{ cursor: 'pointer' }}>
            <div className="prov-icon">📞</div><div style={{ fontSize: 13, fontWeight: 700, marginBottom: 6 }}>Twilio</div>
            <div className="fg" style={{ marginBottom: 8 }}><label className="fl">Account SID</label><input placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" value={voice.voice_account_sid} onChange={(e) => setVoice((f) => ({ ...f, voice_account_sid: e.target.value }))} /></div>
            <div className="fg" style={{ marginBottom: 8 }}><label className="fl">Auth Token</label><input type="password" placeholder="Your Twilio auth token" value={voice.voice_auth_token} onChange={(e) => setVoice((f) => ({ ...f, voice_auth_token: e.target.value }))} /></div>
            <div className="fg" style={{ margin: 0 }}><label className="fl">Phone Number</label><input placeholder="+971 4 123 4567" value={voice.voice_phone_number} onChange={(e) => setVoice((f) => ({ ...f, voice_phone_number: e.target.value }))} /></div>
          </div>
          <div className={`prov-card ${provider === 'vonage' ? 'active' : ''}`} onClick={() => setProvider('vonage')} style={{ cursor: 'pointer' }}>
            <div className="prov-icon">📱</div><div style={{ fontSize: 13, fontWeight: 700, marginBottom: 6 }}>Vonage</div>
            <div className="fg" style={{ marginBottom: 8 }}><label className="fl">API Key</label><input placeholder="Your Vonage API key" value={voice.voice_account_sid} onChange={(e) => setVoice((f) => ({ ...f, voice_account_sid: e.target.value }))} /></div>
            <div className="fg" style={{ marginBottom: 8 }}><label className="fl">API Secret</label><input type="password" placeholder="Your API secret" value={voice.voice_auth_token} onChange={(e) => setVoice((f) => ({ ...f, voice_auth_token: e.target.value }))} /></div>
            <div className="fg" style={{ margin: 0 }}><label className="fl">Virtual Number</label><input placeholder="+971 4 000 0000" value={voice.voice_phone_number} onChange={(e) => setVoice((f) => ({ ...f, voice_phone_number: e.target.value }))} /></div>
          </div>
        </div>
        <div className="fg"><label className="fl">Webhook URL — copy into your Twilio/Vonage console under Voice → Webhook</label><input value={data?.voice_webhook_url ?? ''} readOnly style={{ background: '#F9FAFB', color: 'var(--t3)', fontFamily: 'monospace', fontSize: 11 }} /></div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
          <div className="fg"><label className="fl">Call Forwarding Type</label>
            <select value={voice.call_forwarding_type} onChange={(e) => setVoice((f) => ({ ...f, call_forwarding_type: e.target.value }))}>
              <option>Always Forward</option><option>Forward When Busy</option><option>Forward When No Answer</option><option>After Hours Only</option>
            </select>
          </div>
          <div className="fg"><label className="fl">Business Hours</label><input placeholder="Sun–Thu 08:00–17:00" value={voice.business_hours} onChange={(e) => setVoice((f) => ({ ...f, business_hours: e.target.value }))} /></div>
        </div>
        <div className="fg"><label className="fl">Fallback Message</label><textarea value={voice.fallback_message} onChange={(e) => setVoice((f) => ({ ...f, fallback_message: e.target.value }))} placeholder="Sorry, I wasn't able to help. Please call back during office hours." /></div>
        <button className="btn bp" disabled={saveVoice.isPending} onClick={() => saveVoice.mutate()}>{saveVoice.isPending ? 'Saving…' : 'Save Voice Config'}</button>
      </div>

      <div className="warn-box"><span>🔒</span><span>STT (OpenAI Whisper) and TTS (OpenAI TTS) are managed by Platform Admin at platform level. Cost is absorbed in your AMC.</span></div>

      <div className="card">
        <div className="ct">WhatsApp — Your Business Account</div>
        <div className="cs">Current status: <span className={`bdg ${wa.whatsapp_number || i?.whatsapp_status !== 'not_configured' ? 'b-wn' : 'b-gy'}`}>{i?.whatsapp_status === 'sandbox' ? 'Sandbox' : i?.whatsapp_status === 'production' ? 'Production' : 'Not Enabled'}</span></div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
          <div>
            <div className="fg"><label className="fl">Your WhatsApp Business Number</label><input placeholder="+971 50 000 0000" value={wa.whatsapp_number} onChange={(e) => setWa((f) => ({ ...f, whatsapp_number: e.target.value }))} /></div>
            <div className="fg"><label className="fl">Display Name</label><input placeholder="Your Business Name" value={wa.whatsapp_display_name} onChange={(e) => setWa((f) => ({ ...f, whatsapp_display_name: e.target.value }))} /></div>
            <div className="fg" style={{ margin: 0 }}><label className="fl">Phone Number ID (from Meta)</label><input placeholder="Meta phone number ID" value={wa.whatsapp_phone_number_id} onChange={(e) => setWa((f) => ({ ...f, whatsapp_phone_number_id: e.target.value }))} /></div>
          </div>
          <div>
            <div className="fg"><label className="fl">Your WABA Token</label><input type="password" placeholder="Your Meta WhatsApp token" value={wa.whatsapp_token} onChange={(e) => setWa((f) => ({ ...f, whatsapp_token: e.target.value }))} /></div>
            <div className="fg"><label className="fl">Webhook URL — paste into Meta Developer Console</label><input value={data?.whatsapp_webhook_url ?? ''} readOnly style={{ background: '#F9FAFB', color: 'var(--t3)', fontFamily: 'monospace', fontSize: 11 }} /></div>
            <div className="fg" style={{ margin: 0 }}><label className="fl">Greeting Message</label><textarea value={wa.whatsapp_greeting} onChange={(e) => setWa((f) => ({ ...f, whatsapp_greeting: e.target.value }))} placeholder="Hello! How can I help you today?" /></div>
          </div>
        </div>
        <div style={{ marginTop: 14 }}>
          <button className="btn bp" disabled={saveWa.isPending} onClick={() => saveWa.mutate()}>{saveWa.isPending ? 'Saving…' : 'Save WhatsApp Config'}</button>
        </div>
      </div>
    </Shell>
  )
}
