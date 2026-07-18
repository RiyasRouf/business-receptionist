import { useLocation, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell } from '@/components/Shell'
import { PLATFORM_NAV } from '@/lib/nav'

interface Row {
  tenant_id: string; name: string | null; industry: string | null
  voice_provider: string | null; voice_phone_number: string | null; voice_status: string
  whatsapp_number: string | null; whatsapp_status: string
}

async function fetchRows(): Promise<Row[]> {
  return (await api.get<ApiSuccess<Row[]>>('/admin/integrations')).data.data
}

export function PlatformAdminVoice() {
  const { pathname } = useLocation()
  const navigate = useNavigate()
  const { data } = useQuery({ queryKey: ['admin', 'integrations'], queryFn: fetchRows })

  return (
    <Shell role="platform" logo="B" roleLabel="Platform Admin" navItems={PLATFORM_NAV} activePath={pathname}
      title="Voice & WhatsApp — Tenant Status" subtitle="Each tenant owns their own provider account · Platform Admin assists with setup">

      <div className="info-box"><span>🏗️</span><span><b>Each Tenant Owns Their Own Provider Account.</b> Twilio/Vonage bills them directly. Assist any tenant's setup below — credentials are isolated per tenant.</span></div>

      <div className="card">
        <div className="sh"><div><div className="ct">Tenant Voice & WhatsApp Setup Status</div><div className="cs">Click any tenant to assist with their setup</div></div></div>
        <div className="tw"><table>
          <thead><tr><th>Tenant</th><th>Voice Provider</th><th>Phone Number</th><th>Voice Status</th><th>WhatsApp</th><th>WA Status</th><th></th></tr></thead>
          <tbody>
            {data?.map((r) => (
              <tr key={r.tenant_id}>
                <td><div style={{ fontWeight: 600 }}>{r.name ?? '—'}</div><div style={{ fontSize: 10.5, color: 'var(--t3)' }}>{r.industry ?? ''}</div></td>
                <td>{r.voice_provider ? <div className="bdg b-in">{r.voice_provider}</div> : <div className="bdg b-gy">Not Set</div>}</td>
                <td style={{ fontFamily: 'monospace', fontSize: 11.5 }}>{r.voice_phone_number ?? '—'}</td>
                <td>{r.voice_status === 'configured' ? <div className="bdg b-ok">✓ Configured</div> : <div className="bdg b-er">✗ Not Configured</div>}</td>
                <td style={{ fontFamily: 'monospace', fontSize: 11.5 }}>{r.whatsapp_number ?? '—'}</td>
                <td>{r.whatsapp_status === 'sandbox' ? <div className="bdg b-wn">Sandbox</div> : r.whatsapp_status === 'production' ? <div className="bdg b-ok">Production</div> : <div className="bdg b-gy">Not Enabled</div>}</td>
                <td><button className={`btn bsm ${r.voice_status === 'configured' ? 'bs' : 'bp'}`} onClick={() => navigate(`/admin/voice/${r.tenant_id}`)}>{r.voice_status === 'configured' ? 'Assist Setup' : 'Set Up Now'}</button></td>
              </tr>
            ))}
          </tbody>
        </table></div>
      </div>
    </Shell>
  )
}
