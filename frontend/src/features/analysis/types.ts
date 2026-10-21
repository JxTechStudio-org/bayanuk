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

// Integration contract, section 6: why a document was rejected or failed, as a code.
// The frontend shows its own translated message for each one (processing.reasons).
export type StatusDetail =
  | 'unsafe_file'
  | 'too_many_pages'
  | 'not_employment_document'
  | 'file_unreadable'
  | 'analysis_failed'
  | 'ai_budget_reached'

export interface DocumentStatusResponse {
  id: string
  status: DocumentStatus
  // null while processing and when the document is ready.
  status_detail: StatusDetail | null
}
