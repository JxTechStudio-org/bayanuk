import { request } from '@/lib/api'

// Technical Plan, section 7.2: deletes the file, its text, the analysis and the chat at once. Answers 204.
// A guest sends the guest token; a signed-in user does not (integration contract, section 5).
export function deleteDocument(id: string, signedIn: boolean) {
  return request<void>(`/documents/${id}`, { method: 'DELETE' }, { guestToken: signedIn ? undefined : 'guest' })
}
