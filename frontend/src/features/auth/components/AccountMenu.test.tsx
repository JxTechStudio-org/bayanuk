import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { fakeSession } from '@/test/fakeUser'
import { renderWithProviders } from '@/test/renderWithProviders'
import { AccountMenu } from './AccountMenu'

function renderMenu() {
  renderWithProviders(
    <Routes>
      <Route path="/" element={<p>home page</p>} />
      <Route path="/d/:id/result" element={<AccountMenu />} />
    </Routes>,
    { path: '/d/1/result' },
  )
}

afterEach(() => {
  vi.restoreAllMocks()
})

it('offers a guest to sign in, and to come back to this page', async () => {
  fakeSession(false)
  renderMenu()
  expect(await screen.findByRole('link', { name: ar.account.signIn })).toHaveAttribute(
    'href',
    '/sign-in?next=%2Fd%2F1%2Fresult',
  )
})

it('shows the signed-in user, My documents and Sign out', async () => {
  fakeSession(true)
  const user = userEvent.setup()
  renderMenu()

  await user.click(await screen.findByRole('button', { name: ar.account.menu }))
  expect(screen.getByRole('menuitem', { name: ar.account.documents })).toHaveAttribute('href', '/documents')
  expect(screen.getByRole('menuitem', { name: ar.account.signOut })).toBeInTheDocument()
})

it('signs out and goes to the home page', async () => {
  const fetch = fakeSession(true)
  const user = userEvent.setup()
  renderMenu()

  await user.click(await screen.findByRole('button', { name: ar.account.menu }))
  await user.click(screen.getByRole('menuitem', { name: ar.account.signOut }))

  expect(await screen.findByText('home page')).toBeInTheDocument()
  expect(fetch.mock.calls.some(([input, init]) => input === '/api/v1/auth/logout' && init?.method === 'POST')).toBe(true)
})
