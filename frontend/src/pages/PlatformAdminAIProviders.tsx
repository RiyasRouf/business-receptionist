import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell, type NavItem } from '@/components/Shell'

const NAV: NavItem[] = [
  { label: 'Dashboard', to: '/admin' },
  { label: 'Tenants', to: '/admin/tenants' },
  { label: 'Users', to: '/admin/users' },
  { label: 'AI Providers', to: '/admin/ai-providers', section: 'Configuration' },
]

interface Model { model_id: string; name: string; input_cost_per_1m: number; output_cost_per_1m: number }
interface Provider { provider_id: string; name: string; status: string; models: Model[] }
interface Cost { tenant_id: string; tenant_name: string; model: string | null; provider: string | null; tokens: number; turns: number; cost_usd: number }

async function fetchProviders(): Promise<Provider[]> {
  return (await api.get<ApiSuccess<Provider[]>>('/admin/ai-providers')).data.data
}
async function fetchCosts(): Promise<Cost[]> {
  return (await api.get<ApiSuccess<Cost[]>>('/admin/usage-cost')).data.data
}

export function PlatformAdminAIProviders() {
  const { pathname } = useLocation()
  const queryClient = useQueryClient()
  const { data: providers } = useQuery({ queryKey: ['admin', 'ai-providers'], queryFn: fetchProviders })
  const { data: costs } = useQuery({ queryKey: ['admin', 'usage-cost'], queryFn: fetchCosts })

  const [providerForm, setProviderForm] = useState({ name: '', api_key: '', base_url: '' })
  const [modelForms, setModelForms] = useState<Record<string, { name: string; input_cost_per_1m: string; output_cost_per_1m: string }>>({})

  const createProvider = useMutation({
    mutationFn: async () => (await api.post('/admin/ai-providers', providerForm)).data,
    onSuccess: () => {
      setProviderForm({ name: '', api_key: '', base_url: '' })
      queryClient.invalidateQueries({ queryKey: ['admin', 'ai-providers'] })
    },
  })

  const addModel = useMutation({
    mutationFn: async (providerId: string) => {
      const f = modelForms[providerId]
      return api.post(`/admin/ai-providers/${providerId}/models`, {
        name: f.name,
        input_cost_per_1m: Number(f.input_cost_per_1m) || 0,
        output_cost_per_1m: Number(f.output_cost_per_1m) || 0,
      })
    },
    onSuccess: (_data, providerId) => {
      setModelForms((f) => ({ ...f, [providerId]: { name: '', input_cost_per_1m: '', output_cost_per_1m: '' } }))
      queryClient.invalidateQueries({ queryKey: ['admin', 'ai-providers'] })
    },
  })

  function modelField(providerId: string) {
    return modelForms[providerId] ?? { name: '', input_cost_per_1m: '', output_cost_per_1m: '' }
  }

  const totalCost = costs?.reduce((sum, c) => sum + c.cost_usd, 0) ?? 0

  return (
    <Shell role="platform" logo="B" roleLabel="Platform Admin" navItems={NAV} activePath={pathname}
      title="AI Providers" subtitle="Add providers · assign per tenant · cost tracked per provider">

      <div className="sg">
        <div className="sc am"><div className="si2 am">🤖</div><div className="sv">${totalCost.toFixed(2)}</div><div className="sl">Total AI Cost · This Month</div></div>
        <div className="sc vi"><div className="si2 vi">🔌</div><div className="sv">{providers?.length ?? 0}</div><div className="sl">Providers Configured</div></div>
      </div>

      <div className="sh" style={{ marginBottom: 14 }}><div className="sht">Active AI Providers</div></div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12, marginBottom: 24 }}>
        {providers?.map((p) => (
          <div className="ai-card active" key={p.provider_id}>
            <div className="ai-badge"><div className="bdg b-ok">Active</div></div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 14 }}>
              <div style={{ width: 40, height: 40, borderRadius: 'var(--r2)', background: 'var(--bg)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 18 }}>🤖</div>
              <div><div style={{ fontSize: 14, fontWeight: 700 }}>{p.name}</div><div style={{ fontSize: 11, color: 'var(--t3)' }}>{p.models.length} model(s) configured</div></div>
            </div>
            <div style={{ background: 'var(--bg)', borderRadius: 'var(--r2)', padding: 12, marginBottom: 12 }}>
              <div style={{ fontSize: 11, fontWeight: 600, color: 'var(--t2)', textTransform: 'uppercase', letterSpacing: '.04em', marginBottom: 8 }}>Models</div>
              <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 10 }}>
                {p.models.map((m) => (
                  <div className="bdg b-in" key={m.model_id}>{m.name} · ${m.input_cost_per_1m}/1M in · ${m.output_cost_per_1m}/1M out</div>
                ))}
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr auto', gap: 8, alignItems: 'end' }}>
                <div className="fg" style={{ margin: 0 }}><label className="fl">Model Name</label><input placeholder="gpt-4o-mini" value={modelField(p.provider_id).name} onChange={(e) => setModelForms((f) => ({ ...f, [p.provider_id]: { ...modelField(p.provider_id), name: e.target.value } }))} /></div>
                <div className="fg" style={{ margin: 0 }}><label className="fl">Input $/1M</label><input placeholder="0.15" value={modelField(p.provider_id).input_cost_per_1m} onChange={(e) => setModelForms((f) => ({ ...f, [p.provider_id]: { ...modelField(p.provider_id), input_cost_per_1m: e.target.value } }))} /></div>
                <div className="fg" style={{ margin: 0 }}><label className="fl">Output $/1M</label><input placeholder="0.60" value={modelField(p.provider_id).output_cost_per_1m} onChange={(e) => setModelForms((f) => ({ ...f, [p.provider_id]: { ...modelField(p.provider_id), output_cost_per_1m: e.target.value } }))} /></div>
                <button className="btn bs bsm" disabled={addModel.isPending} onClick={() => addModel.mutate(p.provider_id)}>+ Add Model</button>
              </div>
            </div>
          </div>
        ))}
      </div>

      <div className="card" style={{ border: '2px dashed var(--bdr)', marginBottom: 24 }}>
        <div className="ct">Add New AI Provider</div>
        <div className="cs">Any OpenAI-compatible provider · custom endpoint supported</div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12 }}>
          <div className="fg" style={{ margin: 0 }}><label className="fl">Provider Name</label><input placeholder="e.g. OpenAI, Anthropic" value={providerForm.name} onChange={(e) => setProviderForm((f) => ({ ...f, name: e.target.value }))} /></div>
          <div className="fg" style={{ margin: 0 }}><label className="fl">API Key</label><input type="password" placeholder="Provider API key" value={providerForm.api_key} onChange={(e) => setProviderForm((f) => ({ ...f, api_key: e.target.value }))} /></div>
          <div className="fg" style={{ margin: 0 }}><label className="fl">Base URL (optional)</label><input placeholder="https://api.provider.com/v1" value={providerForm.base_url} onChange={(e) => setProviderForm((f) => ({ ...f, base_url: e.target.value }))} /></div>
        </div>
        <div style={{ marginTop: 14 }}>
          <button className="btn bp" disabled={createProvider.isPending || !providerForm.name} onClick={() => createProvider.mutate()}>
            {createProvider.isPending ? 'Adding…' : 'Add Provider'}
          </button>
        </div>
      </div>

      <div className="card">
        <div className="sh"><div><div className="ct">AI Cost Per Tenant</div><div className="cs">This month, computed from actual token usage</div></div></div>
        <div className="tw"><table>
          <thead><tr><th>Tenant</th><th>Model</th><th>Turns</th><th>Tokens</th><th>Cost</th></tr></thead>
          <tbody>
            {costs?.map((c) => (
              <tr key={c.tenant_id}>
                <td style={{ fontWeight: 600 }}>{c.tenant_name}</td>
                <td>{c.model ? <div className="bdg b-in">{c.model}</div> : <div className="bdg b-gy">Unassigned</div>}</td>
                <td>{c.turns}</td>
                <td>{c.tokens.toLocaleString()}</td>
                <td><div className="cost-badge">${c.cost_usd.toFixed(2)}</div></td>
              </tr>
            ))}
            {costs?.length === 0 && <tr><td colSpan={5} style={{ color: 'var(--t3)' }}>No AI usage recorded this month.</td></tr>}
          </tbody>
        </table></div>
      </div>
    </Shell>
  )
}
