import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { expect, it, vi } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { UploadEntry } from './UploadEntry'

it('hands the chosen file over', async () => {
  const user = userEvent.setup()
  const onSelect = vi.fn()
  const { container } = render(<UploadEntry onSelect={onSelect} />)

  expect(screen.getByRole('button', { name: ar.upload.choose })).toBeInTheDocument()

  const file = new File(['%PDF'], 'offer.pdf', { type: 'application/pdf' })
  await user.upload(container.querySelector('input[type="file"]')!, file)

  expect(onSelect).toHaveBeenCalledWith(file)
})
