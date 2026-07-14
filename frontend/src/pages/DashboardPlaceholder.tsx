import { useAuth } from '@/hooks/use-auth'
import { authStore } from '@/lib/auth-store'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

// School Admin (Module 16) and Staff Review (Module 17) dashboards are
// Sprint 5 — this placeholder just proves auth + routing work end to
// end for tenant_admin/staff roles ahead of those.
export function DashboardPlaceholder() {
  const { user } = useAuth()

  return (
    <div className="mx-auto max-w-3xl p-6 space-y-6">
      <header className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Dashboard</h1>
          <p className="text-sm text-muted-foreground">
            Signed in as {user?.email} ({user?.role})
          </p>
        </div>
        <Button variant="outline" onClick={() => authStore.clear()}>
          Sign out
        </Button>
      </header>

      <Card>
        <CardHeader>
          <CardTitle>Coming in Sprint 5</CardTitle>
        </CardHeader>
        <CardContent className="text-sm text-muted-foreground">
          School Admin (KB management, usage view) and Staff Review (lead
          list/detail, transcripts, summaries) dashboards land here.
        </CardContent>
      </Card>
    </div>
  )
}
