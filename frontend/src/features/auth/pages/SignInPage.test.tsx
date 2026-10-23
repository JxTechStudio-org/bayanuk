import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { renderWithProviders } from '@/test/renderWithProviders'
import { SignInPage } from './SignInPage'

const guest = () => Response.json({ error: { code: 'unauthorized', message: '' } }, { status: 401 })

// The visitor is a guest. The sign-in link request answers with `linkAnswer` each time.
function fakeApi(linkAnswer: () => Response = () => new Response(null, { status: 202 })) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
    const url = String(input)
    if (url === '/api/v1/me') return guest()
    if (url.startsWith('/sanctum')) return new Response(null, { status: 204 })
    if (url === '/api/v1/auth/magic-link') return linkAnswer()
    throw new Error(`No answer for ${url}`)
  })
}

function linkRequests(fetch: ReturnType<typeof fakeApi>) {
  return fetch.mock.calls
    .filter(([input]) => input === '/api/v1/auth/magic-link')
    .map(([, init]) => JSON.parse(String(init?.body)))
}

function renderPage() {
  renderWithProviders(
    <Routes>
      <Route path="/sign-in" element={<SignInPage />} />
      <Route path="/d/:id/result" element={<p>result page</p>} />
    </Routes>,
    { path: '/sign-in?next=/d/1/result' },
  )
}

afterEach(() => {
  vi.restoreAllMocks()
})

it('offers Google and the email link, and shows the terms consent', async () => {
  fakeApi()
  renderPage()
  expect(await screen.findByRole('heading', { name: ar.signIn.title })).toBeInTheDocument()
  expect(screen.getByRole('button', { name: ar.signIn.google })).toBeInTheDocument()
  expect(screen.getByLabelText(ar.signIn.email)).toBeInTheDocument()
  expect(screen.getByText(ar.signIn.consent)).toBeInTheDocument()
})

it('does not send an email that is not valid', async () => {
  const fetch = fakeApi()
  const user = userEvent.setup()
  renderPage()

  await user.type(await screen.findByLabelText(ar.signIn.email), 'sara@')
  await user.click(screen.getByRole('button', { name: ar.signIn.send }))

  expect(screen.getByRole('alert')).toHaveTextContent(ar.signIn.errors.email)
  expect(linkRequests(fetch)).toHaveLength(0)
})

it('sends the link, then says to check the email', async () => {
  const fetch = fakeApi()
  const user = userEvent.setup()
  renderPage()

  await user.type(await screen.findByLabelText(ar.signIn.email), 'sara@example.com')
  await user.click(screen.getByRole('button', { name: ar.signIn.send }))

  expect(await screen.findByRole('heading', { name: ar.signIn.checkEmail.title })).toBeInTheDocument()
  expect(screen.getByText('sara@example.com')).toBeInTheDocument()
  expect(linkRequests(fetch)).toEqual([{ email: 'sara@example.com' }])
  // The page to return to after sign-in is kept for when the link is opened.
  expect(localStorage.getItem('bayanuk.returnTo')).toBe('/d/1/result')
})

it('sends the link again', async () => {
  const fetch = fakeApi()
  const user = userEvent.setup()
  renderPage()

  await user.type(await screen.findByLabelText(ar.signIn.email), 'sara@example.com')
  await user.click(screen.getByRole('button', { name: ar.signIn.send }))
  await user.click(await screen.findByRole('button', { name: ar.signIn.checkEmail.resend }))

  expect(await screen.findByRole('status')).toHaveTextContent(ar.signIn.checkEmail.resent)
  expect(linkRequests(fetch)).toHaveLength(2)
})

it('shows the message from the API when too many links were asked for', async () => {
  fakeApi(() =>
    Response.json({ error: { code: 'magic_link_limit_reached', message: 'Too many requests.' } }, { status: 429 }),
  )
  const user = userEvent.setup()
  renderPage()

  await user.type(await screen.findByLabelText(ar.signIn.email), 'sara@example.com')
  await user.click(screen.getByRole('button', { name: ar.signIn.send }))

  expect(await screen.findByRole('alert')).toHaveTextContent('Too many requests.')
})

it('shows the field message when the API refuses the email (validation_error)', async () => {
  fakeApi(() =>
    Response.json(
      {
        error: {
          code: 'validation_error',
          message: 'Please check the highlighted fields.',
          details: { email: ['Enter a valid email address.'] },
        },
      },
      { status: 422 },
    ),
  )
  const user = userEvent.setup()
  renderPage()

  await user.type(await screen.findByLabelText(ar.signIn.email), 'sara@example.com')
  await user.click(screen.getByRole('button', { name: ar.signIn.send }))

  expect(await screen.findByRole('alert')).toHaveTextContent('Enter a valid email address.')
  expect(screen.getByLabelText(ar.signIn.email)).toHaveAttribute('aria-invalid', 'true')
})

it('sends a signed-in user straight to the next page', async () => {
  vi.spyOn(globalThis, 'fetch').mockImplementation(async () =>
    Response.json({ id: 'u1', name: null, email: 'sara@example.com', locale: 'ar', keep_documents: false }),
  )
  renderPage()
  expect(await screen.findByText('result page')).toBeInTheDocument()
})
