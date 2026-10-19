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
