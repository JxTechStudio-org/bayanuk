import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { expect, it } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { renderWithProviders } from '@/test/renderWithProviders'
import { UploadEntry } from './UploadEntry'

it('explains why a file is refused, before sending it', async () => {
  // applyAccept: false lets the test pick a file the picker would normally hide.
  const user = userEvent.setup({ applyAccept: false })
  const { container } = renderWithProviders(<UploadEntry />)

  expect(screen.getByRole('button', { name: ar.upload.choose })).toBeInTheDocument()

  const photo = new File(['x'], 'photo.jpg', { type: 'image/jpeg' })
  await user.upload(container.querySelector('input[type="file"]')!, photo)

  expect(screen.getByRole('alert')).toHaveTextContent(ar.upload.errors.type)
})
