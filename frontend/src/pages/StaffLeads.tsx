import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useLocation, useNavigate } from 'react-router-dom'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell, type NavItem } from '@/components/Shell'

const NAV: NavItem[] = [{ label: 'Leads', to: '/leads' }]

interface LeadSummary {
  lead_id: string
  session_id: string
  status: string
  parent_name: string | null
  child_name: string | null
  created_at: string
}

const STATUS_BADGE: Record<string, string> = {
  partial: 'b-in', complete: 'b-wn', contacted: 'b-ok', enrolled: 'b-pu', closed: 'b-gy',
}
const STATUS_LABEL: Record<string, string> = {
  partial: 'New', complete: 'In Progress', contacted: 'Followed Up', enrolled: 'Enrolled', closed: 'Closed',
}

async function fetchLeads(): Promise<LeadSummary[]> {
  return (await api.get<ApiSuccess<LeadSummary[]>>('/leads')).data.data
}

export function StaffLeads() {
  const { pathname } = useLocation()
  const navigate = useNavigate()
  const [statusFilter, setStatusFilter] = useState('')
  const { data: leads, isLoading } = useQuery({ queryKey: ['leads'], queryFn: fetchLeads })

  const filtered = statusFilter ? leads?.filter((l) => l.status === statusFilter) : leads

  const counts = {
    partial: leads?.filter((l) => l.status === 'partial').length ?? 0,
    complete: leads?.filter((l) => l.status === 'complete').length ?? 0,
    contacted: leads?.filter((l) => l.status === 'contacted').length ?? 0,
    enrolled: leads?.filter((l) => l.status === 'enrolled').length ?? 0,
  }

  return (
    <Shell role="business" logo="B" roleLabel="Team Member" navItems={NAV} activePath={pathname}
      title="Leads" subtitle={`${leads?.length ?? 0} total`}
      topbarActions={
        <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} style={{ width: 160 }}>
          <option value="">All statuses</option>
          <option value="partial">New</option>
          <option value="complete">In Progress</option>
          <option value="contacted">Followed Up</option>
          <option value="enrolled">Enrolled</option>
        </select>
      }>

      <div className="sg">
        <div className="sc vi"><div className="si2 vi">🆕</div><div className="sv">{counts.partial}</div><div className="sl">New</div></div>
        <div className="sc am"><div className="si2 am">⏳</div><div className="sv">{counts.complete}</div><div className="sl">In Progress</div></div>
        <div className="sc gr"><div className="si2 gr">✅</div><div className="sv">{counts.contacted}</div><div className="sl">Followed Up</div></div>
        <div className="sc pu"><div className="si2 pu">🎓</div><div className="sv">{counts.enrolled}</div><div className="sl">Enrolled</div></div>
      </div>

      <div className="card">
        {isLoading && <div style={{ fontSize: 12, color: 'var(--t3)' }}>Loading…</div>}
        {filtered?.length === 0 && <div style={{ fontSize: 12, color: 'var(--t3)' }}>No leads captured yet.</div>}
        {filtered && filtered.length > 0 && (
          <div className="tw"><table>
            <thead><tr><th>Parent</th><th>Child</th><th>Status</th><th>Captured</th><th /></tr></thead>
            <tbody>
              {filtered.map((lead) => (
                <tr key={lead.lead_id} style={lead.status === 'partial' ? { background: '#EFF6FF' } : undefined}>
                  <td style={{ fontWeight: 600 }}>{lead.parent_name ?? '—'}</td>
                  <td>{lead.child_name ?? '—'}</td>
                  <td><div className={`bdg ${STATUS_BADGE[lead.status] ?? 'b-gy'}`}>{STATUS_LABEL[lead.status] ?? lead.status}</div></td>
                  <td>{new Date(lead.created_at).toLocaleDateString()}</td>
                  <td><button className="btn bs bsm" onClick={() => navigate(`/leads/${lead.lead_id}`)}>Review</button></td>
                </tr>
              ))}
            </tbody>
          </table></div>
        )}
      </div>
    </Shell>
  )
}
