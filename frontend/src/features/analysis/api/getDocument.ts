import { request } from '@/lib/api'
import type { DocumentResult } from '../types'

// Integration contract, section 13. A guest sends the guest token; a signed-in user does not (section 5).
export function getDocument(id: string, signedIn: boolean) {
  return request<DocumentResult>(`/documents/${id}`, {}, { guestToken: signedIn ? undefined : 'guest' })
}
