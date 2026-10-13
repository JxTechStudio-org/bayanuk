// Database Schema, section 4: documents.status
export type DocumentStatus =
  | 'uploaded'
  | 'scanning'
  | 'extracting'
  | 'classifying'
  | 'analyzing'
  | 'indexing'
  | 'ready'
  | 'failed'
  | 'rejected'

export interface DocumentStatusResponse {
  id: string
  status: DocumentStatus
  // User-safe reason when failed or rejected (Database Schema, documents.status_detail).
  status_detail: string | null
}
