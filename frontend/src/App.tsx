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
import { PlatformAdminVoice } from '@/pages/PlatformAdminVoice'
import { PlatformAdminVoiceTenant } from '@/pages/PlatformAdminVoiceTenant'
import { PlatformAdminBranding } from '@/pages/PlatformAdminBranding'
import { PlatformAdminTenantBrand } from '@/pages/PlatformAdminTenantBrand'
import { PlatformAdminHealth } from '@/pages/PlatformAdminHealth'
import { PlatformAdminAuditLogs } from '@/pages/PlatformAdminAuditLogs'
import { BusinessAdminDashboard } from '@/pages/BusinessAdminDashboard'
import { BusinessAdminTeam } from '@/pages/BusinessAdminTeam'
import { BusinessAdminKnowledgeBase } from '@/pages/BusinessAdminKnowledgeBase'
import { BusinessAdminRoles } from '@/pages/BusinessAdminRoles'
import { BusinessAdminVoice } from '@/pages/BusinessAdminVoice'
import { BusinessAdminBranding } from '@/pages/BusinessAdminBranding'
import { StaffLeads } from '@/pages/StaffLeads'
import { LeadDetail } from '@/pages/LeadDetail'

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 1, staleTime: 30_000 } },
})

function homeFor(user: NonNullable<ReturnType<typeof useAuth>['user']>): string {
  if (user.role === 'platform_admin') return '/admin'

  // Unrestricted admin (permissions === null) or anyone with no more
  // specific permission lands on the dashboard — it has no permission
  // requirement of its own. A permission-limited member with 'leads'
  // (the common case) goes straight to their actual work instead.
  if (user.permissions !== null && user.permissions.includes('leads') && !user.permissions.includes('team')) {
    return '/leads'
  }

  return '/business'
}

function RootRedirect() {
  const { user } = useAuth()

  if (!user) return <Navigate to="/login" replace />

  return <Navigate to={homeFor(user)} replace />
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
        <Route path="/admin/voice" element={<PlatformAdminVoice />} />
        <Route path="/admin/voice/:tenantId" element={<PlatformAdminVoiceTenant />} />
        <Route path="/admin/branding" element={<PlatformAdminBranding />} />
        <Route path="/admin/branding/:tenantId" element={<PlatformAdminTenantBrand />} />
        <Route path="/admin/health" element={<PlatformAdminHealth />} />
        <Route path="/admin/audit-logs" element={<PlatformAdminAuditLogs />} />
      </Route>

      {/* Only 2 system roles exist — everyone here is tenant_admin.
          requiredPermission gates the permission-limited (custom
          custom_role_id set); unrestricted admins (permissions===null)
          always pass. Dashboard has no permission of its own. */}
      <Route element={<ProtectedRoute allowedRoles={['tenant_admin']} />}>
        <Route path="/business" element={<BusinessAdminDashboard />} />
      </Route>
      <Route element={<ProtectedRoute allowedRoles={['tenant_admin']} requiredPermission="team" />}>
        <Route path="/business/team" element={<BusinessAdminTeam />} />
        <Route path="/business/roles" element={<BusinessAdminRoles />} />
        <Route path="/business/voice" element={<BusinessAdminVoice />} />
        <Route path="/business/brand" element={<BusinessAdminBranding />} />
      </Route>
      <Route element={<ProtectedRoute allowedRoles={['tenant_admin']} requiredPermission="knowledge_base" />}>
        <Route path="/business/kb" element={<BusinessAdminKnowledgeBase />} />
      </Route>
      <Route element={<ProtectedRoute allowedRoles={['tenant_admin']} requiredPermission="leads" />}>
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
