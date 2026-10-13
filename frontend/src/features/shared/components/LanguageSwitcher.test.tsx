import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { expect, it } from 'vitest'
import '@/app/i18n'
import { LanguageSwitcher } from './LanguageSwitcher'

it('switches to English, left to right, and back', async () => {
  const user = userEvent.setup()
  render(<LanguageSwitcher />)

  await user.click(screen.getByRole('button', { name: 'English' }))

  expect(document.documentElement.dir).toBe('ltr')
  expect(document.documentElement.lang).toBe('en')
  expect(document.title).toBe('Bayanuk')

  await user.click(screen.getByRole('button', { name: 'العربية' }))

  expect(document.documentElement.dir).toBe('rtl')
  expect(document.title).toBe('بيانك')
})