import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { api, type ApiError, type ApiSuccess } from '@/lib/api'
import { Shell, type NavItem } from '@/components/Shell'

const NAV: NavItem[] = [
  { label: 'Dashboard', to: '/school' },
  { label: 'Team', to: '/school/team' },
  { label: 'Knowledge Base', to: '/school/kb' },
  { label: 'Leads', to: '/leads' },
  { label: 'Roles & Permissions', to: '/school/roles', section: 'Configuration' },
]

interface TeamMember {
  user_id: string; name: string; email: string; role: string
  job_title: string | null; custom_role_id: string | null
  customRole: { role_id: string; name: string } | null
  locked_until: string | null; created_at: string
}
interface CreateResult { user: TeamMember; temporary_password: string }
interface TenantRole { role_id: string; name: string }
interface RolesResponse { roles: TenantRole[] }

async function fetchTeam(): Promise<TeamMember[]> {
  return (await api.get<ApiSuccess<TeamMember[]>>('/team')).data.data
}
async function fetchRoles(): Promise<TenantRole[]> {
  return (await api.get<ApiSuccess<RolesResponse>>('/roles')).data.data.roles
}

export function BusinessAdminTeam() {
  const { pathname } = useLocation()
  const queryClient = useQueryClient()
  const { data: team } = useQuery({ queryKey: ['team'], queryFn: fetchTeam })
  const { data: roles } = useQuery({ queryKey: ['roles', 'list'], queryFn: fetchRoles })

  const [form, setForm] = useState({ name: '', email: '', role: 'staff', job_title: '', custom_role_id: '' })
  const [result, setResult] = useState<CreateResult | null>(null)
  const [error, setError] = useState<string | null>(null)

  const createMutation = useMutation({
    mutationFn: async () => (await api.post<ApiSuccess<CreateResult>>('/team', {
      ...form,
      custom_role_id: form.custom_role_id || undefined,
    })).data.data,
    onSuccess: (data) => {
      setResult(data)
      setError(null)
      setForm({ name: '', email: '', role: 'staff', job_title: '', custom_role_id: '' })
      queryClient.invalidateQueries({ queryKey: ['team'] })
    },
    onError: (err) => setError(isAxiosError<ApiError>(err) ? err.response?.data.message ?? 'Failed to create team member.' : 'Failed to create team member.'),
  })

  return (
    <Shell role="business" logo="B" roleLabel="Business Admin" navItems={NAV} activePath={pathname}
      title="Team" subtitle={`${team?.length ?? 0} members`}>

      <div className="card" style={{ marginBottom: 20, border: '1.5px solid #A7F3D0' }}>
        <div className="ct">Add Team Member</div>
        <div className="cs">Invite a new staff member or additional Business Admin.</div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12 }}>
          <div className="fg" style={{ margin: 0 }}><label className="fl">Full Name</label><input placeholder="Layla Khalil" value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} /></div>
          <div className="fg" style={{ margin: 0 }}><label className="fl">Email</label><input type="email" placeholder="layla@business.ae" value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} /></div>
          <div className="fg" style={{ margin: 0 }}>
            <label className="fl">System Role</label>
            <select value={form.role} onChange={(e) => setForm((f) => ({ ...f, role: e.target.value }))}>
              <option value="staff">Staff</option>
              <option value="tenant_admin">Business Admin</option>
            </select>
          </div>
          <div className="fg" style={{ margin: 0 }}><label className="fl">Job Title</label><input placeholder="Admissions Officer" value={form.job_title} onChange={(e) => setForm((f) => ({ ...f, job_title: e.target.value }))} /></div>
          <div className="fg" style={{ margin: 0 }}>
            <label className="fl">Custom Role</label>
            <select value={form.custom_role_id} onChange={(e) => setForm((f) => ({ ...f, custom_role_id: e.target.value }))}>
              <option value="">None</option>
              {roles?.map((r) => <option key={r.role_id} value={r.role_id}>{r.name}</option>)}
            </select>
          </div>
        </div>
        {error && <div style={{ color: 'var(--err)', fontSize: 12, marginTop: 8 }}>{error}</div>}
        {result && (
          <div className="info-box" style={{ marginTop: 12 }}>
            <span>✅</span><span>Temporary password for <b>{result.user.email}</b>: <code>{result.temporary_password}</code></span>
          </div>
        )}
        <div style={{ marginTop: 14 }}>
          <button className="btn bp" disabled={createMutation.isPending} onClick={() => createMutation.mutate()}>
            {createMutation.isPending ? 'Creating…' : 'Create + Send Invite'}
          </button>
        </div>
      </div>

      <div className="card">
        <div className="sh"><div><div className="ct">Staff Members</div><div className="cs">Your tenant's team</div></div></div>
        <div className="tw"><table>
          <thead><tr><th>Name</th><th>Role</th><th>Custom Role</th><th>Job Title</th><th>Status</th></tr></thead>
          <tbody>
            {team?.map((m) => (
              <tr key={m.user_id}>
                <td><div style={{ fontWeight: 600 }}>{m.name}</div><div style={{ fontSize: 10.5, color: 'var(--t3)' }}>{m.email}</div></td>
                <td><div className={`bdg ${m.role === 'tenant_admin' ? 'b-pu' : 'b-in'}`}>{m.role === 'tenant_admin' ? 'Business Admin' : 'Staff'}</div></td>
                <td>{m.customRole ? <div className="bdg b-cy">{m.customRole.name}</div> : '—'}</td>
                <td>{m.job_title ?? '—'}</td>
                <td><div className="bdg b-ok">Active</div></td>
              </tr>
            ))}
          </tbody>
        </table></div>
      </div>
    </Shell>
  )
}
