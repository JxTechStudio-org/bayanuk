import { screen } from '@testing-library/react'
import { StrictMode } from 'react'
import { Route, Routes } from 'react-router-dom'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { renderWithProviders } from '@/test/renderWithProviders'
import { VerifyLinkPage } from './VerifyLinkPage'

const user = { id: 'u1', name: null, email: 'sara@example.com', locale: 'ar', keep_documents: false }

// The verify request answers with `answer`. It returns the fake fetch to read what was sent.
function verifyAnswers(answer: () => Response | Promise<Response>) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
    if (String(input).startsWith('/sanctum')) return new Response(null, { status: 204 })
    return answer()
  })
}

const verifyCalls = (fetch: ReturnType<typeof verifyAnswers>) =>
  fetch.mock.calls.filter(([input]) => input === '/api/v1/auth/magic-link/verify')

function renderPage(path: string) {
  renderWithProviders(
    <StrictMode>
      <Routes>
        <Route path="/auth/verify" element={<VerifyLinkPage />} />
        <Route path="/d/:id/result" element={<p>result page</p>} />
      </Routes>
    </StrictMode>,
    { path },
  )
}

beforeEach(() => {
  // The guest uploaded a document, then asked for a link from its result page.
  localStorage.setItem('bayanuk.returnTo', '/d/1/result')
})

afterEach(() => {
  vi.restoreAllMocks()
})

it('signs in once with POST, claims the guest documents, and returns to the saved page', async () => {
  const fetch = verifyAnswers(() => Response.json({ user }))
  renderPage('/auth/verify?token=abc')

  expect(await screen.findByText('result page')).toBeInTheDocument()
  const calls = verifyCalls(fetch)
  // Sent once, even though React runs effects twice in development.
  expect(calls).toHaveLength(1)
  const [, init] = calls[0]
  expect(init?.method).toBe('POST')
  expect(JSON.parse(String(init?.body))).toEqual({ token: 'abc' })
  expect((init?.headers as Record<string, string>)['X-Guest-Token']).toBe('test-guest-token')
  // The documents now belong to the account, so the guest token is deleted.
  expect(localStorage.getItem('bayanuk.guestToken')).toBeNull()
})

it.each([
  ['link_expired', 410, ar.auth.expired.title],
  ['link_used', 410, ar.auth.used.title],
  ['link_invalid', 400, ar.auth.invalid.title],
])('explains a link that cannot be used (%s), with a way to get a new one', async (code, status, title) => {
  verifyAnswers(() => Response.json({ error: { code, message: '' } }, { status }))
  renderPage('/auth/verify?token=abc')

  expect(await screen.findByRole('heading', { name: title })).toBeInTheDocument()
  expect(screen.getByRole('link', { name: ar.auth.newLink })).toHaveAttribute(
    'href',
    '/sign-in?next=%2Fd%2F1%2Fresult',
  )
})

it('treats a page with no token as a link that is not valid, and sends nothing', async () => {
  const fetch = verifyAnswers(() => Response.json({ user }))
  renderPage('/auth/verify')

  expect(await screen.findByRole('heading', { name: ar.auth.invalid.title })).toBeInTheDocument()
  expect(verifyCalls(fetch)).toHaveLength(0)
})

it('offers to try again when the connection fails', async () => {
  verifyAnswers(() => Promise.reject(new TypeError('Failed to fetch')))
  renderPage('/auth/verify?token=abc')

  expect(await screen.findByRole('heading', { name: ar.auth.failed.title })).toBeInTheDocument()
  expect(screen.getByRole('button', { name: ar.auth.tryAgain })).toBeInTheDocument()
})
