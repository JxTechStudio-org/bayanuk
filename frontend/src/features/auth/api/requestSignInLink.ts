import { postJson, request } from '@/lib/api'
import { TERMS_VERSION } from '../terms'

// Integration contract, section 10: sends a sign-in link to the email. The link works once, for 15 minutes.
export function requestSignInLink(email: string) {
  return request<void>('/auth/magic-link', postJson({ email, terms_version: TERMS_VERSION }))
}
