import { screen } from '@testing-library/react'
import { Route, Routes, useLocation } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { fakeSession } from '@/test/fakeUser'
import { renderWithProviders } from '@/test/renderWithProviders'
import { RequireSignIn } from './RequireSignIn'

function SignInStub() {
  const { search } = useLocation()
  return <p>sign-in page {search}</p>
}

function renderPage() {
  renderWithProviders(
    <Routes>
      <Route path="/sign-in" element={<SignInStub />} />
      <Route
        path="/documents"
        element={
          <RequireSignIn>
            <p>my documents</p>
          </RequireSignIn>
        }
      />
    </Routes>,
    { path: '/documents' },
  )
}

afterEach(() => {
  vi.restoreAllMocks()
})

it('sends a guest to sign in, to come back to the page afterwards', async () => {
  fakeSession(false)
  renderPage()
  expect(await screen.findByText('sign-in page ?next=%2Fdocuments')).toBeInTheDocument()
})

it('shows the page to a signed-in user', async () => {
  fakeSession(true)
  renderPage()
  expect(await screen.findByText('my documents')).toBeInTheDocument()
})
