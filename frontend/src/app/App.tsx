import { createBrowserRouter, RouterProvider } from 'react-router-dom'
import { HomePage } from '@/pages/HomePage'

// Every page of the site is listed here: path -> page.
const router = createBrowserRouter([
  { path: '/', element: <HomePage /> },
])

export function App() {
  return <RouterProvider router={router} />
}