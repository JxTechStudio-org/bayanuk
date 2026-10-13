import { QueryClientProvider } from '@tanstack/react-query'
import { createBrowserRouter, RouterProvider } from 'react-router-dom'
import { LandingPage } from '@/features/landing/pages/LandingPage'
import { queryClient } from './queryClient'

// Every page of the site is listed here: path -> page.
const router = createBrowserRouter([{ path: '/', element: <LandingPage /> }])

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>
  )
}
