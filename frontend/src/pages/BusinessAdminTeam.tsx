import { useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { api, type ApiError, type ApiSuccess } from '@/lib/api'
import { Shell, type NavItem } from '@/components/Shell'

const NAV: NavItem[] = [
  { label: 'Dashboard', to: '/business' },
  { label: 'Team', to: '/business/team' },
  { label: 'Knowledge Base', to: '/business/kb' },
  { label: 'Leads', to: '/leads' },
  { label: 'Roles & Permissions', to: '/business/roles', section: 'Configuration' },
]

const BUSINESS_ADMIN = 'tenant_admin'

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
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: team } = useQuery({ queryKey: ['team'], queryFn: fetchTeam })
  const { data: roles } = useQuery({ queryKey: ['roles', 'list'], queryFn: fetchRoles })

  // Only 2 system roles exist: Business Admin, or a custom role the
  // business_admin created (system role "staff" underneath). This
  // select's value is either BUSINESS_ADMIN or a tenant_role_id.
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [jobTitle, setJobTitle] = useState('')
  const [roleSelection, setRoleSelection] = useState('')
  const [result, setResult] = useState<CreateResult | null>(null)
  const [error, setError] = useState<string | null>(null)

  const isCustomRole = roleSelection !== '' && roleSelection !== BUSINESS_ADMIN

  const createMutation = useMutation({
    mutationFn: async () => (await api.post<ApiSuccess<CreateResult>>('/team', {
      name,
      email,
      job_title: jobTitle || undefined,
      role: roleSelection === BUSINESS_ADMIN ? 'tenant_admin' : 'staff',
      custom_role_id: isCustomRole ? roleSelection : undefined,
    })).data.data,
    onSuccess: (data) => {
      setResult(data)
      setError(null)
      setName(''); setEmail(''); setJobTitle(''); setRoleSelection('')
      queryClient.invalidateQueries({ queryKey: ['team'] })
    },
    onError: (err) => setError(isAxiosError<ApiError>(err) ? err.response?.data.message ?? 'Failed to create team member.' : 'Failed to create team member.'),
  })

  return (
    <Shell role="business" logo="B" roleLabel="Business Admin" navItems={NAV} activePath={pathname}
      title="Team" subtitle={`${team?.length ?? 0} members`}>

      <div className="card" style={{ marginBottom: 20, border: '1.5px solid #A7F3D0' }}>
        <div className="ct">Add Team Member</div>
        <div className="cs">Only 2 system roles exist — Business Admin, or a custom role you define below.</div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12 }}>
          <div className="fg" style={{ margin: 0 }}><label className="fl">Full Name</label><input placeholder="Layla Khalil" value={name} onChange={(e) => setName(e.target.value)} /></div>
          <div className="fg" style={{ margin: 0 }}><label className="fl">Email</label><input type="email" placeholder="layla@business.ae" value={email} onChange={(e) => setEmail(e.target.value)} /></div>
          <div className="fg" style={{ margin: 0 }}>
            <label className="fl">Role</label>
            <select value={roleSelection} onChange={(e) => setRoleSelection(e.target.value)}>
              <option value="">Select role…</option>
              <option value={BUSINESS_ADMIN}>Business Admin</option>
              {roles && roles.length > 0 && (
                <optgroup label="Custom Roles">
                  {roles.map((r) => <option key={r.role_id} value={r.role_id}>{r.name}</option>)}
                </optgroup>
              )}
            </select>
          </div>
          <div className="fg" style={{ margin: 0 }}><label className="fl">Job Title</label><input placeholder="Admissions Officer" value={jobTitle} onChange={(e) => setJobTitle(e.target.value)} /></div>
        </div>
        {roles?.length === 0 && (
          <div className="warn-box">
            <span>⚠️</span>
            <span>No custom roles yet — <a href="#" onClick={(e) => { e.preventDefault(); navigate('/business/roles') }}>create one first</a> (e.g. "Staff") before adding a non-admin team member.</span>
          </div>
        )}
        {error && <div style={{ color: 'var(--err)', fontSize: 12, marginTop: 8 }}>{error}</div>}
        {result && (
          <div className="info-box" style={{ marginTop: 12 }}>
            <span>✅</span><span>Temporary password for <b>{result.user.email}</b>: <code>{result.temporary_password}</code></span>
          </div>
        )}
        <div style={{ marginTop: 14 }}>
          <button className="btn bp" disabled={createMutation.isPending || !name || !email || !roleSelection} onClick={() => createMutation.mutate()}>
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
                  {m.role === 'tenant_admin'
                    ? <div className="bdg b-pu">Business Admin</div>
                    : (m.customRole ? <div className="bdg b-cy">{m.customRole.name}</div> : <div className="bdg b-gy">No role assigned</div>)}
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
