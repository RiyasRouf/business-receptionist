import { useQuery } from '@tanstack/react-query'
import { useLocation, useNavigate } from 'react-router-dom'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell, type NavItem } from '@/components/Shell'

const NAV: NavItem[] = [
  { label: 'Dashboard', to: '/school' },
  { label: 'Team', to: '/school/team' },
  { label: 'Knowledge Base', to: '/school/kb' },
  { label: 'Leads', to: '/leads' },
  { label: 'Roles & Permissions', to: '/school/roles', section: 'Configuration' },
]

interface TenantStats {
  minutes_used: number
  leads_this_month: number
  followed_up: number
  team_members: number
  pipeline: Record<string, number>
}

const PIPELINE_LABELS: Record<string, string> = {
  partial: 'New', complete: 'In Progress', contacted: 'Followed Up', enrolled: 'Enrolled', closed: 'Closed',
}
const PIPELINE_COLORS: Record<string, string> = {
  partial: 'var(--pa)', complete: 'var(--warn)', contacted: 'var(--ok)', enrolled: 'var(--t3)', closed: 'var(--t3)',
}

async function fetchStats(): Promise<TenantStats> {
  return (await api.get<ApiSuccess<TenantStats>>('/dashboard')).data.data
}

export function BusinessAdminDashboard() {
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const { data: stats } = useQuery({ queryKey: ['dashboard'], queryFn: fetchStats })

  const pipelineEntries = Object.entries(stats?.pipeline ?? {})
  const pipelineMax = Math.max(1, ...pipelineEntries.map(([, v]) => v))

  return (
    <Shell role="business" logo="B" roleLabel="Business Admin" navItems={NAV} activePath={pathname}
      title="Overview" subtitle="This month"
      topbarActions={<button className="btn bp" onClick={() => navigate('/school/team')}>+ Team Member</button>}>

      <div className="sg">
        <div className="sc gr"><div className="si2 gr">📞</div><div className="sv">{stats?.minutes_used ?? '—'}</div><div className="sl">Minutes Used</div></div>
        <div className="sc pu"><div className="si2 pu">🎯</div><div className="sv">{stats?.leads_this_month ?? '—'}</div><div className="sl">Leads This Month</div></div>
        <div className="sc vi"><div className="si2 vi">✅</div><div className="sv">{stats?.followed_up ?? '—'}</div><div className="sl">Followed Up</div></div>
        <div className="sc cy"><div className="si2 cy">👥</div><div className="sv">{stats?.team_members ?? '—'}</div><div className="sl">Team Members</div></div>
      </div>

      <div className="card">
        <div className="ct">Lead Pipeline</div>
        <div className="cs">This month</div>
        {pipelineEntries.length === 0 && <div style={{ fontSize: 12, color: 'var(--t3)' }}>No leads yet this month.</div>}
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10, marginTop: 8 }}>
          {pipelineEntries.map(([status, count]) => (
            <div key={status} style={{ display: 'flex', alignItems: 'center', gap: 9 }}>
              <div style={{ width: 8, height: 8, borderRadius: '50%', background: PIPELINE_COLORS[status] ?? 'var(--t3)' }} />
              <div style={{ flex: 1, fontSize: 12 }}>{PIPELINE_LABELS[status] ?? status}</div>
              <div style={{ fontWeight: 700, fontSize: 12.5 }}>{count}</div>
              <div className="pb" style={{ width: 64, margin: 0 }}>
                <div className="pf" style={{ width: `${(count / pipelineMax) * 100}%`, background: PIPELINE_COLORS[status] ?? 'var(--t3)' }} />
              </div>
            </div>
          ))}
        </div>
      </div>
    </Shell>
  )
}
