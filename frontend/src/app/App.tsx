import { createBrowserRouter, RouterProvider } from 'react-router-dom'
import { LandingPage } from '@/features/landing/pages/LandingPage'
import { Layout } from './Layout'

// Every page of the site is listed here: path -> page.
// Pages inside the Layout get the shared header.
const router = createBrowserRouter([
  {
    element: <Layout />,
    children: [{ path: '/', element: <LandingPage /> }],
  },
])

export function App() {
  return <RouterProvider router={router} />
}