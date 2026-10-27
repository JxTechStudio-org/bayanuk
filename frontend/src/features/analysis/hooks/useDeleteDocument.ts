import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { useCurrentUser } from '@/features/auth/hooks/useCurrentUser'
import { ApiError } from '@/lib/api'
import { deleteDocument } from '../api/deleteDocument'

// Deletes the document, then goes to the home page and forgets what was loaded about it.
// A document that is already gone (404 or 410) counts as deleted: what the user asked for is done.
export function useDeleteDocument(id: string) {
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const { data: user } = useCurrentUser()

  return useMutation({
    mutationFn: async () => {
      try {
        await deleteDocument(id, Boolean(user))
      } catch (error) {
        if (!(error instanceof ApiError && (error.status === 404 || error.status === 410))) throw error
      }
    },
    onSuccess: () => {
      // Leave first, so the result page does not load the deleted document again.
      navigate('/', { replace: true, flushSync: true })
      queryClient.removeQueries({ queryKey: ['document', id] })
      queryClient.removeQueries({ queryKey: ['document-status', id] })
    },
  })
}
