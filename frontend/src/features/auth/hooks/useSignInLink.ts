import { useMutation } from '@tanstack/react-query'
import { requestSignInLink } from '../api/requestSignInLink'

// Sends the sign-in link. Used for the first send and for "send again".
export function useSignInLink() {
  return useMutation({ mutationFn: requestSignInLink })
}
