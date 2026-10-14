import { screen } from '@testing-library/react'
import { expect, it } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { renderWithProviders } from '@/test/renderWithProviders'
import { LandingPage } from './LandingPage'

it('starts in Arabic, right to left', () => {
  renderWithProviders(<LandingPage />)

  expect(screen.getByRole('heading', { name: ar.hero.title })).toBeInTheDocument()
  expect(document.documentElement.dir).toBe('rtl')
  expect(document.documentElement.lang).toBe('ar')
})
