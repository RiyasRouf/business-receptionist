import { useSyncExternalStore } from 'react'
import { authStore } from '@/lib/auth-store'

export function useAuth() {
  const state = useSyncExternalStore(authStore.subscribe, authStore.getState)

  return {
    user: state.user,
    accessToken: state.accessToken,
    isAuthenticated: state.accessToken !== null,
  }
}
