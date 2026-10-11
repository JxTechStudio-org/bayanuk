import { render, screen } from '@testing-library/react'
import { expect, it } from 'vitest'
import '@/app/i18n'
import { LandingPage } from './LandingPage'

it('starts in Arabic, right to left', () => {
  render(<LandingPage />)

  expect(screen.getByRole('heading', { name: 'بيانك' })).toBeInTheDocument()
  expect(document.documentElement.dir).toBe('rtl')
  expect(document.documentElement.lang).toBe('ar')
})