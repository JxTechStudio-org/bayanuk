import { request } from '@/lib/api'
import type { DocumentStatusResponse } from '../types'

// A guest sends the guest token; a signed-in user does not (integration contract, section 5).
export function getDocumentStatus(id: string, signedIn: boolean) {
  return request<DocumentStatusResponse>(`/documents/${id}/status`, {}, { guestToken: signedIn ? undefined : 'guest' })
}
