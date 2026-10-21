import { request } from '@/lib/api'
import type { DocumentStatusResponse } from '../types'

// A guest's document request, so it carries the guest token (integration contract, section 5).
export function getDocumentStatus(id: string) {
  return request<DocumentStatusResponse>(`/documents/${id}/status`, {}, { guest: true })
}
