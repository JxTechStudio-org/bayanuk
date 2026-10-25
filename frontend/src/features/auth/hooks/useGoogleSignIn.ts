import { useMutation } from '@tanstack/react-query'
import { startGoogleSignIn } from '../api/startGoogleSignIn'

// Integration contract, section 11: the backend returns the Google address, and the whole page goes there.
export function useGoogleSignIn() {
  return useMutation({
    mutationFn: startGoogleSignIn,
    onSuccess: ({ url }) => window.location.assign(url),
  })
}
