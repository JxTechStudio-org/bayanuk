import { vi } from 'vitest'

export const signedInUser = { id: 'u1', name: null, email: 'sara@example.com', locale: 'ar', keep_documents: false }

// GET /me answers as a guest (401) or as the signed-in user. Every other request answers 204,
// so the tests can see what was sent.
export function fakeSession(signedIn: boolean) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
    if (String(input) !== '/api/v1/me') return new Response(null, { status: 204 })
    return signedIn
      ? Response.json(signedInUser)
      : Response.json({ error: { code: 'unauthorized', message: '' } }, { status: 401 })
  })
}
