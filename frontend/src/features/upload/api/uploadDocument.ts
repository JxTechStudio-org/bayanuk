import { request } from '@/lib/api'
import type { DocumentStatusResponse } from '@/features/analysis/types'

// Technical Plan, section 7: POST /documents, multipart upload, returns 202 with { id, status }.
export function uploadDocument(file: File) {
  const body = new FormData()
  body.append('file', file)
  return request<Pick<DocumentStatusResponse, 'id' | 'status'>>('/documents', { method: 'POST', body })
}
