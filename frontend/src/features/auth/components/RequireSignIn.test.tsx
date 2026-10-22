import { useQuery } from '@tanstack/react-query'
import { screen } from '@testing-library/react'
import { Route, Routes, useLocation } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { request } from '@/lib/api'
import { fakeSession, signedInUser } from '@/test/fakeUser'
import { renderWithProviders } from '@/test/renderWithProviders'
import { RequireSignIn } from './RequireSignIn'

function SignInStub() {
  const { search } = useLocation()
  return <p>sign-in page {search}</p>
}

// A page that needs an account and loads data from the API.
function DocumentsStub() {
  const documents = useQuery({ queryKey: ['documents'], queryFn: () => request('/documents') })
  return <p>{documents.isSuccess ? 'my documents' : 'loading'}</p>
}

function renderPage() {
  renderWithProviders(
    <Routes>
      <Route path="/sign-in" element={<SignInStub />} />
      <Route
        path="/documents"
        element={
          <RequireSignIn>
            <DocumentsStub />
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
  vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) =>
    Response.json(String(input) === '/api/v1/me' ? signedInUser : { data: [] }),
  )
  renderPage()
  expect(await screen.findByText('my documents')).toBeInTheDocument()
})

it('sends the user to sign in when the session ends (401), to come back afterwards', async () => {
  vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) =>
    String(input) === '/api/v1/me'
      ? Response.json(signedInUser)
      : Response.json({ error: { code: 'unauthorized', message: '' } }, { status: 401 }),
  )
  renderPage()
  expect(await screen.findByText('sign-in page ?next=%2Fdocuments')).toBeInTheDocument()
})
