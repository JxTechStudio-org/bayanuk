import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, expect, it, vi } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { fakeSession, signedInUser } from '@/test/fakeUser'
import { renderWithProviders } from '@/test/renderWithProviders'
import { UploadEntry } from './UploadEntry'

// GET /me answers that the visitor is a guest.
const guest = () => Response.json({ error: { code: 'unauthorized', message: '' } }, { status: 401 })

afterEach(() => {
  vi.restoreAllMocks()
})

it('explains why a file is refused, before sending it', async () => {
  // applyAccept: false lets the test pick a file the picker would normally hide.
  const user = userEvent.setup({ applyAccept: false })
  const { container } = renderWithProviders(<UploadEntry />)

  expect(screen.getByRole('button', { name: ar.upload.choose })).toBeInTheDocument()

  const photo = new File(['x'], 'photo.jpg', { type: 'image/jpeg' })
  await user.upload(container.querySelector('input[type="file"]')!, photo)

  expect(screen.getByRole('alert')).toHaveTextContent(ar.upload.errors.type)
})

it('sends one file at a time', async () => {
  // The visitor is a guest and the CSRF cookie arrives, but the upload never answers,
  // so the first upload stays in progress.
  const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = String(input)
    if (url === '/api/v1/me') return Promise.resolve(guest())
    if (url.startsWith('/sanctum')) return Promise.resolve(new Response(null, { status: 204 }))
    return new Promise(() => {})
  })
  const uploads = () => fetch.mock.calls.filter(([input]) => input === '/api/v1/documents').length
  const user = userEvent.setup()
  const { container } = renderWithProviders(<UploadEntry />)
  const input = container.querySelector<HTMLInputElement>('input[type="file"]')!

  await user.upload(input, new File(['%PDF'], 'offer.pdf', { type: 'application/pdf' }))
  expect(await screen.findByRole('button', { name: ar.upload.uploading })).toBeDisabled()

  await user.upload(input, new File(['%PDF'], 'second.pdf', { type: 'application/pdf' }))
  expect(uploads()).toBe(1)
})

it('explains a file that is too large, before sending it', async () => {
  const fetch = fakeSession(false)
  const user = userEvent.setup()
  const { container } = renderWithProviders(<UploadEntry />)

  const big = new File(['x'], 'offer.pdf', { type: 'application/pdf' })
  Object.defineProperty(big, 'size', { value: 11 * 1024 * 1024 })
  await user.upload(container.querySelector('input[type="file"]')!, big)

  expect(screen.getByRole('alert')).toHaveTextContent(ar.upload.errors.size)
  expect(fetch.mock.calls.some(([input]) => input === '/api/v1/documents')).toBe(false)
})

// Integration contract, section 4: the upload errors, with the message the API sends in the interface language.
it.each([
  [422, 'file_type_not_supported', 'نوع الملف غير مدعوم.'],
  [422, 'file_too_large', 'حجم الملف يتجاوز الحد المسموح به (10 ميجابايت).'],
  [429, 'upload_limit_reached', 'وصلت إلى الحد اليومي للرفع.'],
  [429, 'ai_budget_reached', 'توقفت التحليلات الجديدة لهذا اليوم.'],
])('shows the API message for a %i %s answer', async (status, code, message) => {
  vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
    const url = String(input)
    if (url === '/api/v1/me') return guest()
    if (url.startsWith('/sanctum')) return new Response(null, { status: 204 })
    return Response.json({ error: { code, message } }, { status })
  })
  const user = userEvent.setup()
  const { container } = renderWithProviders(<UploadEntry />)

  await user.upload(
    container.querySelector('input[type="file"]')!,
    new File(['%PDF'], 'offer.pdf', { type: 'application/pdf' }),
  )

  expect(await screen.findByRole('alert')).toHaveTextContent(message)
})

it('shows our own message when the API cannot be reached', async () => {
  vi.spyOn(globalThis, 'fetch').mockRejectedValue(new TypeError('Failed to fetch'))
  const user = userEvent.setup()
  const { container } = renderWithProviders(<UploadEntry />)

  await user.upload(
    container.querySelector('input[type="file"]')!,
    new File(['%PDF'], 'offer.pdf', { type: 'application/pdf' }),
  )

  expect(await screen.findByRole('alert')).toHaveTextContent(ar.upload.errors.generic)
})

// Integration contract, section 5: a signed-in user sends no guest token, even when the file is chosen
// before the app knows who is signed in.
it('waits to know who is signed in, and sends no guest token for a signed-in user', async () => {
  let answerMe: (response: Response) => void = () => {}
  const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = String(input)
    if (url === '/api/v1/me') return new Promise((resolve) => (answerMe = resolve))
    if (url.startsWith('/sanctum')) return Promise.resolve(new Response(null, { status: 204 }))
    return Promise.resolve(Response.json({ id: '1', status: 'uploaded' }, { status: 202 }))
  })
  const user = userEvent.setup()
  const { container } = renderWithProviders(<UploadEntry />)

  await user.upload(
    container.querySelector('input[type="file"]')!,
    new File(['%PDF'], 'offer.pdf', { type: 'application/pdf' }),
  )
  const uploads = () => fetch.mock.calls.filter(([input]) => input === '/api/v1/documents')
  expect(uploads()).toHaveLength(0)

  answerMe(Response.json(signedInUser))
  await waitFor(() => expect(uploads()).toHaveLength(1))
  const [, init] = uploads()[0]
  expect((init?.headers as Record<string, string>)['X-Guest-Token']).toBeUndefined()
})
