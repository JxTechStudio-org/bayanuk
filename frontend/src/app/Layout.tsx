import { Outlet } from 'react-router-dom'
import { Header } from '@/components/Header'

// Wraps every page: the header is shared, and <Outlet /> is where the current page goes.
export function Layout() {
  return (
    <>
      <Header />
      <main>
        <Outlet />
      </main>
    </>
  )
}