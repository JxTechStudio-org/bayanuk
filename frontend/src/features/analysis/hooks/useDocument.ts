import { useQuery } from '@tanstack/react-query'
import { useCurrentUser } from '@/features/auth/hooks/useCurrentUser'
import { ApiError } from '@/lib/api'
import { getDocument } from '../api/getDocument'

// The document and its result, read once. It waits until it is known whether the visitor is signed in,
// so a signed-in user never gets a guest token by mistake (as useDocumentStatus).
// A lost connection or a server error is tried once more. A 4xx answer (404 or 410) is final.
export function useDocument(id: string) {
  const { data: user, isPending: checkingUser } = useCurrentUser()
  return useQuery({
    queryKey: ['document', id],
    queryFn: () => getDocument(id, Boolean(user)),
    enabled: !checkingUser,
    retry: (failureCount, error) =>
      error instanceof ApiError && (error.status === 0 || error.status >= 500) && failureCount < 1,
  })
}
