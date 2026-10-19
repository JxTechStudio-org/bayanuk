import { useQuery } from '@tanstack/react-query'
import { ApiError } from '@/lib/api'
import { getDocumentStatus } from '../api/getDocumentStatus'
import { isFinished } from '../utils/stages'

// Integration contract, section 6: the status is polled every 2 seconds until processing ends,
// or until the API says the document does not exist (404) or was deleted (410).
export function useDocumentStatus(id: string) {
  return useQuery({
    queryKey: ['document-status', id],
    queryFn: () => getDocumentStatus(id),
    refetchInterval: (query) => {
      const { data, error } = query.state
      if (error instanceof ApiError && (error.status === 404 || error.status === 410)) return false
      return data && isFinished(data.status) ? false : 2000
    },
  })
}
