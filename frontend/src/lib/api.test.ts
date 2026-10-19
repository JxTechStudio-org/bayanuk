import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { ApiError, request } from './api'

// The fake API: each call returns the next answer listed for its address.
// An answer can be a function, for one that does something when it is sent (like setting a cookie).
type Answer = Response | (() => Response)

function fakeApi(answers: Record<string, Answer[]>) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
    const answer = answers[String(input)]?.shift()
    if (!answer) throw new Error(`No answer for ${String(input)}`)
    return typeof answer === 'function' ? answer() : answer
  })
}

// What each call sent: its address and its headers.
function calls(fetch: ReturnType<typeof fakeApi>) {
  return fetch.mock.calls.map(([input, init]) => ({
    url: String(input),
    headers: (init?.headers ?? {}) as Record<string, string>,
  }))
}

const csrfCookie = () => {
  document.cookie = 'XSRF-TOKEN=csrf-1; path=/'
  return new Response(null, { status: 204 })
}

beforeEach(() => {
  // These tests start as a new guest, with no token yet.
  localStorage.clear()
})

afterEach(() => {
  vi.restoreAllMocks()
})

it('asks the backend for a guest token, then sends it in X-Guest-Token', async () => {
  const fetch = fakeApi({
    '/sanctum/csrf-cookie': [csrfCookie],
    '/api/v1/guest-token': [Response.json({ token: 'guest-1' }, { status: 201 })],
    '/api/v1/documents/1/status': [Response.json({ id: '1' })],
  })

  await request('/documents/1/status', {}, { guestToken: 'guest' })

  expect(calls(fetch).map((call) => call.url)).toEqual([
    '/sanctum/csrf-cookie',
    '/api/v1/guest-token',
    '/api/v1/documents/1/status',
  ])
  expect(calls(fetch)[2].headers['X-Guest-Token']).toBe('guest-1')
  expect(localStorage.getItem('bayanuk.guestToken')).toBe('guest-1')
})

it('asks for one token when two requests start together', async () => {
  const fetch = fakeApi({
    '/sanctum/csrf-cookie': [csrfCookie],
    '/api/v1/guest-token': [Response.json({ token: 'guest-1' }, { status: 201 })],
    '/api/v1/documents/1/status': [Response.json({}), Response.json({})],
  })

  await Promise.all([
    request('/documents/1/status', {}, { guestToken: 'guest' }),
    request('/documents/1/status', {}, { guestToken: 'guest' }),
  ])

  expect(calls(fetch).filter((call) => call.url === '/api/v1/guest-token')).toHaveLength(1)
})

it('sends the CSRF token from the cookie on a request that changes data', async () => {
  localStorage.setItem('bayanuk.guestToken', 'guest-1')
  const fetch = fakeApi({
    '/sanctum/csrf-cookie': [csrfCookie],
    '/api/v1/documents': [Response.json({ id: '1', status: 'uploaded' }, { status: 202 })],
  })

  await request('/documents', { method: 'POST' })

  expect(calls(fetch)[1].headers['X-XSRF-TOKEN']).toBe('csrf-1')
})

it('refreshes the CSRF cookie and repeats the request once after a 419', async () => {
  localStorage.setItem('bayanuk.guestToken', 'guest-1')
  const fetch = fakeApi({
    '/sanctum/csrf-cookie': [csrfCookie, csrfCookie],
    '/api/v1/documents': [
      Response.json({ error: { code: 'csrf_token_mismatch', message: '' } }, { status: 419 }),
      Response.json({ id: '1', status: 'uploaded' }, { status: 202 }),
    ],
  })

  await expect(request('/documents', { method: 'POST' })).resolves.toEqual({ id: '1', status: 'uploaded' })
  expect(calls(fetch).filter((call) => call.url === '/api/v1/documents')).toHaveLength(2)
})

it('replaces a guest token the backend does not accept, and repeats the request once', async () => {
  localStorage.setItem('bayanuk.guestToken', 'old-token')
  document.cookie = 'XSRF-TOKEN=csrf-1; path=/'
  const fetch = fakeApi({
    '/api/v1/guest-token': [Response.json({ token: 'new-token' }, { status: 201 })],
    '/api/v1/documents/1/status': [
      Response.json({ error: { code: 'invalid_guest_token', message: '' } }, { status: 400 }),
      Response.json({ id: '1' }),
    ],
  })

  await request('/documents/1/status', {}, { guestToken: 'guest' })

  const statusCalls = calls(fetch).filter((call) => call.url === '/api/v1/documents/1/status')
  expect(statusCalls.map((call) => call.headers['X-Guest-Token'])).toEqual(['old-token', 'new-token'])
  expect(localStorage.getItem('bayanuk.guestToken')).toBe('new-token')
})

it('sends no guest token on a request that is not about documents', async () => {
  localStorage.setItem('bayanuk.guestToken', 'guest-1')
  const fetch = fakeApi({ '/api/v1/me': [Response.json({ id: 'user-1' })] })

  await request('/me')

  expect(calls(fetch)[0].headers['X-Guest-Token']).toBeUndefined()
})

it('sends the stored guest token on a sign-in request, to claim the guest documents', async () => {
  localStorage.setItem('bayanuk.guestToken', 'guest-1')
  document.cookie = 'XSRF-TOKEN=csrf-1; path=/'
  const fetch = fakeApi({ '/api/v1/auth/magic-link/verify': [Response.json({ user: {} })] })

  await request('/auth/magic-link/verify', { method: 'POST' }, { guestToken: 'claim' })

  expect(calls(fetch)[0].headers['X-Guest-Token']).toBe('guest-1')
})

it('never asks for a new guest token on a sign-in request', async () => {
  document.cookie = 'XSRF-TOKEN=csrf-1; path=/'
  const fetch = fakeApi({ '/api/v1/auth/magic-link/verify': [Response.json({ user: {} })] })

  await request('/auth/magic-link/verify', { method: 'POST' }, { guestToken: 'claim' })

  expect(calls(fetch).map((call) => call.url)).toEqual(['/api/v1/auth/magic-link/verify'])
  expect(calls(fetch)[0].headers['X-Guest-Token']).toBeUndefined()
})

it('repeats a sign-in request without the guest token when the backend does not accept it', async () => {
  localStorage.setItem('bayanuk.guestToken', 'old-token')
  document.cookie = 'XSRF-TOKEN=csrf-1; path=/'
  const fetch = fakeApi({
    '/api/v1/auth/magic-link/verify': [
      Response.json({ error: { code: 'invalid_guest_token', message: '' } }, { status: 400 }),
      Response.json({ user: {} }),
    ],
  })

  await request('/auth/magic-link/verify', { method: 'POST' }, { guestToken: 'claim' })

  expect(calls(fetch).map((call) => call.headers['X-Guest-Token'])).toEqual(['old-token', undefined])
  expect(localStorage.getItem('bayanuk.guestToken')).toBeNull()
})

it('reads an answer with no body, such as 204', async () => {
  document.cookie = 'XSRF-TOKEN=csrf-1; path=/'
  fakeApi({ '/api/v1/auth/logout': [new Response(null, { status: 204 })] })

  await expect(request('/auth/logout', { method: 'POST' })).resolves.toBeUndefined()
})

it('turns an error answer into an ApiError with its status, code and message', async () => {
  localStorage.setItem('bayanuk.guestToken', 'guest-1')
  fakeApi({
    '/api/v1/documents/1/status': [
      Response.json({ error: { code: 'document_not_found', message: 'Not found.' } }, { status: 404 }),
    ],
  })

  const error = await request('/documents/1/status').catch((caught: unknown) => caught)

  expect(error).toBeInstanceOf(ApiError)
  expect(error).toMatchObject({ status: 404, code: 'document_not_found', message: 'Not found.' })
})
