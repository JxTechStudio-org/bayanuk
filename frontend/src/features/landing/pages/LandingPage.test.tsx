import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { expect, it } from 'vitest'
import '@/app/i18n'
import { LandingPage } from './LandingPage'

it('starts in Arabic, right to left', () => {
  // The header has links, and links need a router around them.
  render(
    <MemoryRouter>
      <LandingPage />
    </MemoryRouter>,
  )

  expect(screen.getByRole('heading', { name: 'بيانك' })).toBeInTheDocument()
  expect(document.documentElement.dir).toBe('rtl')
  expect(document.documentElement.lang).toBe('ar')
})
