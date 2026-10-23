import { ApiError, postJson, request } from '@/lib/api'
import { getCurrentUser } from './getCurrentUser'

// Integration contract, section 10: exchanges the token from the email link for a session.
// The guest token goes with it, so the guest's documents move to the account.
// The backend sets the session cookie; the user is then loaded from GET /me, as after Google (section 11).
export async function verifySignInLink(token: string) {
  await request<unknown>('/auth/magic-link/verify', postJson({ token }), { guestToken: 'claim' })
  const user = await getCurrentUser()
  // The link was accepted but there is no session: shown as a failed sign-in, with an option to try again.
  if (!user) throw new ApiError(401, 'unauthorized', '')
  return user
}
