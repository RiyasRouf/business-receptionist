import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell } from '@/components/Shell'
import { PLATFORM_NAV } from '@/lib/nav'

interface Model { model_id: string; name: string; input_cost_per_1m: number; output_cost_per_1m: number }
interface Provider { provider_id: string; name: string; status: string; base_url: string | null; models: Model[] }
interface Cost { tenant_id: string; tenant_name: string; model: string | null; provider: string | null; tokens: number; turns: number; cost_usd: number }
interface Tenant { tenant_id: string; name: string | null; slug: string; ai_provider_model_id: string | null; ai_model: (Model & { provider: { name: string } }) | null }
interface FetchedModel { name: string; input_cost_per_1m: number | null; output_cost_per_1m: number | null }

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
  const [availableModels, setAvailableModels] = useState<Record<string, FetchedModel[]>>({})
  const [editingProvider, setEditingProvider] = useState<Provider | null>(null)
  const [editForm, setEditForm] = useState({ api_key: '', base_url: '', status: 'active' })

  const invalidateProviders = () => queryClient.invalidateQueries({ queryKey: ['admin', 'ai-providers'] })

  const createProvider = useMutation({
    mutationFn: async () => (await api.post('/admin/ai-providers', providerForm)).data,
    onSuccess: () => { setProviderForm({ name: '', api_key: '', base_url: '' }); invalidateProviders() },
  })

  const updateProvider = useMutation({
    mutationFn: async () => api.put(`/admin/ai-providers/${editingProvider!.provider_id}`, {
      api_key: editForm.api_key || undefined,
      base_url: editForm.base_url,
      status: editForm.status,
    }),
    onSuccess: () => { setEditingProvider(null); invalidateProviders() },
  })

  const fetchModelsForProvider = useMutation({
    mutationFn: async (providerId: string) =>
      (await api.get<ApiSuccess<{ source: string; models: FetchedModel[] }>>(`/admin/ai-providers/${providerId}/available-models`)).data.data,
    onSuccess: (data, providerId) => setAvailableModels((m) => ({ ...m, [providerId]: data.models })),
  })

  const addModel = useMutation({
    mutationFn: async ({ providerId, name }: { providerId: string; name: string }) =>
      api.post(`/admin/ai-providers/${providerId}/models`, { name }),
    onSuccess: () => invalidateProviders(),
  })

  const assignTenant = useMutation({
    mutationFn: async ({ tenantId, modelId }: { tenantId: string; modelId: string }) =>
      api.put(`/admin/tenants/${tenantId}/ai-assignment`, { ai_provider_model_id: modelId }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'tenants'] })
      queryClient.invalidateQueries({ queryKey: ['admin', 'usage-cost'] })
    },
  })

  function openEdit(p: Provider) {
    setEditingProvider(p)
    setEditForm({ api_key: '', base_url: p.base_url ?? '', status: p.status })
  }

  const allModels = providers?.flatMap((p) => p.models.map((m) => ({ ...m, providerName: p.name }))) ?? []
  const totalCost = costs?.reduce((sum, c) => sum + c.cost_usd, 0) ?? 0
  const addedModelNames = (p: Provider) => new Set(p.models.map((m) => m.name))

  return (
    <Shell role="platform" logo="B" roleLabel="Platform Admin" navItems={PLATFORM_NAV} activePath={pathname}
      title="AI Providers" subtitle="Add providers · fetch models at their real token rate · assign per tenant">

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
              <div style={{ flex: 1 }}><div style={{ fontSize: 14, fontWeight: 700 }}>{p.name}</div><div style={{ fontSize: 11, color: 'var(--t3)' }}>{p.models.length} model(s) configured</div></div>
              <button className="btn bs bsm" onClick={() => openEdit(p)}>✎ Edit</button>
            </div>

            <div style={{ background: 'var(--bg)', borderRadius: 'var(--r2)', padding: 12 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                <div style={{ fontSize: 11, fontWeight: 600, color: 'var(--t2)', textTransform: 'uppercase', letterSpacing: '.04em' }}>Models</div>
                <button className="btn bs bsm" disabled={fetchModelsForProvider.isPending} onClick={() => fetchModelsForProvider.mutate(p.provider_id)}>
                  {fetchModelsForProvider.isPending ? 'Fetching…' : '↻ Fetch Available Models'}
                </button>
              </div>
              <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: availableModels[p.provider_id] ? 10 : 0 }}>
                {p.models.map((m) => (
                  <div className="bdg b-in" key={m.model_id}>{m.name} · ${m.input_cost_per_1m}/1M in · ${m.output_cost_per_1m}/1M out</div>
                ))}
                {p.models.length === 0 && <span style={{ fontSize: 11, color: 'var(--t3)' }}>None added yet.</span>}
              </div>

              {availableModels[p.provider_id] && (
                <div>
                  <div className="fl" style={{ marginBottom: 4 }}>Click to add at its real token rate</div>
                  <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                    {availableModels[p.provider_id]
                      .filter((m) => !addedModelNames(p).has(m.name))
                      .map((m) => (
                        <button key={m.name} className="btn bs bsm" disabled={addModel.isPending}
                          onClick={() => window.confirm(`Add model "${m.name}" to ${p.name}?`) && addModel.mutate({ providerId: p.provider_id, name: m.name })}>
                          + {m.name}{m.input_cost_per_1m != null ? ` · $${m.input_cost_per_1m}/$${m.output_cost_per_1m} per 1M` : ' · rate unknown'}
                        </button>
                      ))}
                  </div>
                </div>
              )}
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
          <button className="btn bp" disabled={createProvider.isPending || !providerForm.name} onClick={() => window.confirm(`Add provider "${providerForm.name}"?`) && createProvider.mutate()}>
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
                    onChange={(e) => {
                      const modelId = e.target.value
                      if (modelId && window.confirm(`Change ${t.name ?? t.slug}'s AI model?`)) assignTenant.mutate({ tenantId: t.tenant_id, modelId })
                    }}
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

      {editingProvider && (
        <div className="modal-overlay" onClick={() => setEditingProvider(null)}>
          <div className="modal-card" onClick={(e) => e.stopPropagation()}>
            <div className="ct">Edit {editingProvider.name}</div>
            <div className="cs">Update credentials and connection settings</div>

            <div className="fg"><label className="fl">Provider Name</label><input value={editingProvider.name} disabled /></div>
            <div className="fg">
              <label className="fl">API Key</label>
              <input type="password" placeholder="Leave blank to keep current" value={editForm.api_key}
                onChange={(e) => setEditForm((f) => ({ ...f, api_key: e.target.value }))} />
            </div>
            <div className="fg">
              <label className="fl">Base URL</label>
              <input placeholder="https://api.provider.com/v1" value={editForm.base_url}
                onChange={(e) => setEditForm((f) => ({ ...f, base_url: e.target.value }))} />
            </div>
            <div className="fg">
              <label className="fl">Status</label>
              <select value={editForm.status} onChange={(e) => setEditForm((f) => ({ ...f, status: e.target.value }))}>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>

            <div style={{ display: 'flex', gap: 8, marginTop: 16 }}>
              <button className="btn bp" disabled={updateProvider.isPending} onClick={() => window.confirm(`Save changes to ${editingProvider.name}?`) && updateProvider.mutate()}>
                {updateProvider.isPending ? 'Saving…' : 'Save Changes'}
              </button>
              <button className="btn bs" onClick={() => setEditingProvider(null)}>Cancel</button>
            </div>
          </div>
        </div>
      )}
    </Shell>
  )
}
