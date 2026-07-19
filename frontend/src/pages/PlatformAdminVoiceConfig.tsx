import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell } from '@/components/Shell'
import { PLATFORM_NAV } from '@/lib/nav'

interface VoiceProvider {
  provider_id: string; name: string; provider: string; api_key_set: boolean
  region: string | null; realtime_endpoint: string; rest_endpoint: string
  speech_model: string; language: string
  streaming_enabled: boolean; diarization: boolean; smart_formatting: boolean
  keywords: string | null; punctuation: boolean; profanity_filter: boolean; endpointing_ms: number | null
  tts_voice: string; tts_model: string; sample_rate: number; audio_encoding: string
  status: string; last_tested_at: string | null; latency_ms: number | null; last_error: string | null
}
interface TestResult { ok: boolean; status: string; latency_ms: number | null; data: unknown }
interface TestLog { log_id: string; action: string; ok: boolean; latency_ms: number | null; detail_json: { status?: string; error?: string | null } | null; created_at: string }

const EMPTY = { name: '', api_key: '', region: '', realtime_endpoint: 'wss://api.deepgram.com/v1/listen', rest_endpoint: 'https://api.deepgram.com', speech_model: 'nova-3', language: 'en', streaming_enabled: true, diarization: false, smart_formatting: true, keywords: '', punctuation: true, profanity_filter: false, endpointing_ms: 300, tts_voice: 'aura-2-thalia-en', tts_model: 'aura-2', sample_rate: 16000, audio_encoding: 'linear16' }

export function PlatformAdminVoiceConfig() {
  const { pathname } = useLocation()
  const queryClient = useQueryClient()
  const { data: providers } = useQuery({ queryKey: ['admin', 'voice-providers'], queryFn: async () => (await api.get<ApiSuccess<VoiceProvider[]>>('/admin/voice-providers')).data.data })
  const { data: logs } = useQuery({ queryKey: ['admin', 'voice-provider-logs'], queryFn: async () => (await api.get<ApiSuccess<TestLog[]>>('/admin/voice-providers-logs')).data.data })

  const [editing, setEditing] = useState<string | 'new' | null>(null)
  const [form, setForm] = useState(EMPTY)
  const [result, setResult] = useState<TestResult | null>(null)
  const [busy, setBusy] = useState<string | null>(null)

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['admin', 'voice-providers'] })
    queryClient.invalidateQueries({ queryKey: ['admin', 'voice-provider-logs'] })
  }

  const save = useMutation({
    mutationFn: () => {
      if (!window.confirm('Save voice provider configuration?')) return Promise.reject('cancelled')
      return editing === 'new' ? api.post('/admin/voice-providers', form) : api.put(`/admin/voice-providers/${editing}`, form)
    },
    onSuccess: () => { setEditing(null); refresh() },
  })

  const destroy = useMutation({
    mutationFn: (id: string) => {
      if (!window.confirm('Delete this voice provider?')) return Promise.reject('cancelled')
      return api.delete(`/admin/voice-providers/${id}`)
    },
    onSuccess: refresh,
  })

  async function runTest(id: string, action: string, method: 'post' | 'get' = 'post') {
    setBusy(`${id}:${action}`); setResult(null)
    try {
      const r = method === 'post'
        ? await api.post<ApiSuccess<TestResult>>(`/admin/voice-providers/${id}/${action}`)
        : await api.get<ApiSuccess<TestResult>>(`/admin/voice-providers/${id}/${action}`)
      setResult(r.data.data)
    } catch (e) {
      setResult({ ok: false, status: isAxiosError(e) ? (e.response?.data?.message ?? 'request_failed') : 'request_failed', latency_ms: null, data: null })
    }
    setBusy(null); refresh()
  }

  const startEdit = (p: VoiceProvider) => {
    setEditing(p.provider_id)
    setForm({ name: p.name, api_key: '', region: p.region ?? '', realtime_endpoint: p.realtime_endpoint, rest_endpoint: p.rest_endpoint, speech_model: p.speech_model, language: p.language, streaming_enabled: p.streaming_enabled, diarization: p.diarization, smart_formatting: p.smart_formatting, keywords: p.keywords ?? '', punctuation: p.punctuation, profanity_filter: p.profanity_filter, endpointing_ms: p.endpointing_ms ?? 300, tts_voice: p.tts_voice, tts_model: p.tts_model, sample_rate: p.sample_rate, audio_encoding: p.audio_encoding })
  }

  return (
    <Shell role="platform" logo="B" roleLabel="Platform Admin" navItems={PLATFORM_NAV} activePath={pathname}
      title="Voice Configuration" subtitle="Platform-owned speech providers (Deepgram STT/TTS) — separate from AI model providers"
      topbarActions={<button className="btn bp bsm" onClick={() => { setEditing('new'); setForm(EMPTY) }}>+ Add Voice Provider</button>}>

      <div className="info-box"><span>ℹ️</span><span>Speech-to-text and text-to-speech run at platform level and serve all tenants. API keys are encrypted at rest and never returned by the API. Every test button below performs a real Deepgram API call.</span></div>

      {providers?.map((p) => (
        <div className="card" style={{ marginBottom: 16 }} key={p.provider_id}>
          <div className="sh"><div>
            <div className="ct">{p.name} <span className="bdg b-pu" style={{ marginLeft: 6 }}>{p.provider}</span></div>
            <div className="cs">
              {p.last_error ? <span className="bdg b-er">✗ {p.last_error.slice(0, 60)}</span>
                : p.status === 'connected' ? <span className="bdg b-ok">✓ Connected</span>
                : <span className="bdg b-gy">Untested</span>}
              {p.last_tested_at && <span style={{ marginLeft: 8, fontSize: 11, color: 'var(--t3)' }}>Last tested {new Date(p.last_tested_at).toLocaleString()}{p.latency_ms != null && ` · ${p.latency_ms}ms`}</span>}
              {!p.api_key_set && <span className="bdg b-wn" style={{ marginLeft: 8 }}>No API key</span>}
            </div>
          </div>
          <div style={{ display: 'flex', gap: 6 }}>
            <button className="btn bs bsm" onClick={() => startEdit(p)}>Edit</button>
            <button className="btn ber bsm" disabled={destroy.isPending} onClick={() => destroy.mutate(p.provider_id)}>Delete</button>
          </div></div>

          <div className="g3" style={{ marginBottom: 8 }}>
            <div className="lf"><div className="ll">STT Model / Language</div><div className="lv">{p.speech_model} · {p.language}</div></div>
            <div className="lf"><div className="ll">TTS Voice</div><div className="lv">{p.tts_voice}</div></div>
            <div className="lf"><div className="ll">Audio</div><div className="lv">{p.audio_encoding} @ {p.sample_rate}Hz</div></div>
          </div>
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <button className="btn bs bsm" disabled={busy !== null} onClick={() => runTest(p.provider_id, 'verify')}>{busy === `${p.provider_id}:verify` ? 'Testing…' : 'Verify API'}</button>
            <button className="btn bs bsm" disabled={busy !== null} onClick={() => runTest(p.provider_id, 'models', 'get')}>{busy === `${p.provider_id}:models` ? 'Loading…' : 'List Models'}</button>
            <button className="btn bs bsm" disabled={busy !== null} onClick={() => runTest(p.provider_id, 'stt-test')}>{busy === `${p.provider_id}:stt-test` ? 'Transcribing…' : 'STT Test'}</button>
            <button className="btn bs bsm" disabled={busy !== null} onClick={() => runTest(p.provider_id, 'tts-test')}>{busy === `${p.provider_id}:tts-test` ? 'Synthesizing…' : 'TTS Test'}</button>
            <button className="btn bs bsm" disabled={busy !== null} onClick={() => runTest(p.provider_id, 'latency-test')}>{busy === `${p.provider_id}:latency-test` ? 'Measuring…' : 'Latency Test'}</button>
          </div>
        </div>
      ))}

      {result && (
        <div className={result.ok ? 'info-box' : 'warn-box'}>
          <span>{result.ok ? '✅' : '⚠️'}</span>
          <span><b>{result.ok ? 'Connected' : result.status}</b>{result.latency_ms != null && <> · {result.latency_ms}ms</>}
            {result.data != null && <> · <code style={{ fontSize: 10, wordBreak: 'break-all' }}>{JSON.stringify(result.data).slice(0, 400)}</code></>}</span>
        </div>
      )}

      {editing && (
        <div className="modal-overlay" onClick={() => setEditing(null)}>
          <div className="modal-card" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 620 }}>
            <div className="ct" style={{ marginBottom: 12 }}>{editing === 'new' ? 'Add Voice Provider' : 'Edit Voice Provider'}</div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <div className="fg"><label className="fl">Name</label><input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} placeholder="Deepgram Production" /></div>
              <div className="fg"><label className="fl">API Key</label><input type="password" value={form.api_key} onChange={(e) => setForm((f) => ({ ...f, api_key: e.target.value }))} placeholder={editing !== 'new' ? '•••• (leave blank to keep)' : 'Deepgram API key'} /></div>
              <div className="fg"><label className="fl">Region</label><input value={form.region} onChange={(e) => setForm((f) => ({ ...f, region: e.target.value }))} placeholder="global" /></div>
              <div className="fg"><label className="fl">Speech Model</label><input value={form.speech_model} onChange={(e) => setForm((f) => ({ ...f, speech_model: e.target.value }))} /></div>
              <div className="fg"><label className="fl">Language</label><input value={form.language} onChange={(e) => setForm((f) => ({ ...f, language: e.target.value }))} /></div>
              <div className="fg"><label className="fl">Endpointing (ms)</label><input type="number" value={form.endpointing_ms} onChange={(e) => setForm((f) => ({ ...f, endpointing_ms: Number(e.target.value) }))} /></div>
              <div className="fg"><label className="fl">REST Endpoint</label><input value={form.rest_endpoint} onChange={(e) => setForm((f) => ({ ...f, rest_endpoint: e.target.value }))} /></div>
              <div className="fg"><label className="fl">Realtime Endpoint</label><input value={form.realtime_endpoint} onChange={(e) => setForm((f) => ({ ...f, realtime_endpoint: e.target.value }))} /></div>
              <div className="fg"><label className="fl">TTS Voice</label><input value={form.tts_voice} onChange={(e) => setForm((f) => ({ ...f, tts_voice: e.target.value }))} /></div>
              <div className="fg"><label className="fl">TTS Model</label><input value={form.tts_model} onChange={(e) => setForm((f) => ({ ...f, tts_model: e.target.value }))} /></div>
              <div className="fg"><label className="fl">Sample Rate</label>
                <select value={form.sample_rate} onChange={(e) => setForm((f) => ({ ...f, sample_rate: Number(e.target.value) }))}>
                  {[8000, 16000, 24000, 48000].map((r) => <option key={r} value={r}>{r} Hz</option>)}
                </select></div>
              <div className="fg"><label className="fl">Audio Encoding</label>
                <select value={form.audio_encoding} onChange={(e) => setForm((f) => ({ ...f, audio_encoding: e.target.value }))}>
                  {['linear16', 'mulaw', 'alaw', 'mp3', 'opus', 'flac', 'aac'].map((enc) => <option key={enc} value={enc}>{enc}</option>)}
                </select></div>
            </div>
            <div className="fg"><label className="fl">Keywords (comma-separated boost terms)</label><input value={form.keywords} onChange={(e) => setForm((f) => ({ ...f, keywords: e.target.value }))} /></div>
            <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap', margin: '4px 0 16px' }}>
              {([['streaming_enabled', 'Streaming'], ['diarization', 'Diarization'], ['smart_formatting', 'Smart Formatting'], ['punctuation', 'Punctuation'], ['profanity_filter', 'Profanity Filter']] as const).map(([k, label]) => (
                <label key={k} style={{ display: 'flex', alignItems: 'center', gap: 7, fontSize: 12, cursor: 'pointer' }}>
                  <div className={`toggle ${form[k] ? 'on' : ''}`} onClick={() => setForm((f) => ({ ...f, [k]: !f[k] }))} />{label}
                </label>
              ))}
            </div>
            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
              <button className="btn bs" onClick={() => setEditing(null)}>Cancel</button>
              <button className="btn bp" disabled={save.isPending || !form.name} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Save'}</button>
            </div>
          </div>
        </div>
      )}

      {logs && logs.length > 0 && (
        <div className="card">
          <div className="ct">Connection History</div>
          <div className="cs">Latest live test results against Deepgram</div>
          <div className="tw"><table>
            <thead><tr><th>Time</th><th>Action</th><th>Result</th><th>Latency</th><th>Detail</th></tr></thead>
            <tbody>{logs.slice(0, 12).map((l) => (
              <tr key={l.log_id}>
                <td>{new Date(l.created_at).toLocaleString()}</td><td>{l.action}</td>
                <td>{l.ok ? <span className="bdg b-ok">OK</span> : <span className="bdg b-er">Fail</span>}</td>
                <td>{l.latency_ms != null ? `${l.latency_ms}ms` : '—'}</td>
                <td style={{ fontSize: 11, color: 'var(--t2)' }}>{l.detail_json?.error ?? l.detail_json?.status ?? '—'}</td>
              </tr>
            ))}</tbody>
          </table></div>
        </div>
      )}
    </Shell>
  )
}
