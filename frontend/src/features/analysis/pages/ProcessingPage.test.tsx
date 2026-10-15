import { screen, waitFor } from '@testing-library/react'
import { Route, Routes } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { renderWithProviders } from '@/test/renderWithProviders'
import { ProcessingPage } from './ProcessingPage'

// The API answers once with this body and status.
function apiAnswers(body: unknown, status = 200) {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(Response.json(body, { status }))
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

it('explains a rejected document, with the reason from the API', async () => {
  apiAnswers({ id: '123', status: 'rejected', status_detail: 'This is a lease contract.' })
  renderPage()
  expect(await screen.findByRole('heading', { name: ar.processing.rejected.title })).toBeInTheDocument()
  expect(screen.getByText('This is a lease contract.')).toBeInTheDocument()
})

it('explains a file that could not be read', async () => {
  apiAnswers({ id: '123', status: 'failed', status_detail: null })
  renderPage()
  expect(await screen.findByRole('heading', { name: ar.processing.failed.title })).toBeInTheDocument()
})

it('says when the document does not exist', async () => {
  apiAnswers({ error: { code: 'not_found', message: '' } }, 404)
  renderPage()
  expect(await screen.findByRole('heading', { name: ar.processing.notFound.title })).toBeInTheDocument()
})

it('keeps the stages on screen when the connection drops', async () => {
  const fetch = vi.spyOn(globalThis, 'fetch').mockRejectedValue(new TypeError('Failed to fetch'))
  renderPage()
  await waitFor(() => expect(fetch).toHaveBeenCalled())
  // No end state appears: the page keeps checking instead of saying the document does not exist.
  await expect(screen.findByRole('alert', undefined, { timeout: 500 })).rejects.toThrow()
  expect(screen.getByRole('heading', { name: ar.processing.title })).toBeInTheDocument()
})
