import { useQuery } from '@tanstack/react-query'
import { api, type ApiSuccess } from '@/lib/api'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/hooks/use-auth'
import { authStore } from '@/lib/auth-store'

interface Tenant {
  tenant_id: string
  slug: string
  status: string
  created_at: string
}

async function fetchTenants(): Promise<Tenant[]> {
  const res = await api.get<ApiSuccess<Tenant[]>>('/admin/tenants')

  return res.data.data
}

export function PlatformAdminDashboard() {
  const { user } = useAuth()

  const { data: tenants, isLoading, isError } = useQuery({
    queryKey: ['admin', 'tenants'],
    queryFn: fetchTenants,
  })

  return (
    <div className="mx-auto max-w-5xl p-6 space-y-6">
      <header className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Platform Admin</h1>
          <p className="text-sm text-muted-foreground">Signed in as {user?.email}</p>
        </div>
        <Button variant="outline" onClick={() => authStore.clear()}>
          Sign out
        </Button>
      </header>

      <Card>
        <CardHeader>
          <CardTitle>Tenants</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading && <p className="text-sm text-muted-foreground">Loading…</p>}
          {isError && (
            <p className="text-sm text-destructive">Failed to load tenants.</p>
          )}
          {tenants && tenants.length === 0 && (
            <p className="text-sm text-muted-foreground">No tenants yet.</p>
          )}
          {tenants && tenants.length > 0 && (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Slug</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Created</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {tenants.map((tenant) => (
                  <TableRow key={tenant.tenant_id}>
                    <TableCell className="font-medium">{tenant.slug}</TableCell>
                    <TableCell>
                      <Badge variant={tenant.status === 'active' ? 'default' : 'secondary'}>
                        {tenant.status}
                      </Badge>
                    </TableCell>
                    <TableCell>{new Date(tenant.created_at).toLocaleDateString()}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
