import { useEffect, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell } from '@/components/Shell'
import { PLATFORM_NAV } from '@/lib/nav'

interface PlatformSetting { id: number; name: string; color: string; tagline: string }
interface TenantRow { tenant_id: string; name: string | null; brand_name: string | null; brand_color: string | null }

async function fetchPlatform(): Promise<PlatformSetting> {
  return (await api.get<ApiSuccess<PlatformSetting>>('/admin/branding')).data.data
}
async function fetchTenants(): Promise<TenantRow[]> {
  return (await api.get<ApiSuccess<TenantRow[]>>('/admin/tenants')).data.data
}

export function PlatformAdminBranding() {
  const { pathname } = useLocation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data } = useQuery({ queryKey: ['admin', 'branding'], queryFn: fetchPlatform })
  const { data: tenants } = useQuery({ queryKey: ['admin', 'tenants'], queryFn: fetchTenants })
  const [form, setForm] = useState({ name: '', color: '#6366F1', tagline: '' })

  useEffect(() => {
    if (data) setForm({ name: data.name, color: data.color, tagline: data.tagline })
  }, [data])

  const save = useMutation({
    mutationFn: () => {
      if (!window.confirm('Save platform branding? This updates the login page and all Platform Admin screens.')) return Promise.reject('cancelled')
      return api.put('/admin/branding', form)
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'branding'] }),
  })

  return (
    <Shell role="platform" logo="B" roleLabel="Platform Admin" navItems={PLATFORM_NAV} activePath={pathname}
      title="Branding" subtitle="Platform brand + assist with tenant branding">

      <div className="card" style={{ marginBottom: 20, border: '2px solid #C7D2FE' }}>
        <div className="ct">Platform Branding</div>
        <div className="cs">Global brand shown on the login page and Platform Admin screens. Tenants have their own separate branding.</div>
        <div className="fg"><label className="fl">Platform Name</label><input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} /></div>
        <div className="fg"><label className="fl">Primary Colour</label>
          <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            <input value={form.color} onChange={(e) => setForm((f) => ({ ...f, color: e.target.value }))} style={{ width: 120 }} />
            <div style={{ width: 32, height: 32, borderRadius: 'var(--r1)', background: form.color, border: '1px solid var(--bdr)' }} />
          </div>
        </div>
        <div className="fg" style={{ margin: 0 }}><label className="fl">Login Tagline</label><input value={form.tagline} onChange={(e) => setForm((f) => ({ ...f, tagline: e.target.value }))} /></div>
        <div style={{ marginTop: 16 }}>
          <button className="btn bp" disabled={save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Save Platform Brand'}</button>
        </div>
      </div>

      <div className="card">
        <div className="sh"><div><div className="ct">Tenant Branding Status</div><div className="cs">Each tenant has their own name and colour · click to assist</div></div></div>
        <div className="tw"><table>
          <thead><tr><th>Tenant</th><th>Custom Name</th><th>Colour</th><th>Status</th><th></th></tr></thead>
          <tbody>
            {tenants?.map((t) => (
              <tr key={t.tenant_id}>
                <td style={{ fontWeight: 600 }}>{t.name ?? '—'}</td>
                <td>{t.brand_name ?? '—'}</td>
                <td>{t.brand_color ? <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}><div style={{ width: 16, height: 16, borderRadius: 3, background: t.brand_color, border: '1px solid var(--bdr)' }} />{t.brand_color}</div> : <span style={{ color: 'var(--t3)', fontSize: 11.5 }}>Default</span>}</td>
                <td>{t.brand_name ? <div className="bdg b-ok">Configured</div> : <div className="bdg b-gy">Using Default</div>}</td>
                <td><button className={`btn bsm ${t.brand_name ? 'bs' : 'bp'}`} onClick={() => navigate(`/admin/branding/${t.tenant_id}`)}>{t.brand_name ? 'Edit Brand' : 'Set Brand'}</button></td>
              </tr>
            ))}
          </tbody>
        </table></div>
      </div>
    </Shell>
  )
}
