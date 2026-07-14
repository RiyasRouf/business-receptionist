// Access token lives in memory only (ADR-058) — never localStorage/
// sessionStorage, which are readable by any injected script (XSS).
// Refresh token is a Secure HttpOnly cookie the browser sends
// automatically; JS never touches it. Lost on page reload by design —
// the app calls /auth/refresh on boot to re-establish the session from
// that cookie.

export interface AuthUser {
  user_id: string
  name: string
  email: string
  role: 'platform_admin' | 'tenant_admin' | 'staff'
  tenant_id: string | null
}

interface AuthState {
  accessToken: string | null
  user: AuthUser | null
}

type Listener = (state: AuthState) => void

let state: AuthState = { accessToken: null, user: null }
const listeners = new Set<Listener>()

function notify() {
  listeners.forEach((l) => l(state))
}

export const authStore = {
  getState: (): AuthState => state,

  setSession(accessToken: string, user: AuthUser) {
    state = { accessToken, user }
    notify()
  },

  clear() {
    state = { accessToken: null, user: null }
    notify()
  },

  subscribe(listener: Listener): () => void {
    listeners.add(listener)
    return () => listeners.delete(listener)
  },
}
