import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { renderWithProviders } from '@/test/renderWithProviders'
import type { DocumentResult } from '../types'
import { ResultPage } from './ResultPage'

const READY: DocumentResult = {
  id: '123',
  status: 'ready',
  document_type: 'employment_contract',
  language: 'ar',
  page_count: 9,
  expires_at: '2026-11-20T10:00:00Z',
  analysis: { id: 'a1', summary: '...', summary_language: 'ar', terms: [] },
}

// The visitor is a guest (GET /me answers 401), and GET /documents/123 answers with these bodies and statuses,
// one per call (the last one repeats).
function apiAnswers(...answers: { body: unknown; status?: number }[]) {
  let call = 0
  vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
    if (String(input) === '/api/v1/me') {
      return Response.json({ error: { code: 'unauthorized', message: '' } }, { status: 401 })
    }
    const { body, status = 200 } = answers[Math.min(call++, answers.length - 1)]
    return Response.json(body, { status })
  })
}

function renderPage() {
  renderWithProviders(
    <Routes>
      <Route path="/d/:id/result" element={<ResultPage />} />
      <Route path="/d/:id" element={<p>processing page</p>} />
    </Routes>,
    { path: '/d/123/result' },
  )
}

afterEach(() => {
  vi.restoreAllMocks()
})

it('says the analysis is loading, then shows it', async () => {
  apiAnswers({ body: READY })
  renderPage()
  expect(screen.getByRole('status')).toHaveTextContent(ar.result.loading)
  expect(await screen.findByRole('heading', { name: ar.result.title })).toBeInTheDocument()
})

it('says the document was not found (404)', async () => {
  apiAnswers({ body: { error: { code: 'document_not_found', message: '' } }, status: 404 })
  renderPage()
  expect(await screen.findByRole('alert')).toHaveTextContent(ar.processing.notFound.title)
  expect(screen.getByRole('link', { name: ar.processing.notFound.action })).toHaveAttribute('href', '/')
})

it('says the document was deleted (410)', async () => {
  apiAnswers({ body: { error: { code: 'document_expired', message: '' } }, status: 410 })
  renderPage()
  expect(await screen.findByRole('alert')).toHaveTextContent(ar.processing.expired.title)
})

it('can load the result again after a server error', async () => {
  // The hook tries a server error once more by itself, so the first two answers fail.
  const serverError = { body: { error: { code: 'server_error', message: '' } }, status: 500 }
  apiAnswers(serverError, serverError, { body: READY })
  renderPage()
  expect(await screen.findByRole('alert')).toHaveTextContent(ar.result.error)
  await userEvent.click(screen.getByRole('button', { name: ar.result.retry }))
  expect(await screen.findByRole('heading', { name: ar.result.title })).toBeInTheDocument()
})

it('goes back to the processing page when the document is not ready yet', async () => {
  apiAnswers({ body: { ...READY, status: 'analyzing', analysis: null } })
  renderPage()
  expect(await screen.findByText('processing page')).toBeInTheDocument()
})
