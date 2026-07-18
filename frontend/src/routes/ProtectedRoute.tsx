import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '@/hooks/use-auth'
import type { AuthUser } from '@/lib/auth-store'

interface ProtectedRouteProps {
  allowedRoles?: AuthUser['role'][]
  // Only meaningful for tenant_admin routes. null on the user =
  // unrestricted admin, always passes regardless of what's required
  // here. Omit to allow any tenant_admin (e.g. the dashboard, which
  // has no dedicated permission on the backend either).
  requiredPermission?: string
}

export function ProtectedRoute({ allowedRoles, requiredPermission }: ProtectedRouteProps) {
  const { isAuthenticated, user } = useAuth()

  if (!isAuthenticated || !user) {
    return <Navigate to="/login" replace />
  }

  if (allowedRoles && !allowedRoles.includes(user.role)) {
    return <Navigate to="/" replace />
  }

  if (requiredPermission && user.permissions !== null && !user.permissions.includes(requiredPermission)) {
    return <Navigate to="/" replace />
  }

  return <Outlet />
}
