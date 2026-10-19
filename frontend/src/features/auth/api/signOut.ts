import { request } from '@/lib/api'

// Technical Plan, section 7: POST /auth/logout ends the session.
export function signOut() {
  return request<void>('/auth/logout', { method: 'POST' })
}
