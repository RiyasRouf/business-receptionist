import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { api, type ApiError, type ApiSuccess } from '@/lib/api'
import { Shell } from '@/components/Shell'
import { PLATFORM_NAV } from '@/lib/nav'

const INDUSTRIES = ['Education', 'Real Estate', 'Healthcare', 'Hospitality', 'Other']
const COUNTRIES = ['UAE', 'Saudi Arabia', 'Qatar', 'Kuwait']
const GRADIENTS = ['g1grad', 'g2grad', 'g3grad', 'g4grad']

interface Tenant {
  tenant_id: string
  name: string | null
  slug: string
  industry: string | null
  country: string | null
  status: string
}

interface CreateTenantResult {
  tenant: Tenant
  admin: { name: string; email: string }
  temporary_password: string
}

async function fetchTenants(): Promise<Tenant[]> {
  const res = await api.get<ApiSuccess<Tenant[]>>('/admin/tenants')
  return res.data.data
}

function initials(name: string): string {
  return name.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase()
}

export function PlatformAdminTenants() {
  const { pathname } = useLocation()
  const queryClient = useQueryClient()
  const { data: tenants } = useQuery({ queryKey: ['admin', 'tenants'], queryFn: fetchTenants })

  const [form, setForm] = useState({
    name: '', slug: '', industry: INDUSTRIES[0], country: COUNTRIES[0],
    admin_name: '', admin_email: '', monthly_allowance_minutes: '500',
  })
  const [result, setResult] = useState<CreateTenantResult | null>(null)
  const [error, setError] = useState<string | null>(null)

  const createMutation = useMutation({
    mutationFn: async () => {
      const res = await api.post<ApiSuccess<CreateTenantResult>>('/admin/tenants', {
        ...form,
        monthly_allowance_minutes: Number(form.monthly_allowance_minutes) || undefined,
      })
      return res.data.data
    },
    onSuccess: (data) => {
      setResult(data)
      setError(null)
      setForm({ name: '', slug: '', industry: INDUSTRIES[0], country: COUNTRIES[0], admin_name: '', admin_email: '', monthly_allowance_minutes: '500' })
      queryClient.invalidateQueries({ queryKey: ['admin', 'tenants'] })
    },
    onError: (err) => {
      setError(isAxiosError<ApiError>(err) ? err.response?.data.message ?? 'Failed to create tenant.' : 'Failed to create tenant.')
    },
  })

  function field(key: keyof typeof form) {
    return {
      value: form[key],
      onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setForm((f) => ({ ...f, [key]: e.target.value })),
    }
  }

  function slugify(name: string) {
    return name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')
  }

  return (
    <Shell role="platform" logo="B" roleLabel="Platform Admin" navItems={PLATFORM_NAV} activePath={pathname}
      title="Tenants" subtitle={`${tenants?.length ?? 0} total`}>

      <div className="card" style={{ marginBottom: 20, border: '2px solid #C7D2FE', background: '#FAFBFF' }}>
        <div className="ct">Add New Tenant</div>
        <div className="cs">Creates the tenant and its first Business Admin in one step.</div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12 }}>
          <div className="fg" style={{ margin: 0 }}>
            <label className="fl">Business Name</label>
            <input placeholder="Al Noor International" value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value, slug: slugify(e.target.value) }))} />
          </div>
          <div className="fg" style={{ margin: 0 }}>
            <label className="fl">Industry</label>
            <select {...field('industry')}>{INDUSTRIES.map((i) => <option key={i}>{i}</option>)}</select>
          </div>
          <div className="fg" style={{ margin: 0 }}>
            <label className="fl">Country</label>
            <select {...field('country')}>{COUNTRIES.map((c) => <option key={c}>{c}</option>)}</select>
          </div>
          <div className="fg" style={{ margin: 0 }}>
            <label className="fl">Business Admin Name</label>
            <input placeholder="Sarah Ahmed" {...field('admin_name')} />
          </div>
          <div className="fg" style={{ margin: 0 }}>
            <label className="fl">Business Admin Email</label>
            <input placeholder="sarah@business.ae" type="email" {...field('admin_email')} />
          </div>
          <div className="fg" style={{ margin: 0 }}>
            <label className="fl">Monthly Allowance (min)</label>
            <input type="number" placeholder="500" {...field('monthly_allowance_minutes')} />
          </div>
        </div>
        {error && <div className="af-err" style={{ color: 'var(--err)', textAlign: 'left', marginTop: 8 }}>{error}</div>}
        {result && (
          <div className="info-box" style={{ marginTop: 12 }}>
            <span>✅</span>
            <span>
              Tenant created. Temporary password for <b>{result.admin.email}</b>: <code>{result.temporary_password}</code> — share this with them (no email transport configured yet).
            </span>
          </div>
        )}
        <div style={{ marginTop: 14, display: 'flex', gap: 8 }}>
          <button className="btn bp" disabled={createMutation.isPending} onClick={() => createMutation.mutate()}>
            {createMutation.isPending ? 'Creating…' : 'Create Tenant + Send Invite'}
          </button>
        </div>
      </div>

      <div className="g3">
        {tenants?.map((t, i) => (
          <div className="rc" key={t.tenant_id}>
            <div className={`ri ${GRADIENTS[i % GRADIENTS.length]}`}>{initials(t.name ?? t.slug)}</div>
            <div style={{ fontSize: 13, fontWeight: 700, marginBottom: 1 }}>{t.name ?? t.slug}</div>
            <div style={{ fontSize: 10.5, color: 'var(--t3)', marginBottom: 8 }}>{[t.industry, t.country].filter(Boolean).join(' · ') || '—'}</div>
            <div className={`bdg ${t.status === 'active' ? 'b-ok' : 'b-er'}`}>{t.status === 'active' ? 'Active' : 'Suspended'}</div>
          </div>
        ))}
      </div>
    </Shell>
  )
}
