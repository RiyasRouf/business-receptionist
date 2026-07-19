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
    <div className="cfm-overlay" onClick={() => close(false)}>
      <div className={`cfm-card ${snap.danger ? 'cfm-danger' : ''}`} onClick={(e) => e.stopPropagation()} role="alertdialog" aria-modal="true">
        <div className="cfm-icon">
          {snap.danger ? (
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />
            </svg>
          ) : (
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="12" cy="12" r="10" /><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" /><line x1="12" y1="17" x2="12.01" y2="17" />
            </svg>
          )}
        </div>
        <div className="cfm-title">{snap.title}</div>
        {snap.message && <div className="cfm-msg">{snap.message}</div>}
        <div className="cfm-actions">
          <button className="cfm-btn cfm-cancel" onClick={() => close(false)}>Cancel</button>
          <button className={`cfm-btn ${snap.danger ? 'cfm-confirm-danger' : 'cfm-confirm'}`} autoFocus onClick={() => close(true)}>{snap.confirmText ?? 'Confirm'}</button>
        </div>
      </div>
    </div>
  )
}
