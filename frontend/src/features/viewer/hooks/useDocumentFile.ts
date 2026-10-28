import { useQuery } from '@tanstack/react-query'
import { useCurrentUser } from '@/features/auth/hooks/useCurrentUser'
import { getDocumentFile } from '../api/getDocumentFile'

// A signed URL is asked again 30 seconds before it expires, so the viewer never opens with an expired one.
const MARGIN_MS = 30_000

// The signed URL of the original file (contract, section 9). It counts as fresh until shortly before it expires;
// after that, opening the viewer again asks for a new one. refetch() asks at once, when a load fails.
export function useDocumentFile(documentId: string) {
  const { data: user, isPending: checkingUser } = useCurrentUser()
  return useQuery({
    queryKey: ['document-file', documentId],
    queryFn: () => getDocumentFile(documentId, Boolean(user)),
    enabled: !checkingUser,
    staleTime: (query) =>
      query.state.data ? Math.max(0, Date.parse(query.state.data.expires_at) - Date.now() - MARGIN_MS) : 0,
  })
}
