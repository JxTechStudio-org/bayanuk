import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { expect, it } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { FAQ } from './FAQ'

it('opens one answer at a time', async () => {
  const user = userEvent.setup()
  render(<FAQ />)

  // The first answer is open when the page loads.
  expect(screen.getByText(ar.faq.items.legal.answer)).toBeVisible()

  await user.click(screen.getByRole('button', { name: ar.faq.items.account.question }))

  expect(screen.getByText(ar.faq.items.account.answer)).toBeVisible()
  expect(screen.queryByText(ar.faq.items.legal.answer)).not.toBeInTheDocument()
})
