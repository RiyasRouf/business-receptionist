import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type ApiSuccess } from '@/lib/api'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useAuth } from '@/hooks/use-auth'
import { authStore } from '@/lib/auth-store'

interface LeadSummary {
  lead_id: string
  session_id: string
  status: string
  parent_name: string | null
  child_name: string | null
  created_at: string
}

interface LeadDetail {
  lead_id: string
  session_id: string
  status: string
  fields: Record<string, string | null>
  created_at: string
}

const STATUSES = ['partial', 'complete', 'contacted', 'enrolled', 'closed'] as const

async function fetchLeads(): Promise<LeadSummary[]> {
  const res = await api.get<ApiSuccess<LeadSummary[]>>('/leads')

  return res.data.data
}

async function fetchLead(leadId: string): Promise<LeadDetail> {
  const res = await api.get<ApiSuccess<LeadDetail>>(`/leads/${leadId}`)

  return res.data.data
}

// F-15 (Staff Dashboard: leads list + detail, all 13 fields, status
// update, audit trail). Transcript/recording/AI-summary links are
// deferred — no per-lead endpoint surfacing the linked session's
// transcript_id/summary_id exists yet (leads and transcripts are both
// keyed by session_id, but nothing joins them for the frontend today).
export function StaffReviewDashboard() {
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const [selectedLeadId, setSelectedLeadId] = useState<string | null>(null)

  const { data: leads, isLoading } = useQuery({
    queryKey: ['leads'],
    queryFn: fetchLeads,
  })

  const { data: leadDetail } = useQuery({
    queryKey: ['leads', selectedLeadId],
    queryFn: () => fetchLead(selectedLeadId!),
    enabled: selectedLeadId !== null,
  })

  const statusMutation = useMutation({
    mutationFn: ({ leadId, status }: { leadId: string; status: string }) =>
      api.patch(`/leads/${leadId}/status`, { status }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      queryClient.invalidateQueries({ queryKey: ['leads', selectedLeadId] })
    },
  })

  return (
    <div className="mx-auto max-w-5xl p-6 space-y-6">
      <header className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Leads</h1>
          <p className="text-sm text-muted-foreground">Signed in as {user?.email}</p>
        </div>
        <Button variant="outline" onClick={() => authStore.clear()}>
          Sign out
        </Button>
      </header>

      <Card>
        <CardHeader>
          <CardTitle>Captured leads</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading && <p className="text-sm text-muted-foreground">Loading…</p>}
          {leads && leads.length === 0 && (
            <p className="text-sm text-muted-foreground">No leads captured yet.</p>
          )}
          {leads && leads.length > 0 && (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Parent</TableHead>
                  <TableHead>Child</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Captured</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                {leads.map((lead) => (
                  <TableRow key={lead.lead_id}>
                    <TableCell className="font-medium">{lead.parent_name ?? '—'}</TableCell>
                    <TableCell>{lead.child_name ?? '—'}</TableCell>
                    <TableCell>
                      <Badge variant={lead.status === 'complete' ? 'default' : 'secondary'}>
                        {lead.status}
                      </Badge>
                    </TableCell>
                    <TableCell>{new Date(lead.created_at).toLocaleDateString()}</TableCell>
                    <TableCell>
                      <Button variant="ghost" size="sm" onClick={() => setSelectedLeadId(lead.lead_id)}>
                        View
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Dialog open={selectedLeadId !== null} onOpenChange={(open) => !open && setSelectedLeadId(null)}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Lead detail</DialogTitle>
          </DialogHeader>
          {leadDetail && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-3 text-sm">
                {Object.entries(leadDetail.fields).map(([key, value]) => (
                  <div key={key}>
                    <div className="text-muted-foreground">{key.replace(/_/g, ' ')}</div>
                    <div className="font-medium">{value ?? '—'}</div>
                  </div>
                ))}
              </div>

              <div className="flex items-center gap-2 pt-2">
                <span className="text-sm text-muted-foreground">Status:</span>
                {STATUSES.map((status) => (
                  <Button
                    key={status}
                    size="sm"
                    variant={leadDetail.status === status ? 'default' : 'outline'}
                    disabled={statusMutation.isPending}
                    onClick={() => statusMutation.mutate({ leadId: leadDetail.lead_id, status })}
                  >
                    {status}
                  </Button>
                ))}
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}
