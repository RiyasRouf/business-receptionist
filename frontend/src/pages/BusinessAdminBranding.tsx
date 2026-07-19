import { useEffect, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell } from '@/components/Shell'
import { LogoUploader } from '@/components/LogoUploader'
import { BUSINESS_NAV } from '@/lib/nav'

interface Brand { tenant_id: string; brand_name: string | null; brand_color: string | null; brand_tagline: string | null; logo_url: string | null }

async function fetchBrand(): Promise<Brand> {
  return (await api.get<ApiSuccess<Brand>>('/branding')).data.data
}

const SWATCHES = ['#10B981', '#6366F1', '#F59E0B', '#EF4444']

export function BusinessAdminBranding() {
  const { pathname } = useLocation()
  const queryClient = useQueryClient()
  const { data } = useQuery({ queryKey: ['branding'], queryFn: fetchBrand })
  const [form, setForm] = useState({ brand_name: '', brand_color: '#10B981', brand_tagline: '' })

  useEffect(() => {
    if (data) setForm({ brand_name: data.brand_name ?? '', brand_color: data.brand_color ?? '#10B981', brand_tagline: data.brand_tagline ?? '' })
  }, [data])

  const save = useMutation({
    mutationFn: () => {
      if (!window.confirm('Save branding changes? This updates your team\'s sidebar and login screen immediately.')) return Promise.reject('cancelled')
      return api.put('/branding', form)
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['branding'] }),
  })

  return (
    <Shell role="business" logo="B" roleLabel="Business Admin" navItems={BUSINESS_NAV} activePath={pathname}
      title="Branding" subtitle="Your logo, name and colour shown in your team's sidebar and login">

      <div className="info-box"><span>ℹ️</span><span>Your branding is shown to your staff when they log in. It does not affect the Platform Admin view.</span></div>

      <div className="card">
        <div className="ct">Your Brand Settings</div>
        <div className="cs">Shown in sidebar, staff login screen and emails</div>
        <LogoUploader logoUrl={data?.logo_url ?? null} uploadUrl="/branding/logo" deleteUrl="/branding/logo" queryKey={['branding']} />
        <div className="fg"><label className="fl">Display Name</label><input value={form.brand_name} onChange={(e) => setForm((f) => ({ ...f, brand_name: e.target.value }))} placeholder="Your AI Receptionist" /></div>
        <div className="fg"><label className="fl">Primary Colour</label>
          <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            <input type="color" value={form.brand_color} onChange={(e) => setForm((f) => ({ ...f, brand_color: e.target.value }))} style={{ width: 40, height: 32, padding: 2 }} />
            <input value={form.brand_color} onChange={(e) => setForm((f) => ({ ...f, brand_color: e.target.value }))} style={{ width: 120 }} />
            {SWATCHES.map((c) => (
              <div key={c} onClick={() => setForm((f) => ({ ...f, brand_color: c }))}
                style={{ width: 32, height: 32, borderRadius: 'var(--r1)', background: c, border: form.brand_color === c ? '2px solid var(--t1)' : '1px solid var(--bdr)', cursor: 'pointer' }} />
            ))}
          </div>
        </div>
        <div className="fg" style={{ margin: 0 }}><label className="fl">Login Tagline</label><input value={form.brand_tagline} onChange={(e) => setForm((f) => ({ ...f, brand_tagline: e.target.value }))} placeholder="AI Receptionist for your business" /></div>
        <div style={{ marginTop: 16 }}>
          <button className="btn bp" disabled={save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Save Branding'}</button>
        </div>
      </div>
    </Shell>
  )
}
