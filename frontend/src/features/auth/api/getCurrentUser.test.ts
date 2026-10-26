import { afterEach, expect, it, vi } from 'vitest'
import { getCurrentUser } from './getCurrentUser'

afterEach(() => {
  vi.restoreAllMocks()
})

it('returns the user when signed in', async () => {
  const user = { id: 'u1', name: null, email: 'sara@example.com', locale: 'ar', keep_documents: false }
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(Response.json(user))
  await expect(getCurrentUser()).resolves.toEqual(user)
})

it('returns null for a guest (401)', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(
    Response.json({ error: { code: 'unauthorized', message: '' } }, { status: 401 }),
  )
  await expect(getCurrentUser()).resolves.toBeNull()
})
