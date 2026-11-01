import { useQuery } from '@tanstack/react-query'
import { useCurrentUser } from '@/features/auth/hooks/useCurrentUser'
import { getChat } from '../api/getChat'

export const chatKey = (documentId: string) => ['chat', documentId]

// The chat history and the questions left, read once. It waits for /me, as the document requests do.
export function useChat(documentId: string) {
  const { data: user, isPending: checkingUser } = useCurrentUser()
  return useQuery({
    queryKey: chatKey(documentId),
    queryFn: () => getChat(documentId, Boolean(user)),
    enabled: !checkingUser,
  })
}
