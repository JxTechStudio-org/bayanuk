import { postJson, request } from '@/lib/api'
import type { ChatAnswer } from '../types'

// Integration contract, section 14. A guest sends the guest token with question requests (section 5).
export function askQuestion(documentId: string, question: string, signedIn: boolean) {
  return request<ChatAnswer>(`/documents/${documentId}/chat`, postJson({ question }), {
    guestToken: signedIn ? undefined : 'guest',
  })
}
