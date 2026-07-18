import { useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { api, type ApiError, type ApiSuccess } from '@/lib/api'
import { Shell, type NavItem } from '@/components/Shell'

const NAV: NavItem[] = [
  { label: 'Dashboard', to: '/business' },
  { label: 'Team', to: '/business/team', permission: 'team' },
  { label: 'Knowledge Base', to: '/business/kb', permission: 'knowledge_base' },
  { label: 'Leads', to: '/leads', permission: 'leads' },
  { label: 'Roles & Permissions', to: '/business/roles', section: 'Configuration', permission: 'team' },
]

interface TeamMember {
  user_id: string; name: string; email: string; role: string
  job_title: string | null; custom_role_id: string | null
  custom_role: { role_id: string; name: string } | null
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
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: team } = useQuery({ queryKey: ['team'], queryFn: fetchTeam })
  const { data: roles } = useQuery({ queryKey: ['roles', 'list'], queryFn: fetchRoles })

  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [jobTitle, setJobTitle] = useState('')
  const [customRoleId, setCustomRoleId] = useState('')
  const [result, setResult] = useState<CreateResult | null>(null)
  const [error, setError] = useState<string | null>(null)

  const createMutation = useMutation({
    mutationFn: async () => (await api.post<ApiSuccess<CreateResult>>('/team', {
      name,
      email,
      job_title: jobTitle || undefined,
      custom_role_id: customRoleId,
    })).data.data,
    onSuccess: (data) => {
      setResult(data)
      setError(null)
      setName(''); setEmail(''); setJobTitle(''); setCustomRoleId('')
      queryClient.invalidateQueries({ queryKey: ['team'] })
    },
    onError: (err) => setError(isAxiosError<ApiError>(err) ? err.response?.data.message ?? 'Failed to create team member.' : 'Failed to create team member.'),
  })

  return (
    <Shell role="business" logo="B" roleLabel="Business Admin" navItems={NAV} activePath={pathname}
      title="Team" subtitle={`${team?.length ?? 0} members`}>

      <div className="card" style={{ marginBottom: 20, border: '1.5px solid #A7F3D0' }}>
        <div className="ct">Add Team Member</div>
        <div className="cs">Every team member needs a custom role — only Platform Admin can grant unrestricted admin access.</div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12 }}>
          <div className="fg" style={{ margin: 0 }}><label className="fl">Full Name</label><input placeholder="Layla Khalil" value={name} onChange={(e) => setName(e.target.value)} /></div>
          <div className="fg" style={{ margin: 0 }}><label className="fl">Email</label><input type="email" placeholder="layla@business.ae" value={email} onChange={(e) => setEmail(e.target.value)} /></div>
          <div className="fg" style={{ margin: 0 }}>
            <label className="fl">Custom Role</label>
            <select value={customRoleId} onChange={(e) => setCustomRoleId(e.target.value)}>
              <option value="">Select role…</option>
              {roles?.map((r) => <option key={r.role_id} value={r.role_id}>{r.name}</option>)}
            </select>
          </div>
          <div className="fg" style={{ margin: 0 }}><label className="fl">Job Title</label><input placeholder="Admissions Officer" value={jobTitle} onChange={(e) => setJobTitle(e.target.value)} /></div>
        </div>
        {roles?.length === 0 && (
          <div className="warn-box">
            <span>⚠️</span>
            <span>No custom roles yet — <a href="#" onClick={(e) => { e.preventDefault(); navigate('/business/roles') }}>create one first</a> (e.g. "Staff") before adding a team member.</span>
          </div>
        )}
        {error && <div style={{ color: 'var(--err)', fontSize: 12, marginTop: 8 }}>{error}</div>}
        {result && (
          <div className="info-box" style={{ marginTop: 12 }}>
            <span>✅</span><span>Temporary password for <b>{result.user.email}</b>: <code>{result.temporary_password}</code></span>
          </div>
        )}
        <div style={{ marginTop: 14 }}>
          <button className="btn bp" disabled={createMutation.isPending || !name || !email || !customRoleId} onClick={() => createMutation.mutate()}>
            {createMutation.isPending ? 'Creating…' : 'Create + Send Invite'}
          </button>
        </div>
      </div>

      <div className="card">
        <div className="sh"><div><div className="ct">Team Members</div><div className="cs">Your tenant's team</div></div></div>
        <div className="tw"><table>
          <thead><tr><th>Name</th><th>Role</th><th>Job Title</th><th>Status</th></tr></thead>
          <tbody>
            {team?.map((m) => (
              <tr key={m.user_id}>
                <td><div style={{ fontWeight: 600 }}>{m.name}</div><div style={{ fontSize: 10.5, color: 'var(--t3)' }}>{m.email}</div></td>
                <td>
                  {/* role is tenant_admin for everyone now — custom_role_id
                      is what actually distinguishes the unrestricted admin
                      (null) from a permission-limited member (set). */}
                  {m.custom_role_id === null
                    ? <div className="bdg b-pu">Business Admin</div>
                    : (m.custom_role ? <div className="bdg b-cy">{m.custom_role.name}</div> : <div className="bdg b-gy">No role assigned</div>)}
                </td>
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
