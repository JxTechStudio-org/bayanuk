import { QueryClientProvider } from '@tanstack/react-query'
import { createBrowserRouter, RouterProvider } from 'react-router-dom'
import { ProcessingPage } from '@/features/analysis/pages/ProcessingPage'
import { ResultPage } from '@/features/analysis/pages/ResultPage'
import { GoogleCallbackPage } from '@/features/auth/pages/GoogleCallbackPage'
import { SignInPage } from '@/features/auth/pages/SignInPage'
import { VerifyLinkPage } from '@/features/auth/pages/VerifyLinkPage'
import { LandingPage } from '@/features/landing/pages/LandingPage'
import { queryClient } from './queryClient'

// Every page of the site is listed here: path -> page.
const router = createBrowserRouter([
  { path: '/', element: <LandingPage /> },
  { path: '/d/:id', element: <ProcessingPage /> },
  { path: '/d/:id/result', element: <ResultPage /> },
  { path: '/sign-in', element: <SignInPage /> },
  { path: '/auth/verify', element: <VerifyLinkPage /> },
  { path: '/auth/callback', element: <GoogleCallbackPage /> },
])

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>
  )
}
