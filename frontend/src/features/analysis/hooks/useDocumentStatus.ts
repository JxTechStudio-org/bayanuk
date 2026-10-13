import { useQuery } from '@tanstack/react-query'
import { getDocumentStatus } from '../api/getDocumentStatus'
import { isFinished } from '../utils/stages'

// Technical Plan, section 7: the status is polled every 2 seconds until processing ends.
export function useDocumentStatus(id: string) {
  return useQuery({
    queryKey: ['document-status', id],
    queryFn: () => getDocumentStatus(id),
    refetchInterval: (query) => {
      const status = query.state.data?.status
      return status && isFinished(status) ? false : 2000
    },
  })
}
