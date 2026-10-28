import { request } from '@/lib/api'
import type { ChatHistory } from '../types'

// Integration contract, section 14. A guest sends the guest token with question requests (section 5).
export function getChat(documentId: string, signedIn: boolean) {
  return request<ChatHistory>(`/documents/${documentId}/chat`, {}, { guestToken: signedIn ? undefined : 'guest' })
}
