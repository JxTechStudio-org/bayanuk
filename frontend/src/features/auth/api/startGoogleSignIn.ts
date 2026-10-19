import { postJson, request } from '@/lib/api'
import { TERMS_VERSION } from '../terms'

// Integration contract, section 11: asks the backend for the Google sign-in address.
// The guest token goes with it, so the guest's documents move to the account after sign-in.
export function startGoogleSignIn() {
  return request<{ url: string }>('/auth/google/redirect', postJson({ terms_version: TERMS_VERSION }), {
    guestToken: 'claim',
  })
}
