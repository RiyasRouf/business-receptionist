import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type ApiSuccess } from '@/lib/api'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
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

// F-16 (Module 16, Sprint 5): School Admin manages the tenant's own
// knowledge base. Usage dashboard (allowance consumption) is deferred —
// no read endpoint for a tenant's own usage_aggregates exists yet
// (TenantController's allowance view is platform_admin-only); adding
// one is Module 13 follow-up work, not invented here.
export function SchoolAdminDashboard() {
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const [title, setTitle] = useState('')
  const [file, setFile] = useState<File | null>(null)
  const [uploadError, setUploadError] = useState<string | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)

  const { data: documents, isLoading } = useQuery({
    queryKey: ['kb', 'documents'],
    queryFn: fetchDocuments,
  })

  const uploadMutation = useMutation({
    mutationFn: async () => {
      if (!file) throw new Error('No file selected')

      const formData = new FormData()
      formData.append('title', title)
      formData.append('file', file)

      return api.post('/kb/documents', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kb', 'documents'] })
      setTitle('')
      setFile(null)
      setUploadError(null)
      setDialogOpen(false)
    },
    onError: () => setUploadError('Upload failed. Only .txt/.md files under 10MB are supported.'),
  })

  const deleteMutation = useMutation({
    mutationFn: (documentId: string) => api.delete(`/kb/documents/${documentId}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['kb', 'documents'] }),
  })

  return (
    <div className="mx-auto max-w-5xl p-6 space-y-6">
      <header className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold">School Admin</h1>
          <p className="text-sm text-muted-foreground">Signed in as {user?.email}</p>
        </div>
        <Button variant="outline" onClick={() => authStore.clear()}>
          Sign out
        </Button>
      </header>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Knowledge Base</CardTitle>
          <Button onClick={() => setDialogOpen(true)}>Upload document</Button>
          <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Upload knowledge base document</DialogTitle>
              </DialogHeader>
              <form
                className="space-y-4"
                onSubmit={(e) => {
                  e.preventDefault()
                  uploadMutation.mutate()
                }}
              >
                <div className="space-y-2">
                  <Label htmlFor="doc-title">Title</Label>
                  <Input
                    id="doc-title"
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="doc-file">File (.txt or .md)</Label>
                  <Input
                    id="doc-file"
                    type="file"
                    accept=".txt,.md"
                    onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                    required
                  />
                </div>
                {uploadError && <p className="text-sm text-destructive">{uploadError}</p>}
                <Button type="submit" className="w-full" disabled={uploadMutation.isPending}>
                  {uploadMutation.isPending ? 'Uploading…' : 'Upload'}
                </Button>
              </form>
            </DialogContent>
          </Dialog>
        </CardHeader>
        <CardContent>
          {isLoading && <p className="text-sm text-muted-foreground">Loading…</p>}
          {documents && documents.length === 0 && (
            <p className="text-sm text-muted-foreground">No documents uploaded yet.</p>
          )}
          {documents && documents.length > 0 && (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Title</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Uploaded</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                {documents.map((doc) => (
                  <TableRow key={doc.document_id}>
                    <TableCell className="font-medium">{doc.title}</TableCell>
                    <TableCell>
                      <Badge variant={doc.status === 'ready' ? 'default' : 'secondary'}>
                        {doc.status}
                      </Badge>
                    </TableCell>
                    <TableCell>{new Date(doc.created_at).toLocaleDateString()}</TableCell>
                    <TableCell>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => deleteMutation.mutate(doc.document_id)}
                        disabled={deleteMutation.isPending}
                      >
                        Delete
                      </Button>
                    </TableCell>
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
