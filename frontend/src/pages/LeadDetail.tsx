import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type ApiSuccess } from '@/lib/api'

interface LeadDetailData {
  lead_id: string
  session_id: string
  status: string
  fields: Record<string, string | null>
  transcript: string | null
  summary: string | null
  action_items: string[] | null
  created_at: string
}

const STATUSES = ['partial', 'complete', 'contacted', 'enrolled', 'closed'] as const
const STATUS_LABEL: Record<string, string> = {
  partial: 'New', complete: 'In Progress', contacted: 'Followed Up', enrolled: 'Enrolled', closed: 'Closed',
}

async function fetchLead(leadId: string): Promise<LeadDetailData> {
  return (await api.get<ApiSuccess<LeadDetailData>>(`/leads/${leadId}`)).data.data
}

export function LeadDetail() {
  const { leadId } = useParams<{ leadId: string }>()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data: lead, isLoading } = useQuery({
    queryKey: ['leads', leadId],
    queryFn: () => fetchLead(leadId!),
    enabled: !!leadId,
  })

  const statusMutation = useMutation({
    mutationFn: (status: string) => api.patch(`/leads/${leadId}/status`, { status }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leads', leadId] })
      queryClient.invalidateQueries({ queryKey: ['leads'] })
    },
  })

  return (
    <div className="ds-root" data-role="business" style={{ minHeight: '100vh', background: 'var(--bg)' }}>
      <div className="topbar">
        <button className="btn bgh" onClick={() => navigate(-1)}>← Back</button>
        <span className="tb-t" style={{ marginLeft: 8 }}>Lead Detail</span>
        {lead && <div className="tb-a"><div className={`bdg ${lead.status === 'contacted' ? 'b-ok' : 'b-in'}`}>{STATUS_LABEL[lead.status] ?? lead.status}</div></div>}
      </div>

      <div className="content" style={{ maxWidth: 1100, margin: '0 auto' }}>
        {isLoading && <div style={{ fontSize: 12, color: 'var(--t3)' }}>Loading…</div>}
        {lead && (
          <div className="g2">
            <div>
              <div className="card" style={{ marginBottom: 16 }}>
                <div className="ct">Captured Details</div>
                <div className="cs">All fields the AI collected during the call</div>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                  {Object.entries(lead.fields ?? {}).map(([key, value]) => (
                    <div className="lf" key={key}>
                      <div className="ll">{key.replace(/_/g, ' ')}</div>
                      <div className="lv">{value ?? '—'}</div>
                    </div>
                  ))}
                </div>
              </div>

              <div className="card">
                <div className="ct">Status</div>
                <div className="cs">Update as you follow up</div>
                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                  {STATUSES.map((status) => (
                    <button
                      key={status}
                      className={`btn bsm ${lead.status === status ? 'bp' : 'bs'}`}
                      disabled={statusMutation.isPending}
                      onClick={() => statusMutation.mutate(status)}
                    >
                      {STATUS_LABEL[status]}
                    </button>
                  ))}
                </div>
              </div>
            </div>

            <div>
              <div className="card" style={{ marginBottom: 16 }}>
                <div className="ct">AI Summary</div>
                {lead.summary ? (
                  <p style={{ fontSize: 12.5, lineHeight: 1.6 }}>{lead.summary}</p>
                ) : (
                  <div style={{ fontSize: 12, color: 'var(--t3)' }}>Not generated yet.</div>
                )}
                {lead.action_items && lead.action_items.length > 0 && (
                  <>
                    <div className="divider" />
                    <div className="ll" style={{ marginBottom: 6 }}>Action Items</div>
                    <ul style={{ paddingLeft: 18, fontSize: 12.5 }}>
                      {lead.action_items.map((item, i) => <li key={i}>{item}</li>)}
                    </ul>
                  </>
                )}
              </div>

              <div className="card">
                <div className="ct">Call Transcript</div>
                {lead.transcript ? (
                  <pre style={{ fontSize: 11.5, whiteSpace: 'pre-wrap', lineHeight: 1.6, fontFamily: 'var(--fb)' }}>{lead.transcript}</pre>
                ) : (
                  <div style={{ fontSize: 12, color: 'var(--t3)' }}>Not available.</div>
                )}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
