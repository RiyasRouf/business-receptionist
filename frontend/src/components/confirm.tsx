import { useSyncExternalStore } from 'react'

// Promise-based confirm dialog (SweetAlert-style) — self-contained, no
// external lib (CSP blocks CDNs). Only for status/delete/irreversible
// actions; save/edit no longer prompt. Reuses design-system modal CSS.
interface Opts { title: string; message?: string; confirmText?: string; danger?: boolean }
type State = (Opts & { resolve: (v: boolean) => void }) | null

let state: State = null
const listeners = new Set<() => void>()
const emit = () => listeners.forEach((l) => l())

export function confirmAction(opts: Opts): Promise<boolean> {
  return new Promise((resolve) => { state = { ...opts, resolve }; emit() })
}

function close(v: boolean) { state?.resolve(v); state = null; emit() }

export function ConfirmHost() {
  const snap = useSyncExternalStore(
    (cb) => { listeners.add(cb); return () => { listeners.delete(cb) } },
    () => state,
  )
  if (!snap) return null

  return (
    <div className="modal-overlay" onClick={() => close(false)}>
      <div className="modal-card" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 400, textAlign: 'center' }}>
        <div style={{ fontSize: 40, marginBottom: 8 }}>{snap.danger ? '⚠️' : '❓'}</div>
        <div className="ct" style={{ fontSize: 16, marginBottom: 6 }}>{snap.title}</div>
        {snap.message && <div className="cs" style={{ marginBottom: 20 }}>{snap.message}</div>}
        <div style={{ display: 'flex', gap: 8, justifyContent: 'center' }}>
          <button className="btn bs" onClick={() => close(false)}>Cancel</button>
          <button className={`btn ${snap.danger ? 'ber' : 'bp'}`} autoFocus onClick={() => close(true)}>{snap.confirmText ?? 'Confirm'}</button>
        </div>
      </div>
    </div>
  )
}
