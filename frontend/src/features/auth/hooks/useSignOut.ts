import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { signOut } from '../api/signOut'

// Ends the session, forgets everything loaded for this user, and goes to the home page.
export function useSignOut() {
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  return useMutation({
    mutationFn: signOut,
    onSuccess: () => {
      // Leave first, and at once (flushSync): on a page that needs an account,
      // forgetting the user while still on it would open sign-in instead.
      navigate('/', { replace: true, flushSync: true })
      queryClient.removeQueries({ queryKey: ['document-status'] })
      queryClient.setQueryData(['current-user'], null)
    },
  })
}
