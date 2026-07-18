import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useSessionBootstrap } from '@/hooks/use-session-bootstrap'
import { useAuth } from '@/hooks/use-auth'
import { ProtectedRoute } from '@/routes/ProtectedRoute'
import { LoginPage } from '@/pages/LoginPage'
import { PlatformAdminDashboard } from '@/pages/PlatformAdminDashboard'
import { PlatformAdminTenants } from '@/pages/PlatformAdminTenants'
import { PlatformAdminUsers } from '@/pages/PlatformAdminUsers'
import { PlatformAdminAIProviders } from '@/pages/PlatformAdminAIProviders'
import { BusinessAdminDashboard } from '@/pages/BusinessAdminDashboard'
import { BusinessAdminTeam } from '@/pages/BusinessAdminTeam'
import { BusinessAdminKnowledgeBase } from '@/pages/BusinessAdminKnowledgeBase'
import { BusinessAdminRoles } from '@/pages/BusinessAdminRoles'
import { StaffLeads } from '@/pages/StaffLeads'
import { LeadDetail } from '@/pages/LeadDetail'

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 1, staleTime: 30_000 } },
})

function homeFor(role: string): string {
  if (role === 'platform_admin') return '/admin'
  if (role === 'tenant_admin') return '/business'
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
        <Route path="/admin/tenants" element={<PlatformAdminTenants />} />
        <Route path="/admin/users" element={<PlatformAdminUsers />} />
        <Route path="/admin/ai-providers" element={<PlatformAdminAIProviders />} />
      </Route>

      <Route element={<ProtectedRoute allowedRoles={['tenant_admin']} />}>
        <Route path="/business" element={<BusinessAdminDashboard />} />
        <Route path="/business/team" element={<BusinessAdminTeam />} />
        <Route path="/business/kb" element={<BusinessAdminKnowledgeBase />} />
        <Route path="/business/roles" element={<BusinessAdminRoles />} />
      </Route>

      {/* Leads: staff reviews them (F-14/F-15); tenant_admin can also
          drill in from their dashboard's lead pipeline. */}
      <Route element={<ProtectedRoute allowedRoles={['staff', 'tenant_admin']} />}>
        <Route path="/leads" element={<StaffLeads />} />
        <Route path="/leads/:leadId" element={<LeadDetail />} />
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
