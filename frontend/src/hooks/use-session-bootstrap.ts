import { useEffect, useState } from 'react'
import axios from 'axios'
import { authStore, type AuthUser } from '@/lib/auth-store'
import type { ApiSuccess } from '@/lib/api'

/**
 * Access token lives in memory only (ADR-058) and is lost on every page
 * reload. On mount, try to re-establish the session from the HttpOnly
 * refresh_token cookie the browser still has. Resolves once, either way
 * — the caller shows a loading state until `ready`.
 */
export function useSessionBootstrap() {
  const [ready, setReady] = useState(false)

  useEffect(() => {
    let cancelled = false

    axios
      .post<ApiSuccess<{ access_token: string; user: AuthUser }>>(
        '/api/v1/auth/refresh',
        {},
        { withCredentials: true },
      )
      .then((res) => {
        if (!cancelled) {
          authStore.setSession(res.data.data.access_token, res.data.data.user)
        }
      })
      .catch(() => {
        // No valid refresh cookie — stay logged out, not an error state.
      })
      .finally(() => {
        if (!cancelled) setReady(true)
      })

    return () => {
      cancelled = true
    }
  }, [])

  return ready
}
