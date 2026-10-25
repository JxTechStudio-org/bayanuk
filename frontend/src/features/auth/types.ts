// Technical Plan, section 7 (GET /me): the signed-in user. Only the fields the frontend uses:
// the email shows in the account menu. The settings (locale, keep_documents, PATCH /me) are a later task.
export interface User {
  email: string
}
