import { useQuery } from '@tanstack/react-query'
import { getCurrentUser } from '../api/getCurrentUser'

// Who is using the site: the signed-in user, or null for a guest. Asked once when the app starts;
// signing in or out refreshes it.
export function useCurrentUser() {
  return useQuery({
    queryKey: ['current-user'],
    queryFn: getCurrentUser,
    staleTime: Infinity,
  })
}
