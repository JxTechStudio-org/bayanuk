// Technical Plan, section 7 (GET /me) and Database Schema, section 3.1 (users).
export interface User {
  id: string
  // Optional: magic link users may not give a name.
  name: string | null
  email: string
  locale: 'ar' | 'en'
  keep_documents: boolean
}
