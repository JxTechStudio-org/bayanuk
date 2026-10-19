import { useQueryClient } from '@tanstack/react-query'
import { useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { clearGuestToken } from '@/lib/guestToken'
import type { User } from '../types'
import { takeReturnTo } from '../utils/returnTo'

// What happens after any successful sign-in (integration contract, sections 10 and 11):
// the guest token is deleted (its documents now belong to the account), the signed-in user is stored,
// and the user goes back to the page they were on.
export function useFinishSignIn() {
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  return useCallback(
    (user: User) => {
      clearGuestToken()
      queryClient.setQueryData(['current-user'], user)
      navigate(takeReturnTo(), { replace: true })
    },
    [queryClient, navigate],
  )
}
