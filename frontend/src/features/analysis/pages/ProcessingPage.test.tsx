import { screen, waitFor } from '@testing-library/react'
import { Route, Routes } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { renderWithProviders } from '@/test/renderWithProviders'
import { ProcessingPage } from './ProcessingPage'

// The visitor is a guest (GET /me answers 401), and the status check answers with this body and status.
function apiAnswers(body: unknown, status = 200) {
  vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) =>
    String(input) === '/api/v1/me'
      ? Response.json({ error: { code: 'unauthorized', message: '' } }, { status: 401 })
      : Response.json(body, { status }),
  )
}

function renderPage() {
  renderWithProviders(
    <Routes>
      <Route path="/d/:id" element={<ProcessingPage />} />
      <Route path="/d/:id/result" element={<p>result page</p>} />
    </Routes>,
    { path: '/d/123' },
  )
}

afterEach(() => {
  vi.restoreAllMocks()
})

it('shows the stages while the document is processed', async () => {
  apiAnswers({ id: '123', status: 'extracting', status_detail: null })
  renderPage()
  // extracting is the second stage, "Reading document".
  await waitFor(() => {
    expect(screen.getByText(ar.processing.stages.reading).closest('li')).toHaveAttribute('aria-current', 'step')
  })
})

it('opens the result when the document is ready', async () => {
  apiAnswers({ id: '123', status: 'ready', status_detail: null })
  renderPage()
  expect(await screen.findByText('result page')).toBeInTheDocument()
})

it('explains a rejected document with the message for its reason code', async () => {
  apiAnswers({ id: '123', status: 'rejected', status_detail: 'too_many_pages' })
  renderPage()
  expect(await screen.findByRole('heading', { name: ar.processing.rejected.title })).toBeInTheDocument()
  expect(screen.getByText(ar.processing.reasons.too_many_pages)).toBeInTheDocument()
})

it('explains a failed document with the message for its reason code', async () => {
  apiAnswers({ id: '123', status: 'failed', status_detail: 'file_unreadable' })
  renderPage()
  expect(await screen.findByRole('heading', { name: ar.processing.failed.title })).toBeInTheDocument()
  expect(screen.getByText(ar.processing.reasons.file_unreadable)).toBeInTheDocument()
})

it('shows the title only for a reason code it does not know', async () => {
  apiAnswers({ id: '123', status: 'failed', status_detail: 'a_new_reason' })
  renderPage()
  expect(await screen.findByRole('heading', { name: ar.processing.failed.title })).toBeInTheDocument()
  expect(screen.queryByText(/a_new_reason/)).not.toBeInTheDocument()
})

it('says when the document does not exist', async () => {
  apiAnswers({ error: { code: 'document_not_found', message: '' } }, 404)
  renderPage()
  expect(await screen.findByRole('heading', { name: ar.processing.notFound.title })).toBeInTheDocument()
})

it('says when the document was deleted after the retention period', async () => {
  apiAnswers({ error: { code: 'document_expired', message: '' } }, 410)
  renderPage()
  expect(await screen.findByRole('heading', { name: ar.processing.expired.title })).toBeInTheDocument()
})

it('keeps the stages on screen when the connection drops', async () => {
  const fetch = vi.spyOn(globalThis, 'fetch').mockRejectedValue(new TypeError('Failed to fetch'))
  renderPage()
  await waitFor(() => expect(fetch).toHaveBeenCalled())
  // No end state appears: the page keeps checking instead of saying the document does not exist.
  await expect(screen.findByRole('alert', undefined, { timeout: 500 })).rejects.toThrow()
  expect(screen.getByRole('heading', { name: ar.processing.title })).toBeInTheDocument()
})

it('says the document is in the queue while it waits', async () => {
  apiAnswers({ id: '123', status: 'uploaded', status_detail: null })
  renderPage()
  expect(await screen.findByText(ar.processing.notices.queued)).toBeInTheDocument()
})

it('says the document is paused when the daily AI budget is reached', async () => {
  apiAnswers({ id: '123', status: 'analyzing', status_detail: 'ai_budget_reached' })
  renderPage()
  expect(await screen.findByText(ar.processing.notices.paused)).toBeInTheDocument()
  // The stages stay on screen, at the stage the document reached.
  expect(screen.getByText(ar.processing.stages.findingTerms).closest('li')).toHaveAttribute('aria-current', 'step')
})

it('says the connection is lost after several failed checks, and keeps trying', async () => {
  vi.useFakeTimers({ shouldAdvanceTime: true })
  const fetch = vi.spyOn(globalThis, 'fetch').mockRejectedValue(new TypeError('Failed to fetch'))
  renderPage()

  const statusChecks = () => fetch.mock.calls.filter(([input]) => String(input).endsWith('/status')).length

  // One failed check is not enough to say the connection is lost.
  await waitFor(() => expect(statusChecks()).toBe(1))
  expect(screen.queryByText(ar.processing.notices.offline)).not.toBeInTheDocument()

  // The third failed check in a row shows the message.
  await vi.advanceTimersByTimeAsync(4000)
  expect(await screen.findByText(ar.processing.notices.offline)).toBeInTheDocument()

  // The connection comes back: the message goes away and the stages continue.
  fetch.mockImplementation(async () => Response.json({ id: '123', status: 'extracting', status_detail: null }))
  await vi.advanceTimersByTimeAsync(2000)
  await waitFor(() => expect(screen.queryByText(ar.processing.notices.offline)).not.toBeInTheDocument())
  vi.useRealTimers()
})
