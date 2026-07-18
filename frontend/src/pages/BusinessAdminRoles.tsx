import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell, type NavItem } from '@/components/Shell'

const NAV: NavItem[] = [
  { label: 'Dashboard', to: '/business' },
  { label: 'Team', to: '/business/team' },
  { label: 'Knowledge Base', to: '/business/kb' },
  { label: 'Leads', to: '/leads' },
  { label: 'Roles & Permissions', to: '/business/roles', section: 'Configuration' },
]

const PERMISSION_LABELS: Record<string, string> = {
  leads: 'Leads', knowledge_base: 'Knowledge Base', live_calls: 'Live Calls', transcripts: 'Transcripts', team: 'Team',
}

interface Role { role_id: string; name: string; permissions_json: string[]; users_count: number }
interface RolesResponse { roles: Role[]; available_permissions: string[] }

async function fetchRoles(): Promise<RolesResponse> {
  return (await api.get<ApiSuccess<RolesResponse>>('/roles')).data.data
}

export function BusinessAdminRoles() {
  const { pathname } = useLocation()
  const queryClient = useQueryClient()
  const { data } = useQuery({ queryKey: ['roles'], queryFn: fetchRoles })

  const [name, setName] = useState('')
  const [selected, setSelected] = useState<Set<string>>(new Set())

  const createRole = useMutation({
    mutationFn: async () => api.post('/roles', { name, permissions: Array.from(selected) }),
    onSuccess: () => {
      setName('')
      setSelected(new Set())
      queryClient.invalidateQueries({ queryKey: ['roles'] })
    },
  })

  const deleteRole = useMutation({
    mutationFn: (roleId: string) => api.delete(`/roles/${roleId}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['roles'] }),
  })

  function toggle(perm: string) {
    setSelected((s) => {
      const next = new Set(s)
      if (next.has(perm)) next.delete(perm)
      else next.add(perm)
      return next
    })
  }

  return (
    <Shell role="business" logo="B" roleLabel="Business Admin" navItems={NAV} activePath={pathname}
      title="Roles & Permissions" subtitle={`${data?.roles.length ?? 0} custom roles`}>

      <div className="info-box">
        <span>ℹ️</span>
        <span>Create named roles with permission sets, then assign them to team members from the Team screen.</span>
      </div>

      <div className="card" style={{ marginBottom: 20 }}>
        <div className="ct">Create New Role</div>
        <div className="cs">Toggle which areas this role can access</div>
        <div className="fg">
          <label className="fl">Role Name</label>
          <input placeholder="Admissions Officer" value={name} onChange={(e) => setName(e.target.value)} />
        </div>
        <div className="perm-grid">
          {data?.available_permissions.map((perm) => (
            <div className={`perm-item ${selected.has(perm) ? 'on' : ''}`} key={perm} onClick={() => toggle(perm)}>
              <div className="perm-label">{PERMISSION_LABELS[perm] ?? perm}</div>
              <div className={`toggle ${selected.has(perm) ? 'on' : ''}`} />
            </div>
          ))}
        </div>
        <button className="btn bp" disabled={createRole.isPending || !name || selected.size === 0} onClick={() => createRole.mutate()}>
          {createRole.isPending ? 'Creating…' : '+ Create Role'}
        </button>
      </div>

      <div className="card">
        <div className="sh"><div><div className="ct">Existing Roles</div></div></div>
        {data?.roles.length === 0 && <div style={{ fontSize: 12, color: 'var(--t3)' }}>No custom roles yet.</div>}
        <div style={{ display: 'flex', flexDirection: 'column', gap: 9 }}>
          {data?.roles.map((role) => (
            <div key={role.role_id} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '9px 12px', background: 'var(--bg)', borderRadius: 'var(--r2)' }}>
              <div>
                <div style={{ fontSize: 12.5, fontWeight: 700 }}>{role.name}</div>
                <div style={{ fontSize: 10.5, color: 'var(--t3)' }}>
                  {role.users_count} staff · {role.permissions_json.map((p) => PERMISSION_LABELS[p] ?? p).join(' + ')}
                </div>
              </div>
              <button className="btn bs bsm" disabled={deleteRole.isPending} onClick={() => deleteRole.mutate(role.role_id)}>Delete</button>
            </div>
          ))}
        </div>
      </div>
    </Shell>
  )
}
