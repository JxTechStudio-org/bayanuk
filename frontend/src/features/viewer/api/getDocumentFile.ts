import { request } from '@/lib/api'
import type { DocumentFile } from '../types'

// Integration contract, section 9. A guest sends the guest token, as with the other document requests (section 5).
export function getDocumentFile(documentId: string, signedIn: boolean) {
  return request<DocumentFile>(`/documents/${documentId}/file`, {}, { guestToken: signedIn ? undefined : 'guest' })
}
