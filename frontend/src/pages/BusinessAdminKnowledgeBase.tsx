import { useRef, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { api, type ApiError, type ApiSuccess } from '@/lib/api'
import { Shell, type NavItem } from '@/components/Shell'

const NAV: NavItem[] = [
  { label: 'Dashboard', to: '/business' },
  { label: 'Team', to: '/business/team', permission: 'team' },
  { label: 'Knowledge Base', to: '/business/kb', permission: 'knowledge_base' },
  { label: 'Leads', to: '/leads', permission: 'leads' },
  { label: 'Roles & Permissions', to: '/business/roles', section: 'Configuration', permission: 'team' },
]

interface KbDocument {
  document_id: string
  title: string
  status: string
  created_at: string
}

async function fetchDocuments(): Promise<KbDocument[]> {
  const res = await api.get<ApiSuccess<KbDocument[]>>('/kb/documents')
  return res.data.data
}

export function BusinessAdminKnowledgeBase() {
  const { pathname } = useLocation()
  const queryClient = useQueryClient()
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [uploadError, setUploadError] = useState<string | null>(null)

  const { data: documents, isLoading } = useQuery({ queryKey: ['kb', 'documents'], queryFn: fetchDocuments })

  const uploadMutation = useMutation({
    mutationFn: async (file: File) => {
      const formData = new FormData()
      formData.append('title', file.name.replace(/\.(txt|md)$/i, ''))
      formData.append('file', file)

      // Don't set Content-Type manually — the browser must generate it
      // (including the multipart boundary) from the FormData body itself.
      return api.post('/kb/documents', formData)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kb', 'documents'] })
      setUploadError(null)
      if (fileInputRef.current) fileInputRef.current.value = ''
    },
    onError: (error) => {
      const message = isAxiosError<ApiError>(error) ? error.response?.data?.message : undefined
      setUploadError(message ?? 'Upload failed. Only .txt/.md files under 10MB are supported.')
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (documentId: string) => api.delete(`/kb/documents/${documentId}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['kb', 'documents'] }),
  })

  return (
    <Shell role="business" logo="B" roleLabel="Business Admin" navItems={NAV} activePath={pathname}
      title="Knowledge Base" subtitle={`${documents?.length ?? 0} documents`}>

      <div className="card" style={{ marginBottom: 20 }}>
        <div className="ct">Upload Document</div>
        <div className="cs">.txt or .md, up to 10MB. The AI answers caller questions from this content.</div>
        <div className="uz" onClick={() => fileInputRef.current?.click()}>
          <div style={{ fontSize: 24, marginBottom: 6 }}>📄</div>
          <div style={{ fontWeight: 600, fontSize: 13, marginBottom: 2 }}>
            {uploadMutation.isPending ? 'Uploading…' : 'Click to choose a file'}
          </div>
          <div style={{ fontSize: 11, color: 'var(--t3)' }}>.txt or .md</div>
        </div>
        <input
          ref={fileInputRef}
          type="file"
          accept=".txt,.md"
          style={{ display: 'none' }}
          onChange={(e) => {
            const file = e.target.files?.[0]
            if (file) uploadMutation.mutate(file)
          }}
        />
        {uploadError && <div style={{ color: 'var(--err)', fontSize: 12, marginTop: 10 }}>{uploadError}</div>}
      </div>

      <div className="card">
        <div className="sh"><div><div className="ct">Documents</div></div></div>
        {isLoading && <div style={{ fontSize: 12, color: 'var(--t3)' }}>Loading…</div>}
        {documents?.length === 0 && <div style={{ fontSize: 12, color: 'var(--t3)' }}>No documents uploaded yet.</div>}
        {documents && documents.length > 0 && (
          <div className="tw"><table>
            <thead><tr><th>Title</th><th>Status</th><th>Uploaded</th><th /></tr></thead>
            <tbody>
              {documents.map((doc) => (
                <tr key={doc.document_id}>
                  <td style={{ fontWeight: 600 }}>{doc.title}</td>
                  <td><div className={`bdg ${doc.status === 'ready' ? 'b-ok' : 'b-wn'}`}>{doc.status === 'ready' ? 'Active' : 'Processing'}</div></td>
                  <td>{new Date(doc.created_at).toLocaleDateString()}</td>
                  <td>
                    <button className="btn bs bsm" disabled={deleteMutation.isPending} onClick={() => deleteMutation.mutate(doc.document_id)}>
                      Delete
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table></div>
        )}
      </div>
    </Shell>
  )
}
