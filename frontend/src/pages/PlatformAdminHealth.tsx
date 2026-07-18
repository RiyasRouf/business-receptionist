import { useLocation } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell } from '@/components/Shell'
import { PLATFORM_NAV } from '@/lib/nav'

interface Health {
  status: string
  checks: { database: boolean; redis: boolean }
  latency_ms: { database: number | null; redis: number | null }
  stats: { active_tenants: number; sessions_today: number; ai_providers_active: number }
  checked_at: string
}

async function fetchHealth(): Promise<Health> {
  return (await api.get<ApiSuccess<Health>>('/admin/health')).data.data
}

export function PlatformAdminHealth() {
  const { pathname } = useLocation()
  const { data, dataUpdatedAt } = useQuery({ queryKey: ['admin', 'health'], queryFn: fetchHealth, refetchInterval: 30_000 })

  return (
    <Shell role="platform" logo="B" roleLabel="Platform Admin" navItems={PLATFORM_NAV} activePath={pathname}
      title="Health Monitor" subtitle="Live infrastructure status · auto-refreshes every 30s">

      <div className="sg">
        <div className={`sc ${data?.status === 'healthy' ? 'gr' : 're'}`}>
          <div className={`si2 ${data?.status === 'healthy' ? 'gr' : 're'}`}>{data?.status === 'healthy' ? '✅' : '⚠️'}</div>
          <div className="sv">{data?.status === 'healthy' ? 'Healthy' : data?.status === 'degraded' ? 'Degraded' : '…'}</div>
          <div className="sl">Overall Status</div>
        </div>
        <div className="sc vi"><div className="si2 vi">🏢</div><div className="sv">{data?.stats.active_tenants ?? '—'}</div><div className="sl">Active Tenants</div></div>
        <div className="sc pu"><div className="si2 pu">📞</div><div className="sv">{data?.stats.sessions_today ?? '—'}</div><div className="sl">Sessions Today</div></div>
        <div className="sc am"><div className="si2 am">🤖</div><div className="sv">{data?.stats.ai_providers_active ?? '—'}</div><div className="sl">Active AI Providers</div></div>
      </div>

      <div className="card">
        <div className="ct">Service Checks</div>
        <div className="cs">{dataUpdatedAt ? `Last checked ${new Date(dataUpdatedAt).toLocaleTimeString()}` : ''}</div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '10px 13px', background: 'var(--bg)', borderRadius: 'var(--r2)' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}><span className={`cs2 ${data?.checks.database ? 'live' : 'off'}`} />
              <div style={{ fontSize: 12.5, fontWeight: 600 }}>Database (PostgreSQL)</div>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
              {data?.latency_ms.database != null && <span style={{ fontSize: 11, color: 'var(--t3)' }}>{data.latency_ms.database}ms</span>}
              <div className={`bdg ${data?.checks.database ? 'b-ok' : 'b-er'}`}>{data?.checks.database ? 'Up' : 'Down'}</div>
            </div>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '10px 13px', background: 'var(--bg)', borderRadius: 'var(--r2)' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}><span className={`cs2 ${data?.checks.redis ? 'live' : 'off'}`} />
              <div style={{ fontSize: 12.5, fontWeight: 600 }}>Redis</div>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
              {data?.latency_ms.redis != null && <span style={{ fontSize: 11, color: 'var(--t3)' }}>{data.latency_ms.redis}ms</span>}
              <div className={`bdg ${data?.checks.redis ? 'b-ok' : 'b-er'}`}>{data?.checks.redis ? 'Up' : 'Down'}</div>
            </div>
          </div>
        </div>
      </div>
    </Shell>
  )
}
