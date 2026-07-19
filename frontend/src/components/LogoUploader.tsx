import { useMutation, useQueryClient, type QueryKey } from '@tanstack/react-query'
import { api } from '@/lib/api'

interface LogoUploaderProps {
  logoUrl: string | null
  uploadUrl: string
  deleteUrl: string
  queryKey: QueryKey
}

const ACCEPT = 'image/*,.svg,.ico,.tiff,.tif,.avif,.heic,.heif'

export function LogoUploader({ logoUrl, uploadUrl, deleteUrl, queryKey }: LogoUploaderProps) {
  const queryClient = useQueryClient()

  const upload = useMutation({
    mutationFn: (file: File) => {
      const body = new FormData()
      body.append('logo', file)

      return api.post(uploadUrl, body)
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey }),
  })

  const remove = useMutation({
    mutationFn: () => {
      if (!window.confirm('Remove this logo?')) return Promise.reject('cancelled')

      return api.delete(deleteUrl)
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey }),
  })

  return (
    <div className="fg">
      <label className="fl">Logo</label>
      <div style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
        {logoUrl ? (
          <img src={logoUrl} alt="Logo" style={{ width: 48, height: 48, borderRadius: 'var(--r1)', objectFit: 'contain', border: '1px solid var(--bdr)', background: '#fff' }} />
        ) : (
          <div style={{ width: 48, height: 48, borderRadius: 'var(--r1)', border: '1px dashed var(--bdr)' }} />
        )}
        <input
          type="file"
          accept={ACCEPT}
          disabled={upload.isPending}
          onChange={(e) => {
            const file = e.target.files?.[0]
            if (file) upload.mutate(file)
            e.target.value = ''
          }}
        />
        {logoUrl && (
          <button type="button" className="btn bgh bsm" disabled={remove.isPending} onClick={() => remove.mutate()}>
            Remove
          </button>
        )}
      </div>
      {upload.isPending && <div className="cs">Uploading…</div>}
      {upload.isError && <div className="af-err">Upload failed. Common image formats, max 5MB.</div>}
    </div>
  )
}
