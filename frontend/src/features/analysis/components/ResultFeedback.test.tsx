import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, expect, it, vi } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { renderWithProviders } from '@/test/renderWithProviders'
import { ResultFeedback } from './ResultFeedback'

// The visitor is a guest. The feedback request answers with this status (201 has no body).
function feedbackAnswers(status: number) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
    const url = String(input)
    if (url === '/api/v1/me') return Response.json({ error: { code: 'unauthorized', message: '' } }, { status: 401 })
    if (url === '/api/v1/analyses/a1/feedback') {
      return status === 201
        ? new Response(null, { status })
        : Response.json({ error: { code: 'server_error', message: '' } }, { status })
    }
    return new Response(null, { status: 204 })
  })
}

afterEach(() => {
  vi.restoreAllMocks()
})

it('sends "not helpful" as -1 and says thank you', async () => {
  const user = userEvent.setup()
  const fetch = feedbackAnswers(201)
  renderWithProviders(<ResultFeedback analysisId="a1" />)
  await user.click(screen.getByRole('button', { name: ar.result.feedback.notHelpful }))

  expect(await screen.findByRole('status')).toHaveTextContent(ar.result.feedback.thanks)
  const [, init] = fetch.mock.calls.find(([url]) => url === '/api/v1/analyses/a1/feedback') ?? []
  expect(JSON.parse(String(init?.body))).toEqual({ rating: -1 })
  // A guest sends the guest token (test/setup.ts stores one).
  expect(new Headers(init?.headers).get('X-Guest-Token')).toBe('test-guest-token')
})

it('keeps the buttons with a message when sending fails', async () => {
  const user = userEvent.setup()
  feedbackAnswers(500)
  renderWithProviders(<ResultFeedback analysisId="a1" />)
  await user.click(screen.getByRole('button', { name: ar.result.feedback.helpful }))

  expect(await screen.findByRole('alert')).toHaveTextContent(ar.result.feedback.error)
  expect(screen.getByRole('button', { name: ar.result.feedback.helpful })).toBeEnabled()
})
