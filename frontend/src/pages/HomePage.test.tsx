import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { expect, it } from 'vitest'
import '@/app/i18n'
import { HomePage } from './HomePage'

it('starts in Arabic, right to left', () => {
  render(<HomePage />)

  expect(screen.getByRole('heading', { name: 'بيانك' })).toBeInTheDocument()
  expect(document.documentElement.dir).toBe('rtl')
  expect(document.documentElement.lang).toBe('ar')
})

it('switches to English, left to right, and back', async () => {
  const user = userEvent.setup()
  render(<HomePage />)

  await user.click(screen.getByRole('button', { name: 'English' }))

  expect(screen.getByRole('heading', { name: 'Bayanuk' })).toBeInTheDocument()
  expect(document.documentElement.dir).toBe('ltr')
  expect(document.title).toBe('Bayanuk')

  await user.click(screen.getByRole('button', { name: 'العربية' }))

  expect(document.documentElement.dir).toBe('rtl')
  expect(document.title).toBe('بيانك')
})