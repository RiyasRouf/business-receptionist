import { useQuery } from '@tanstack/react-query'
import { useLocation, useNavigate } from 'react-router-dom'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell, type NavItem } from '@/components/Shell'

const NAV: NavItem[] = [
  { label: 'Dashboard', to: '/admin' },
  { label: 'Tenants', to: '/admin/tenants' },
  { label: 'Users', to: '/admin/users' },
  { label: 'AI Providers', to: '/admin/ai-providers', section: 'Configuration' },
]

interface PlatformStats {
  active_tenants: number
  total_users: number
  calls_this_month: number
  ai_answer_rate: number | null
}

interface Tenant {
  tenant_id: string
  name: string | null
  slug: string
  industry: string | null
  status: string
  created_at: string
}

async function fetchStats(): Promise<PlatformStats> {
  const res = await api.get<ApiSuccess<PlatformStats>>('/admin/dashboard')
  return res.data.data
}

async function fetchTenants(): Promise<Tenant[]> {
  const res = await api.get<ApiSuccess<Tenant[]>>('/admin/tenants')
  return res.data.data
}

export function PlatformAdminDashboard() {
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const { data: stats } = useQuery({ queryKey: ['admin', 'dashboard'], queryFn: fetchStats })
  const { data: tenants } = useQuery({ queryKey: ['admin', 'tenants'], queryFn: fetchTenants })

  return (
    <Shell
      role="platform"
      logo="B"
      roleLabel="Platform Admin"
      navItems={NAV}
      activePath={pathname}
      title="Platform Overview"
      subtitle="All tenants · Live"
      topbarActions={
        <button className="btn bgr" onClick={() => navigate('/admin/tenants')}>+ New Tenant</button>
      }
    >
      <div className="sg">
        <div className="sc vi"><div className="si2 vi">🏢</div><div className="sv">{stats?.active_tenants ?? '—'}</div><div className="sl">Active Tenants</div></div>
        <div className="sc pu"><div className="si2 pu">👥</div><div className="sv">{stats?.total_users ?? '—'}</div><div className="sl">Total Users</div></div>
        <div className="sc cy"><div className="si2 cy">📞</div><div className="sv">{stats?.calls_this_month ?? '—'}</div><div className="sl">Calls This Month</div></div>
        <div className="sc gr"><div className="si2 gr">✅</div><div className="sv">{stats?.ai_answer_rate != null ? `${stats.ai_answer_rate}%` : '—'}</div><div className="sl">AI Answer Rate</div></div>
      </div>

      <div className="card">
        <div className="ct">Tenants</div>
        <div className="cs">Live status across the platform</div>
        {tenants?.length === 0 && <div style={{ fontSize: 12, color: 'var(--t3)' }}>No tenants yet.</div>}
        {tenants?.map((t) => (
          <div
            key={t.tenant_id}
            style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 0', borderBottom: '1px solid var(--bdr)' }}
          >
            <div className={`cs2 ${t.status === 'active' ? 'live' : 'off'}`} />
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: 12.5, fontWeight: 600 }}>{t.name ?? t.slug}</div>
              <div style={{ fontSize: 10.5, color: 'var(--t3)' }}>{t.industry ?? '—'}</div>
            </div>
            <div className={`bdg ${t.status === 'active' ? 'b-ok' : 'b-er'}`}>{t.status === 'active' ? 'Active' : 'Suspended'}</div>
          </div>
        ))}
      </div>
    </Shell>
  )
}
