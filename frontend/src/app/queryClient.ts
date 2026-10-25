import { MutationCache, QueryCache, QueryClient } from '@tanstack/react-query'
import { ApiError } from '@/lib/api'

// Integration contract, section 4: a 401 (unauthorized) means the session is missing or has ended.
// The signed-in user is forgotten, so a page that needs an account sends the user to sign in
// and back to the same page afterwards (RequireSignIn).
export function createQueryClient() {
  const client: QueryClient = new QueryClient({
    queryCache: new QueryCache({ onError: forgetUserOnUnauthorized }),
    mutationCache: new MutationCache({ onError: forgetUserOnUnauthorized }),
    defaultOptions: {
      // One retry for reads; a failed upload is not repeated on its own.
      queries: { retry: 1 },
      mutations: { retry: false },
    },
  })

  function forgetUserOnUnauthorized(error: Error) {
    if (error instanceof ApiError && error.status === 401) client.setQueryData(['current-user'], null)
  }

  return client
}

export const queryClient = createQueryClient()
