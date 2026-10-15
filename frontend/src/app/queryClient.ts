import { QueryClient } from '@tanstack/react-query'

export const queryClient = new QueryClient({
  defaultOptions: {
    // One retry for reads; a failed upload is not repeated on its own.
    queries: { retry: 1 },
    mutations: { retry: false },
  },
})
