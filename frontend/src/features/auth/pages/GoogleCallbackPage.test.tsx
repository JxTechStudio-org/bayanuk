import { screen } from '@testing-library/react'
import { Route, Routes } from 'react-router-dom'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { renderWithProviders } from '@/test/renderWithProviders'
import { GoogleCallbackPage } from './GoogleCallbackPage'

function meAnswers(answer: () => Response) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation(async () => answer())
}

function renderPage(path: string) {
  renderWithProviders(
    <Routes>
      <Route path="/auth/callback" element={<GoogleCallbackPage />} />
      <Route path="/d/:id/result" element={<p>result page</p>} />
    </Routes>,
    { path },
  )
}

beforeEach(() => {
  localStorage.setItem('bayanuk.returnTo', '/d/1/result')
})

afterEach(() => {
  vi.restoreAllMocks()
})

it('loads the user, deletes the guest token, and returns to the saved page', async () => {
  meAnswers(() => Response.json({ id: 'u1', name: null, email: 'sara@example.com', locale: 'ar', keep_documents: false }))
  renderPage('/auth/callback')

  expect(await screen.findByText('result page')).toBeInTheDocument()
  expect(localStorage.getItem('bayanuk.guestToken')).toBeNull()
})

it('says sign-in was not completed when the user cancelled on Google', async () => {
  meAnswers(() => Response.json({ error: { code: 'unauthorized', message: '' } }, { status: 401 }))
  renderPage('/auth/callback?error=google_cancelled')

  expect(await screen.findByRole('heading', { name: ar.auth.googleCancelled.title })).toBeInTheDocument()
  expect(screen.getByRole('link', { name: ar.auth.tryAgain })).toHaveAttribute('href', '/sign-in?next=%2Fd%2F1%2Fresult')
  // Not signed in, so the guest keeps the token and the documents.
  expect(localStorage.getItem('bayanuk.guestToken')).toBe('test-guest-token')
})

it.each(['google_invalid_state', 'google_sign_in_failed'])('explains a failed Google sign-in (%s)', async (error) => {
  meAnswers(() => Response.json({}))
  renderPage(`/auth/callback?error=${error}`)
  expect(await screen.findByRole('heading', { name: ar.auth.googleFailed.title })).toBeInTheDocument()
})

it('explains a failed sign-in when there is no session after Google', async () => {
  meAnswers(() => Response.json({ error: { code: 'unauthorized', message: '' } }, { status: 401 }))
  renderPage('/auth/callback')
  expect(await screen.findByRole('heading', { name: ar.auth.googleFailed.title })).toBeInTheDocument()
  // The guest token stays: the documents did not move to an account.
  expect(localStorage.getItem('bayanuk.guestToken')).toBe('test-guest-token')
})
