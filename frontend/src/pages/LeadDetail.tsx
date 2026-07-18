import { useEffect, useState } from 'react'
import { useLocation, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type ApiSuccess } from '@/lib/api'
import { Shell } from '@/components/Shell'
import { BUSINESS_NAV } from '@/lib/nav'

interface TimelineEvent {
  label: string
  at: string | null
  done: boolean
}

interface LeadDetailData {
  lead_id: string
  session_id: string
  status: string
  fields: Record<string, string | null>
  staff_notes: string | null
  transcript: string | null
  summary: string | null
  action_items: string[] | null
  created_at: string
  timeline: TimelineEvent[]
}

const STATUSES = ['partial', 'complete', 'contacted', 'enrolled', 'closed'] as const
const STATUS_LABEL: Record<string, string> = {
  partial: 'New', complete: 'In Progress', contacted: 'Followed Up', enrolled: 'Enrolled', closed: 'Closed',
}
const STATUS_BADGE: Record<string, string> = {
  partial: 'b-in', complete: 'b-wn', contacted: 'b-ok', enrolled: 'b-pu', closed: 'b-gy',
}

async function fetchLead(leadId: string): Promise<LeadDetailData> {
  return (await api.get<ApiSuccess<LeadDetailData>>(`/leads/${leadId}`)).data.data
}

export function LeadDetail() {
  const { leadId } = useParams<{ leadId: string }>()
  const { pathname } = useLocation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [notes, setNotes] = useState('')

  const { data: lead, isLoading } = useQuery({
    queryKey: ['leads', leadId],
    queryFn: () => fetchLead(leadId!),
    enabled: !!leadId,
  })

  useEffect(() => {
    if (lead) setNotes(lead.staff_notes ?? '')
  }, [lead])

  const statusMutation = useMutation({
    mutationFn: (status: string) => api.patch(`/leads/${leadId}/status`, { status }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leads', leadId] })
      queryClient.invalidateQueries({ queryKey: ['leads'] })
    },
  })

  const notesMutation = useMutation({
    mutationFn: () => api.patch(`/leads/${leadId}/notes`, { notes }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['leads', leadId] }),
  })

  const parentName = lead?.fields?.parent_name ?? 'Lead'
  const parentPhone = lead?.fields?.parent_phone

  return (
    <Shell
      role="business" logo="B" roleLabel="Business Admin" navItems={BUSINESS_NAV} activePath={pathname}
      title={parentName}
      topbarActions={lead && (
        <>
          <div className={`bdg ${STATUS_BADGE[lead.status] ?? 'b-gy'}`} style={{ fontSize: 12, padding: '4px 12px' }}>
            {STATUS_LABEL[lead.status] ?? lead.status}
          </div>
          <button
            className="btn bok"
            disabled={statusMutation.isPending || lead.status === 'contacted'}
            onClick={() => window.confirm('Mark this lead as followed up?') && statusMutation.mutate('contacted')}
          >
            ✓ Mark Followed Up
          </button>
          {parentPhone && <a className="btn bs" href={`tel:${parentPhone}`}>📞 Call Back</a>}
        </>
      )}
    >
      <button className="btn bgh" style={{ padding: '5px 0', marginBottom: 14 }} onClick={() => navigate('/leads')}>← Back to Leads</button>

      {isLoading && <div style={{ fontSize: 12, color: 'var(--t3)' }}>Loading…</div>}
      {lead && (
        <div className="g2" style={{ alignItems: 'start' }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            <div className="card">
              <div className="ct">Captured Fields</div>
              <div className="cs">Auto-extracted during the call — never manually entered</div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                {Object.entries(lead.fields ?? {}).map(([key, value]) => (
                  <div className="lf" key={key}>
                    <div className="ll">{key.replace(/_/g, ' ')}</div>
                    <div className="lv">{value ?? '—'}</div>
                  </div>
                ))}
              </div>
              {lead.summary && (
                <div className="lf" style={{ gridColumn: 'span 2', marginTop: 8 }}>
                  <div className="ll">AI Summary</div>
                  <div className="lv" style={{ lineHeight: 1.6, fontSize: 11.5 }}>{lead.summary}</div>
                </div>
              )}
            </div>

            <div className="card">
              <div className="ct">Staff Notes</div>
              <div className="cs">Visible to all team members with lead access</div>
              <textarea
                placeholder="Add follow-up notes…"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
              />
              <div style={{ marginTop: 10, display: 'flex', gap: 8, alignItems: 'center' }}>
                <button className="btn bp" disabled={notesMutation.isPending} onClick={() => window.confirm('Save these notes?') && notesMutation.mutate()}>
                  {notesMutation.isPending ? 'Saving…' : 'Save Notes'}
                </button>
                <select
                  value={lead.status}
                  onChange={(e) => {
                    const status = e.target.value
                    if (window.confirm(`Change status to "${STATUS_LABEL[status] ?? status}"?`)) statusMutation.mutate(status)
                  }}
                  disabled={statusMutation.isPending}
                  style={{ width: 160 }}
                >
                  {STATUSES.map((s) => <option key={s} value={s}>{STATUS_LABEL[s]}</option>)}
                </select>
              </div>
            </div>
          </div>

          <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            <div className="card">
              <div className="ct">Call Transcript</div>
              {lead.transcript ? (
                <div style={{ background: 'var(--bg)', borderRadius: 'var(--r2)', padding: 13, fontSize: 11.5, lineHeight: 1.8, maxHeight: 300, overflowY: 'auto', whiteSpace: 'pre-wrap' }}>
                  {lead.transcript}
                </div>
              ) : (
                <div style={{ fontSize: 12, color: 'var(--t3)' }}>Not available yet.</div>
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
              <div className="ct">Activity Timeline</div>
              <div className="tlx" style={{ marginTop: 8 }}>
                {lead.timeline.map((event, i) => (
                  <div className="tli" key={i}>
                    <div className={`tld ${event.done ? 'dk' : ''}`} />
                    <div className="tlt"><b>{event.label}</b></div>
                    <div className="tlm">{event.at ? new Date(event.at).toLocaleString() : (event.done ? '' : 'Pending')}</div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}
    </Shell>
  )
}
