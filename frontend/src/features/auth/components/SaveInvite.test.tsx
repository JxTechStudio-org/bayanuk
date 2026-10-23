import { screen } from '@testing-library/react'
import { afterEach, expect, it, vi } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { fakeSession } from '@/test/fakeUser'
import { renderWithProviders } from '@/test/renderWithProviders'
import { SaveInvite } from './SaveInvite'

afterEach(() => {
  vi.restoreAllMocks()
})

it('invites a guest to sign in to keep the analysis', async () => {
  fakeSession(false)
  renderWithProviders(<SaveInvite />, { path: '/d/1/result' })
  expect(await screen.findByText(ar.account.invite.title)).toBeInTheDocument()
  expect(screen.getByRole('link', { name: ar.account.signIn })).toHaveAttribute('href', '/sign-in?next=%2Fd%2F1%2Fresult')
})

it('does not show the invitation to a signed-in user', async () => {
  const fetch = fakeSession(true)
  renderWithProviders(<SaveInvite />)
  await vi.waitFor(() => expect(fetch).toHaveBeenCalled())
  // Give the answer time to arrive, then check nothing appeared.
  await new Promise((resolve) => setTimeout(resolve, 50))
  expect(screen.queryByText(ar.account.invite.title)).not.toBeInTheDocument()
})
