import { request } from '@/lib/api'
import type { DocumentStatusResponse } from '@/features/analysis/types'

// Technical Plan, section 7: POST /documents, multipart upload, returns 202 with { id, status }.
// A guest sends the guest token; a signed-in user does not (integration contract, section 5).
export function uploadDocument(file: File, signedIn: boolean) {
  const body = new FormData()
  body.append('file', file)
  return request<Pick<DocumentStatusResponse, 'id' | 'status'>>(
    '/documents',
    { method: 'POST', body },
    { guestToken: signedIn ? undefined : 'guest' },
  )
}
