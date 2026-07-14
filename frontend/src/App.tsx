import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useSessionBootstrap } from '@/hooks/use-session-bootstrap'
import { useAuth } from '@/hooks/use-auth'
import { ProtectedRoute } from '@/routes/ProtectedRoute'
import { LoginPage } from '@/pages/LoginPage'
import { PlatformAdminDashboard } from '@/pages/PlatformAdminDashboard'
import { SchoolAdminDashboard } from '@/pages/SchoolAdminDashboard'
import { StaffReviewDashboard } from '@/pages/StaffReviewDashboard'

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 1, staleTime: 30_000 } },
})

function homeFor(role: string): string {
  if (role === 'platform_admin') return '/admin'
  if (role === 'tenant_admin') return '/school'
  return '/leads'
}

function RootRedirect() {
  const { user } = useAuth()

  if (!user) return <Navigate to="/login" replace />

  return <Navigate to={homeFor(user.role)} replace />
}

function AppRoutes() {
  const ready = useSessionBootstrap()

  if (!ready) {
    return (
      <div className="flex min-h-screen items-center justify-center text-muted-foreground">
        Loading…
      </div>
    )
  }

  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/" element={<RootRedirect />} />

      <Route element={<ProtectedRoute allowedRoles={['platform_admin']} />}>
        <Route path="/admin" element={<PlatformAdminDashboard />} />
      </Route>

      {/* tenant_admin: knowledge base management (F-16/F-17). staff:
          lead review (F-14/F-15). Each role gets its own home for now —
          a combined view for tenant_admin (who arguably wants both) is
          a reasonable follow-up, not built here to avoid guessing at
          IA/nav structure that's really a design decision. */}
      <Route element={<ProtectedRoute allowedRoles={['tenant_admin']} />}>
        <Route path="/school" element={<SchoolAdminDashboard />} />
      </Route>

      <Route element={<ProtectedRoute allowedRoles={['staff']} />}>
        <Route path="/leads" element={<StaffReviewDashboard />} />
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AppRoutes />
      </BrowserRouter>
    </QueryClientProvider>
  )
}

export default App
