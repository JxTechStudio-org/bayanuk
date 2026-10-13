import { request } from '@/lib/api'
import type { DocumentStatusResponse } from '../types'

export function getDocumentStatus(id: string) {
  return request<DocumentStatusResponse>(`/documents/${id}/status`)
}
