import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, expect, it, vi } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { renderWithProviders } from '@/test/renderWithProviders'
import { UploadEntry } from './UploadEntry'

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
  // The CSRF cookie arrives, but the upload never answers, so the first upload stays in progress.
  const fetch = vi
    .spyOn(globalThis, 'fetch')
    .mockImplementation((input) =>
      String(input).startsWith('/sanctum') ? Promise.resolve(new Response(null, { status: 204 })) : new Promise(() => {}),
    )
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
  const fetch = vi.spyOn(globalThis, 'fetch')
  const user = userEvent.setup()
  const { container } = renderWithProviders(<UploadEntry />)

  const big = new File(['x'], 'offer.pdf', { type: 'application/pdf' })
  Object.defineProperty(big, 'size', { value: 11 * 1024 * 1024 })
  await user.upload(container.querySelector('input[type="file"]')!, big)

  expect(screen.getByRole('alert')).toHaveTextContent(ar.upload.errors.size)
  expect(fetch).not.toHaveBeenCalled()
})

// Integration contract, section 4: the upload errors, with the message the API sends in the interface language.
it.each([
  [422, 'file_type_not_supported', 'نوع الملف غير مدعوم.'],
  [422, 'file_too_large', 'حجم الملف يتجاوز الحد المسموح به (10 ميجابايت).'],
  [429, 'upload_limit_reached', 'وصلت للحد اليومي للرفع.'],
  [429, 'ai_budget_reached', 'توقفت التحليلات الجديدة لهذا اليوم.'],
])('shows the API message for a %i %s answer', async (status, code, message) => {
  vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) =>
    String(input).startsWith('/sanctum')
      ? new Response(null, { status: 204 })
      : Response.json({ error: { code, message } }, { status }),
  )
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
