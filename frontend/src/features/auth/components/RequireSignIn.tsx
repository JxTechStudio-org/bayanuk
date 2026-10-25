import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { useCurrentUser } from '../hooks/useCurrentUser'

// Wraps a page that needs an account (for example My documents). A guest goes to sign-in,
// and comes back to this page afterwards. Nothing shows while it is not known yet.
export function RequireSignIn({ children }: { children: ReactNode }) {
  const { pathname, search } = useLocation()
  const { data: user, isPending } = useCurrentUser()

  if (isPending) return null
  if (!user) return <Navigate to={`/sign-in?next=${encodeURIComponent(pathname + search)}`} replace />
  return children
}
