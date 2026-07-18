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
interface Provider { provider_id: string; name: string; status: string; base_url: string | null; models: Model[] }
interface Cost { tenant_id: string; tenant_name: string; model: string | null; provider: string | null; tokens: number; turns: number; cost_usd: number }
interface Tenant { tenant_id: string; name: string | null; slug: string; ai_provider_model_id: string | null; ai_model: (Model & { provider: { name: string } }) | null }

async function fetchProviders(): Promise<Provider[]> {
  return (await api.get<ApiSuccess<Provider[]>>('/admin/ai-providers')).data.data
}
async function fetchCosts(): Promise<Cost[]> {
  return (await api.get<ApiSuccess<Cost[]>>('/admin/usage-cost')).data.data
}
async function fetchTenants(): Promise<Tenant[]> {
  return (await api.get<ApiSuccess<Tenant[]>>('/admin/tenants')).data.data
}

export function PlatformAdminAIProviders() {
  const { pathname } = useLocation()
  const queryClient = useQueryClient()
  const { data: providers } = useQuery({ queryKey: ['admin', 'ai-providers'], queryFn: fetchProviders })
  const { data: costs } = useQuery({ queryKey: ['admin', 'usage-cost'], queryFn: fetchCosts })
  const { data: tenants } = useQuery({ queryKey: ['admin', 'tenants'], queryFn: fetchTenants })

  const [providerForm, setProviderForm] = useState({ name: '', api_key: '', base_url: '' })
  const [modelForms, setModelForms] = useState<Record<string, { name: string; input_cost_per_1m: string; output_cost_per_1m: string }>>({})
  const [availableModels, setAvailableModels] = useState<Record<string, string[]>>({})
  const [editingKey, setEditingKey] = useState<Record<string, string>>({})

  const invalidateProviders = () => queryClient.invalidateQueries({ queryKey: ['admin', 'ai-providers'] })

  const createProvider = useMutation({
    mutationFn: async () => (await api.post('/admin/ai-providers', providerForm)).data,
    onSuccess: () => { setProviderForm({ name: '', api_key: '', base_url: '' }); invalidateProviders() },
  })

  const updateProvider = useMutation({
    mutationFn: async (providerId: string) => api.put(`/admin/ai-providers/${providerId}`, { api_key: editingKey[providerId] }),
    onSuccess: (_d, providerId) => { setEditingKey((k) => ({ ...k, [providerId]: '' })); invalidateProviders() },
  })

  const fetchModelsForProvider = useMutation({
    mutationFn: async (providerId: string) =>
      (await api.get<ApiSuccess<{ source: string; models: string[] }>>(`/admin/ai-providers/${providerId}/available-models`)).data.data,
    onSuccess: (data, providerId) => setAvailableModels((m) => ({ ...m, [providerId]: data.models })),
  })

  const addModel = useMutation({
    mutationFn: async (providerId: string) => {
      const f = modelField(providerId)
      return api.post(`/admin/ai-providers/${providerId}/models`, {
        name: f.name,
        input_cost_per_1m: Number(f.input_cost_per_1m) || 0,
        output_cost_per_1m: Number(f.output_cost_per_1m) || 0,
      })
    },
    onSuccess: (_data, providerId) => {
      setModelForms((f) => ({ ...f, [providerId]: { name: '', input_cost_per_1m: '', output_cost_per_1m: '' } }))
      invalidateProviders()
    },
  })

  const assignTenant = useMutation({
    mutationFn: async ({ tenantId, modelId }: { tenantId: string; modelId: string }) =>
      api.put(`/admin/tenants/${tenantId}/ai-assignment`, { ai_provider_model_id: modelId }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'tenants'] })
      queryClient.invalidateQueries({ queryKey: ['admin', 'usage-cost'] })
    },
  })

  function modelField(providerId: string) {
    return modelForms[providerId] ?? { name: '', input_cost_per_1m: '', output_cost_per_1m: '' }
  }
  function setModelField(providerId: string, patch: Partial<{ name: string; input_cost_per_1m: string; output_cost_per_1m: string }>) {
    setModelForms((f) => ({ ...f, [providerId]: { ...modelField(providerId), ...patch } }))
  }

  const allModels = providers?.flatMap((p) => p.models.map((m) => ({ ...m, providerName: p.name }))) ?? []
  const totalCost = costs?.reduce((sum, c) => sum + c.cost_usd, 0) ?? 0

  return (
    <Shell role="platform" logo="B" roleLabel="Platform Admin" navItems={NAV} activePath={pathname}
      title="AI Providers" subtitle="Add providers · fetch all their models · assign per tenant · cost tracked per tenant">

      <div className="sg">
        <div className="sc am"><div className="si2 am">🤖</div><div className="sv">${totalCost.toFixed(2)}</div><div className="sl">Total AI Cost · This Month</div></div>
        <div className="sc vi"><div className="si2 vi">🔌</div><div className="sv">{providers?.length ?? 0}</div><div className="sl">Providers Configured</div></div>
      </div>

      <div className="sh" style={{ marginBottom: 14 }}><div className="sht">Active AI Providers</div></div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12, marginBottom: 24 }}>
        {providers?.map((p) => (
          <div className="ai-card active" key={p.provider_id}>
            <div className="ai-badge"><div className={`bdg ${p.status === 'active' ? 'b-ok' : 'b-gy'}`}>{p.status === 'active' ? 'Active' : 'Inactive'}</div></div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 14 }}>
              <div style={{ width: 40, height: 40, borderRadius: 'var(--r2)', background: 'var(--bg)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 18 }}>🤖</div>
              <div><div style={{ fontSize: 14, fontWeight: 700 }}>{p.name}</div><div style={{ fontSize: 11, color: 'var(--t3)' }}>{p.models.length} model(s) configured</div></div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr auto', gap: 8, marginBottom: 12, alignItems: 'end' }}>
              <div className="fg" style={{ margin: 0 }}>
                <label className="fl">Edit API Key</label>
                <input type="password" placeholder="Leave blank to keep current"
                  value={editingKey[p.provider_id] ?? ''}
                  onChange={(e) => setEditingKey((k) => ({ ...k, [p.provider_id]: e.target.value }))} />
              </div>
              <button className="btn bs bsm" disabled={updateProvider.isPending || !editingKey[p.provider_id]} onClick={() => updateProvider.mutate(p.provider_id)}>Save Key</button>
            </div>

            <div style={{ background: 'var(--bg)', borderRadius: 'var(--r2)', padding: 12, marginBottom: 12 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                <div style={{ fontSize: 11, fontWeight: 600, color: 'var(--t2)', textTransform: 'uppercase', letterSpacing: '.04em' }}>Models</div>
                <button className="btn bs bsm" disabled={fetchModelsForProvider.isPending} onClick={() => fetchModelsForProvider.mutate(p.provider_id)}>
                  {fetchModelsForProvider.isPending ? 'Fetching…' : '↻ Fetch All Available Models'}
                </button>
              </div>
              <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 10 }}>
                {p.models.map((m) => (
                  <div className="bdg b-in" key={m.model_id}>{m.name} · ${m.input_cost_per_1m}/1M in · ${m.output_cost_per_1m}/1M out</div>
                ))}
                {p.models.length === 0 && <span style={{ fontSize: 11, color: 'var(--t3)' }}>None added yet.</span>}
              </div>

              {availableModels[p.provider_id] && (
                <div style={{ marginBottom: 10 }}>
                  <div className="fl" style={{ marginBottom: 4 }}>Available (click to fill name below)</div>
                  <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                    {availableModels[p.provider_id].map((name) => (
                      <button key={name} className="btn bs bsm" onClick={() => setModelField(p.provider_id, { name })}>{name}</button>
                    ))}
                  </div>
                </div>
              )}

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr auto', gap: 8, alignItems: 'end' }}>
                <div className="fg" style={{ margin: 0 }}><label className="fl">Model Name</label><input placeholder="gpt-4o-mini" value={modelField(p.provider_id).name} onChange={(e) => setModelField(p.provider_id, { name: e.target.value })} /></div>
                <div className="fg" style={{ margin: 0 }}><label className="fl">Input $/1M</label><input placeholder="0.15" value={modelField(p.provider_id).input_cost_per_1m} onChange={(e) => setModelField(p.provider_id, { input_cost_per_1m: e.target.value })} /></div>
                <div className="fg" style={{ margin: 0 }}><label className="fl">Output $/1M</label><input placeholder="0.60" value={modelField(p.provider_id).output_cost_per_1m} onChange={(e) => setModelField(p.provider_id, { output_cost_per_1m: e.target.value })} /></div>
                <button className="btn bs bsm" disabled={addModel.isPending || !modelField(p.provider_id).name} onClick={() => addModel.mutate(p.provider_id)}>+ Add Model</button>
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

      <div className="card" style={{ marginBottom: 24 }}>
        <div className="sh"><div><div className="ct">AI Assignment Per Tenant</div><div className="cs">Change the active model for any tenant</div></div></div>
        <div className="tw"><table>
          <thead><tr><th>Tenant</th><th>Current Model</th><th>Change To</th></tr></thead>
          <tbody>
            {tenants?.map((t) => (
              <tr key={t.tenant_id}>
                <td style={{ fontWeight: 600 }}>{t.name ?? t.slug}</td>
                <td>{t.ai_model ? <div className="bdg b-in">{t.ai_model.provider.name} · {t.ai_model.name}</div> : <div className="bdg b-gy">Unassigned</div>}</td>
                <td>
                  <select
                    value={t.ai_provider_model_id ?? ''}
                    onChange={(e) => e.target.value && assignTenant.mutate({ tenantId: t.tenant_id, modelId: e.target.value })}
                    style={{ width: 220 }}
                  >
                    <option value="">— Select model —</option>
                    {allModels.map((m) => <option key={m.model_id} value={m.model_id}>{m.providerName} · {m.name}</option>)}
                  </select>
                </td>
              </tr>
            ))}
          </tbody>
        </table></div>
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
