import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { authStore } from './auth-store'

// Standard success/error envelope (ADR-026, ADR-029).
export interface ApiSuccess<T> {
  success: true
  data: T
  meta?: Record<string, unknown>
  trace_id: string
}

export interface ApiError {
  error_code: string
  message: string
  trace_id: string
  session_id?: string
  timestamp: string
}

export const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true, // send the HttpOnly refresh_token cookie
})

api.interceptors.request.use((config) => {
  const { accessToken } = authStore.getState()

  if (accessToken) {
    config.headers.Authorization = `Bearer ${accessToken}`
  }

  return config
})

let refreshPromise: Promise<string | null> | null = null

async function refreshAccessToken(): Promise<string | null> {
  // Multiple concurrent 401s shouldn't fire multiple refresh calls —
  // share one in-flight request.
  refreshPromise ??= axios
    .post<ApiSuccess<{ access_token: string; user: import('./auth-store').AuthUser }>>(
      '/api/v1/auth/refresh',
      {},
      { withCredentials: true },
    )
    .then((res) => {
      const { access_token, user } = res.data.data
      authStore.setSession(access_token, user)

      return access_token
    })
    .catch(() => {
      authStore.clear()

      return null
    })
    .finally(() => {
      refreshPromise = null
    })

  return refreshPromise
}

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const original = error.config as (InternalAxiosRequestConfig & { _retried?: boolean }) | undefined

    if (error.response?.status === 401 && original && !original._retried && !original.url?.includes('/auth/')) {
      original._retried = true
      const newToken = await refreshAccessToken()

      if (newToken) {
        original.headers.Authorization = `Bearer ${newToken}`

        return api(original)
      }
    }

    return Promise.reject(error)
  },
)
