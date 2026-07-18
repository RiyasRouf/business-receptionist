import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell } from '@/components/Shell'
import { LogoUploader } from '@/components/LogoUploader'
import { PLATFORM_NAV } from '@/lib/nav'

interface Brand { tenant_id: string; brand_name: string | null; brand_color: string | null; brand_tagline: string | null; logo_url: string | null }

async function fetchBrand(tenantId: string): Promise<Brand> {
  return (await api.get<ApiSuccess<Brand>>(`/admin/tenants/${tenantId}/branding`)).data.data
}

export function PlatformAdminTenantBrand() {
  const navigate = useNavigate()
  const { tenantId } = useParams<{ tenantId: string }>()
  const queryClient = useQueryClient()
  const { data } = useQuery({ queryKey: ['admin', 'branding', tenantId], queryFn: () => fetchBrand(tenantId!), enabled: !!tenantId })
  const [form, setForm] = useState({ brand_name: '', brand_color: '#10B981', brand_tagline: '' })

  useEffect(() => {
    if (data) setForm({ brand_name: data.brand_name ?? '', brand_color: data.brand_color ?? '#10B981', brand_tagline: data.brand_tagline ?? '' })
  }, [data])

  const save = useMutation({
    mutationFn: () => {
      if (!window.confirm("Save this tenant's branding? Updates their sidebar, logo colour, and login screen.")) return Promise.reject('cancelled')
      return api.put(`/admin/tenants/${tenantId}/branding`, form)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'branding'] })
      queryClient.invalidateQueries({ queryKey: ['admin', 'tenants'] })
    },
  })

  return (
    <Shell role="platform" logo="B" roleLabel="Platform Admin" navItems={PLATFORM_NAV} activePath="/admin/branding" title="Tenant Brand">
      <button className="btn bgh" style={{ padding: '5px 0', marginBottom: 14 }} onClick={() => navigate('/admin/branding')}>← Back</button>

      <div className="info-box"><span>ℹ️</span><span>Changes here update this tenant's sidebar name, logo colour, and login across their Business Admin and staff. Platform Admin branding is unaffected.</span></div>

      <div className="card">
        <div className="ct">Tenant Brand Settings</div>
        <div className="cs">Shown in tenant's sidebar, login, and emails</div>
        <LogoUploader logoUrl={data?.logo_url ?? null} uploadUrl={`/admin/tenants/${tenantId}/branding/logo`} deleteUrl={`/admin/tenants/${tenantId}/branding/logo`} queryKey={['admin', 'branding', tenantId]} />
        <div className="fg"><label className="fl">Display Name</label><input value={form.brand_name} onChange={(e) => setForm((f) => ({ ...f, brand_name: e.target.value }))} /></div>
        <div className="fg"><label className="fl">Primary Colour</label>
          <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            <input value={form.brand_color} onChange={(e) => setForm((f) => ({ ...f, brand_color: e.target.value }))} style={{ width: 120 }} />
            <div style={{ width: 32, height: 32, borderRadius: 'var(--r1)', background: form.brand_color, border: '1px solid var(--bdr)' }} />
          </div>
        </div>
        <div className="fg" style={{ margin: 0 }}><label className="fl">Login Tagline</label><input value={form.brand_tagline} onChange={(e) => setForm((f) => ({ ...f, brand_tagline: e.target.value }))} /></div>
        <div style={{ marginTop: 16 }}>
          <button className="btn bp" disabled={save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Save Tenant Brand'}</button>
        </div>
      </div>
    </Shell>
  )
}
