import { postJson, request } from '@/lib/api'
import type { User } from '../types'

// Integration contract, section 10: exchanges the token from the email link for a session.
// The guest token goes with it, so the guest's documents move to the account.
export function verifySignInLink(token: string) {
  return request<{ user: User }>('/auth/magic-link/verify', postJson({ token }), { guestToken: 'claim' })
}
