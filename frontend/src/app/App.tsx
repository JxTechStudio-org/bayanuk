import { createBrowserRouter, RouterProvider } from 'react-router-dom'
import { LandingPage } from '@/features/landing/pages/LandingPage'

// Every page of the site is listed here: path -> page.
const router = createBrowserRouter([
  { path: '/', element: <LandingPage /> },
])

export function App() {
  return <RouterProvider router={router} />
}