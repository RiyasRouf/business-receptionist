import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell } from '@/components/Shell'
import { BUSINESS_NAV } from '@/lib/nav'

const PERMISSION_INFO: Record<string, { label: string; sub: string }> = {
  leads: { label: 'View Leads', sub: 'See captured lead list & update status' },
  knowledge_base: { label: 'Manage Knowledge Base', sub: 'Upload and delete documents' },
  live_calls: { label: 'Live Call Monitor', sub: 'Watch active calls live' },
  transcripts: { label: 'View Transcripts', sub: 'Read full call transcripts & summaries' },
  team: { label: 'Manage Team & Roles', sub: 'Invite staff, create/edit roles' },
}

interface Role { role_id: string; name: string; description: string | null; permissions_json: string[]; users_count: number }
interface RolesResponse { roles: Role[]; available_permissions: string[] }

async function fetchRoles(): Promise<RolesResponse> {
  return (await api.get<ApiSuccess<RolesResponse>>('/roles')).data.data
}

const emptyForm = { name: '', description: '' }

export function BusinessAdminRoles() {
  const { pathname } = useLocation()
  const queryClient = useQueryClient()
  const { data } = useQuery({ queryKey: ['roles'], queryFn: fetchRoles })

  const [form, setForm] = useState(emptyForm)
  const [selected, setSelected] = useState<Set<string>>(new Set())
  const [editingId, setEditingId] = useState<string | null>(null)

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['roles'] })

  const createRole = useMutation({
    mutationFn: async () => api.post('/roles', { name: form.name, description: form.description || null, permissions: Array.from(selected) }),
    onSuccess: () => { resetForm(); invalidate() },
  })

  const updateRole = useMutation({
    mutationFn: async () => api.put(`/roles/${editingId}`, { name: form.name, description: form.description || null, permissions: Array.from(selected) }),
    onSuccess: () => { resetForm(); invalidate() },
  })

  const deleteRole = useMutation({
    mutationFn: (roleId: string) => api.delete(`/roles/${roleId}`),
    onSuccess: () => invalidate(),
  })

  function resetForm() {
    setForm(emptyForm)
    setSelected(new Set())
    setEditingId(null)
  }

  function startEdit(role: Role) {
    setEditingId(role.role_id)
    setForm({ name: role.name, description: role.description ?? '' })
    setSelected(new Set(role.permissions_json))
  }

  function toggle(perm: string) {
    setSelected((s) => {
      const next = new Set(s)
      if (next.has(perm)) next.delete(perm)
      else next.add(perm)
      return next
    })
  }

  const isEditing = editingId !== null
  const saving = createRole.isPending || updateRole.isPending

  return (
    <Shell role="business" logo="B" roleLabel="Business Admin" navItems={BUSINESS_NAV} activePath={pathname}
      title="Roles & Permissions" subtitle="Create custom roles · set permissions per role · assign to staff">

      <div style={{ background: 'linear-gradient(135deg,#ECFDF5,#F0FDF4)', border: '1.5px solid #6EE7B7', borderRadius: 'var(--r3)', padding: '13px 17px', marginBottom: 20, display: 'flex', alignItems: 'center', gap: 10 }}>
        <div style={{ fontSize: 18 }}>🔐</div>
        <div>
          <div style={{ fontSize: 12.5, fontWeight: 700, color: 'var(--ok)' }}>Zero Access by Default</div>
          <div style={{ fontSize: 11.5, color: 'var(--t2)', marginTop: 2 }}>Staff have no access until you assign them a role. You decide exactly what each role can see and do.</div>
        </div>
      </div>

      <div className="card" style={{ marginBottom: 20, border: '1.5px solid #6EE7B7' }}>
        <div className="ct">{isEditing ? `Edit Role — ${form.name}` : 'Create New Role'}</div>
        <div className="cs">Name the role and toggle permissions on</div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 16 }}>
          <div className="fg" style={{ margin: 0 }}>
            <label className="fl">Role Name</label>
            <input placeholder="e.g. Admissions Officer, Receptionist" value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
          </div>
          <div className="fg" style={{ margin: 0 }}>
            <label className="fl">Description</label>
            <input placeholder="What this role is responsible for" value={form.description} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} />
          </div>
        </div>
        <div style={{ fontSize: 11, fontWeight: 600, color: 'var(--t2)', textTransform: 'uppercase', letterSpacing: '.04em', marginBottom: 10 }}>Permissions</div>
        <div className="perm-grid">
          {data?.available_permissions.map((perm) => (
            <div className={`perm-item ${selected.has(perm) ? 'on' : ''}`} key={perm} onClick={() => toggle(perm)}>
              <div>
                <div className="perm-label">{PERMISSION_INFO[perm]?.label ?? perm}</div>
                <div className="perm-sub">{PERMISSION_INFO[perm]?.sub ?? ''}</div>
              </div>
              <div className={`toggle ${selected.has(perm) ? 'on' : ''}`} />
            </div>
          ))}
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          <button className="btn bp" disabled={saving || !form.name || selected.size === 0}
            onClick={() => (isEditing ? updateRole.mutate() : createRole.mutate())}>
            {saving ? 'Saving…' : isEditing ? 'Save Changes' : 'Save Role'}
          </button>
          {isEditing && <button className="btn bs" onClick={resetForm}>Cancel</button>}
        </div>
      </div>

      <div className="sh"><div className="sht">Existing Roles <span style={{ fontSize: 13, color: 'var(--t3)', fontWeight: 400 }}>({data?.roles.length ?? 0})</span></div></div>
      {data?.roles.length === 0 && <div style={{ fontSize: 12, color: 'var(--t3)' }}>No custom roles yet.</div>}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        {data?.roles.map((role) => (
          <div className="card" key={role.role_id}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 9 }}>
                <div style={{ width: 32, height: 32, borderRadius: 'var(--r2)', background: 'linear-gradient(135deg,#10B981,#06B6D4)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11, fontWeight: 800, color: '#fff' }}>
                  {role.name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase()}
                </div>
                <div>
                  <div style={{ fontSize: 13, fontWeight: 700 }}>{role.name}</div>
                  <div style={{ fontSize: 10.5, color: 'var(--t3)' }}>{role.users_count} staff assigned{role.description ? ` · ${role.description}` : ''}</div>
                </div>
              </div>
              <div style={{ display: 'flex', gap: 6 }}>
                <button className="btn bs bsm" onClick={() => startEdit(role)}>Edit</button>
                <button className="btn ber bsm" disabled={deleteRole.isPending} onClick={() => deleteRole.mutate(role.role_id)}>Delete</button>
              </div>
            </div>
            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
              {data.available_permissions.map((perm) => (
                <div key={perm} className={`bdg ${role.permissions_json.includes(perm) ? 'b-ok' : 'b-gy'}`}>
                  {role.permissions_json.includes(perm) ? '✓' : '✗'} {PERMISSION_INFO[perm]?.label ?? perm}
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
    </Shell>
  )
}
