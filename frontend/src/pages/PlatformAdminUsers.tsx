import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { api, type ApiError, type ApiSuccess } from '@/lib/api'
import { Shell, type NavItem } from '@/components/Shell'

const NAV: NavItem[] = [
  { label: 'Dashboard', to: '/admin' },
  { label: 'Tenants', to: '/admin/tenants' },
  { label: 'Users', to: '/admin/users' },
  { label: 'AI Providers', to: '/admin/ai-providers', section: 'Configuration' },
]

interface Tenant { tenant_id: string; name: string | null; slug: string; industry: string | null }
interface AdminUser {
  user_id: string; name: string; email: string; job_title: string | null
  locked_until: string | null; created_at: string
  tenant: { tenant_id: string; name: string | null; industry: string | null } | null
}
interface CreateResult { user: AdminUser; temporary_password: string }

async function fetchTenants(): Promise<Tenant[]> {
  return (await api.get<ApiSuccess<Tenant[]>>('/admin/tenants')).data.data
}
async function fetchAdmins(): Promise<AdminUser[]> {
  return (await api.get<ApiSuccess<AdminUser[]>>('/admin/users')).data.data
}

export function PlatformAdminUsers() {
  const { pathname } = useLocation()
  const queryClient = useQueryClient()
  const { data: tenants } = useQuery({ queryKey: ['admin', 'tenants'], queryFn: fetchTenants })
  const { data: admins } = useQuery({ queryKey: ['admin', 'users'], queryFn: fetchAdmins })

  const [form, setForm] = useState({ name: '', email: '', tenant_id: '', job_title: '' })
  const [result, setResult] = useState<CreateResult | null>(null)
  const [error, setError] = useState<string | null>(null)

  const createMutation = useMutation({
    mutationFn: async () => (await api.post<ApiSuccess<CreateResult>>('/admin/users', form)).data.data,
    onSuccess: (data) => {
      setResult(data)
      setError(null)
      setForm({ name: '', email: '', tenant_id: '', job_title: '' })
      queryClient.invalidateQueries({ queryKey: ['admin', 'users'] })
    },
    onError: (err) => setError(isAxiosError<ApiError>(err) ? err.response?.data.message ?? 'Failed to create user.' : 'Failed to create user.'),
  })

  return (
    <Shell role="platform" logo="B" roleLabel="Platform Admin" navItems={NAV} activePath={pathname}
      title="Users" subtitle="Platform Admin creates Business Admins only">

      <div className="info-box"><span>ℹ️</span><span><b>Platform Admin</b> creates Business Admins only. Staff are created and managed by Business Admins within their own tenant.</span></div>

      <div className="card" style={{ marginBottom: 20, border: '1.5px solid #DDD6FE' }}>
        <div className="ct">Add Business Admin</div>
        <div className="cs">Full tenant access — leads, KB, voice, WhatsApp, team.</div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12 }}>
          <div className="fg" style={{ margin: 0 }}><label className="fl">Full Name</label><input placeholder="Sarah Ahmed" value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} /></div>
          <div className="fg" style={{ margin: 0 }}><label className="fl">Email</label><input type="email" placeholder="sarah@business.ae" value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} /></div>
          <div className="fg" style={{ margin: 0 }}>
            <label className="fl">Tenant</label>
            <select value={form.tenant_id} onChange={(e) => setForm((f) => ({ ...f, tenant_id: e.target.value }))}>
              <option value="">Select tenant…</option>
              {tenants?.map((t) => <option key={t.tenant_id} value={t.tenant_id}>{t.name ?? t.slug}</option>)}
            </select>
          </div>
          <div className="fg" style={{ margin: 0 }}><label className="fl">Job Title</label><input placeholder="Head of Admissions" value={form.job_title} onChange={(e) => setForm((f) => ({ ...f, job_title: e.target.value }))} /></div>
        </div>
        {error && <div style={{ color: 'var(--err)', fontSize: 12, marginTop: 8 }}>{error}</div>}
        {result && (
          <div className="info-box" style={{ marginTop: 12 }}>
            <span>✅</span><span>Temporary password for <b>{result.user.email}</b>: <code>{result.temporary_password}</code></span>
          </div>
        )}
        <div style={{ marginTop: 14 }}>
          <button className="btn bp" disabled={createMutation.isPending || !form.tenant_id} onClick={() => createMutation.mutate()}>
            {createMutation.isPending ? 'Creating…' : 'Create + Send Invite'}
          </button>
        </div>
      </div>

      <div className="card">
        <div className="sh"><div><div className="ct">All Business Admins</div><div className="cs">Across all tenants</div></div></div>
        <div className="tw"><table>
          <thead><tr><th>Name</th><th>Tenant</th><th>Industry</th><th>Status</th></tr></thead>
          <tbody>
            {admins?.map((a) => (
              <tr key={a.user_id}>
                <td><div style={{ fontWeight: 600 }}>{a.name}</div><div style={{ fontSize: 10.5, color: 'var(--t3)' }}>{a.email}</div></td>
                <td>{a.tenant?.name ?? '—'}</td>
                <td>{a.tenant?.industry ? <div className="bdg b-pu">{a.tenant.industry}</div> : '—'}</td>
                <td><div className="bdg b-ok">Active</div></td>
              </tr>
            ))}
          </tbody>
        </table></div>
      </div>
    </Shell>
  )
}
