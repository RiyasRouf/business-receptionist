import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell } from '@/components/Shell'
import { PLATFORM_NAV } from '@/lib/nav'

interface LogEntry {
  audit_id: string; action: string; resource_type: string | null; resource_id: string | null
  diff_json: Record<string, unknown> | null; created_at: string
  user: { name: string; email: string } | null
  tenant: { name: string | null } | null
}

async function fetchLogs(action: string): Promise<LogEntry[]> {
  const params: Record<string, string> = {}
  if (action) params.action = action
  return (await api.get<ApiSuccess<LogEntry[]>>('/admin/audit-logs', { params })).data.data
}

export function PlatformAdminAuditLogs() {
  const { pathname } = useLocation()
  const [actionFilter, setActionFilter] = useState('')
  const { data } = useQuery({ queryKey: ['admin', 'audit-logs', actionFilter], queryFn: () => fetchLogs(actionFilter) })

  return (
    <Shell role="platform" logo="B" roleLabel="Platform Admin" navItems={PLATFORM_NAV} activePath={pathname}
      title="Audit Logs" subtitle="Every create/update/delete action across the platform">

      <div className="card">
        <div className="sh">
          <div><div className="ct">Recent Activity</div><div className="cs">{data?.length ?? 0} entries</div></div>
          <input placeholder="Filter by action, e.g. role.created" value={actionFilter} onChange={(e) => setActionFilter(e.target.value)} style={{ width: 240 }} />
        </div>
        <div className="tw"><table>
          <thead><tr><th>When</th><th>Action</th><th>Resource</th><th>Actor</th><th>Tenant</th></tr></thead>
          <tbody>
            {data?.map((log) => (
              <tr key={log.audit_id}>
                <td style={{ fontSize: 11, color: 'var(--t3)' }}>{new Date(log.created_at).toLocaleString()}</td>
                <td><div className="bdg b-in">{log.action}</div></td>
                <td style={{ fontSize: 11.5 }}>{log.resource_type ?? '—'}</td>
                <td>{log.user ? <div><div style={{ fontWeight: 600, fontSize: 12 }}>{log.user.name}</div><div style={{ fontSize: 10.5, color: 'var(--t3)' }}>{log.user.email}</div></div> : <span style={{ color: 'var(--t3)' }}>System</span>}</td>
                <td>{log.tenant?.name ?? '—'}</td>
              </tr>
            ))}
            {data?.length === 0 && <tr><td colSpan={5} style={{ color: 'var(--t3)' }}>No activity recorded yet.</td></tr>}
          </tbody>
        </table></div>
      </div>
    </Shell>
  )
}
