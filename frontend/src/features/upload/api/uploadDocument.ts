import { request } from '@/lib/api'
import type { DocumentStatusResponse } from '@/features/analysis/types'

// Technical Plan, section 7: POST /documents, multipart upload, returns 202 with { id, status }.
export async function uploadDocument(file: File) {
  const body = new FormData()
  body.append('file', await sendableFile(file))
  return request<Pick<DocumentStatusResponse, 'id' | 'status'>>('/documents', { method: 'POST', body })
}

// Safari cannot pass a file chosen on the page to the fake API's service worker
// ("Unable to read form data file"). With the fake API on, the file is copied into memory first.
// The real API is reached directly, so there the file is sent as it is.
async function sendableFile(file: File): Promise<File> {
  if (import.meta.env.VITE_API_MOCK !== 'true') return file
  return new File([await file.arrayBuffer()], file.name, { type: file.type })
}
