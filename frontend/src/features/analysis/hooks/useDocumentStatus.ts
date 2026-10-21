import { useQuery } from '@tanstack/react-query'
import { ApiError } from '@/lib/api'
import { getDocumentStatus } from '../api/getDocumentStatus'
import { isFinished } from '../utils/stages'

// Integration contract, section 6: the status is polled every 2 seconds until processing ends,
// or until the API says the document does not exist (404) or was deleted (410).
// While the daily AI budget pauses the document, it is polled every 60 seconds instead,
// so a page left open overnight does not send thousands of requests.
export function useDocumentStatus(id: string) {
  return useQuery({
    queryKey: ['document-status', id],
    queryFn: () => getDocumentStatus(id),
    // When the API cannot be reached, the same check is retried every 2 seconds until it works,
    // so failureCount counts the failed checks in a row. A server error is retried once.
    // A 4xx answer (for example 404 or 410) is final and never retried.
    retry: (failureCount, error) => {
      if (!(error instanceof ApiError)) return false
      return error.status === 0 || (error.status >= 500 && failureCount < 1)
    },
    retryDelay: 2000,
    refetchInterval: (query) => {
      const { data, error } = query.state
      if (error instanceof ApiError && (error.status === 404 || error.status === 410)) return false
      if (data && isFinished(data.status)) return false
      return data?.status_detail === 'ai_budget_reached' ? 60_000 : 2000
    },
  })
}
