import { useState, type CSSProperties } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import axios, { isAxiosError } from 'axios'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { authStore, type AuthUser } from '@/lib/auth-store'
import type { ApiError, ApiSuccess } from '@/lib/api'
import '@/design-system.css'

const loginSchema = z.object({
  email: z.string().email('Enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
})

type LoginForm = z.infer<typeof loginSchema>

interface PlatformBrand { name: string; color: string; tagline: string; logo_url: string | null }

async function fetchPlatformBrand(): Promise<PlatformBrand> {
  return (await axios.get<ApiSuccess<PlatformBrand>>('/api/v1/public/branding')).data.data
}

export function LoginPage() {
  const navigate = useNavigate()
  const [serverError, setServerError] = useState<string | null>(null)
  const { data: brand } = useQuery({ queryKey: ['public', 'branding'], queryFn: fetchPlatformBrand })

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginForm>({ resolver: zodResolver(loginSchema) })

  async function onSubmit(values: LoginForm) {
    setServerError(null)

    try {
      const res = await axios.post<ApiSuccess<{ access_token: string; user: AuthUser }>>(
        '/api/v1/auth/login',
        values,
        { withCredentials: true },
      )

      authStore.setSession(res.data.data.access_token, res.data.data.user)
      // Role-based landing route is resolved centrally by RootRedirect.
      navigate('/')
    } catch (err) {
      if (isAxiosError<ApiError>(err) && err.response) {
        setServerError(err.response.data.message ?? 'Login failed.')
      } else {
        setServerError('Something went wrong. Please try again.')
      }
    }
  }

  return (
    <div className="ds-root" data-role="platform" style={brand?.color ? ({ '--p': brand.color } as CSSProperties) : undefined}>
      <div className="auth-wrap">
        <div className="auth-card">
          <div className="auth-logo">
            {brand?.logo_url ? <img src={brand.logo_url} alt="" style={{ height: 32, verticalAlign: 'middle', marginRight: 8 }} /> : null}
            {brand?.name ?? 'Business AI ✦'}
          </div>
          <div className="auth-tag">{brand?.tagline ?? 'AI Business Receptionist Platform'}</div>
          <div className="auth-title">Sign in</div>
          <div className="auth-sub">Enter your credentials to continue</div>
          <form onSubmit={handleSubmit(onSubmit)} noValidate>
            <label className="al" htmlFor="email">Email address</label>
            <input
              id="email"
              className="ai-inp"
              type="email"
              autoComplete="email"
              placeholder="you@business.ae"
              {...register('email')}
            />
            {errors.email && <div className="af-err">{errors.email.message}</div>}

            <label className="al" htmlFor="password">Password</label>
            <input
              id="password"
              className="ai-inp"
              type="password"
              autoComplete="current-password"
              placeholder="••••••••••"
              {...register('password')}
            />
            {errors.password && <div className="af-err">{errors.password.message}</div>}

            {serverError && <div className="af-err">{serverError}</div>}

            <button type="submit" className="ab" disabled={isSubmitting}>
              {isSubmitting ? 'Signing in…' : 'Sign in →'}
            </button>
          </form>
          <div className="af">Role and access resolved automatically from your account</div>
        </div>
      </div>
    </div>
  )
}
