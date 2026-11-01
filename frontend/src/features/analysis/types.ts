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

// Integration contract, section 3.
export type DocumentType = 'job_offer' | 'employment_contract' | 'other'

// Integration contract, section 13: one extracted term. A term that is not found has no value, unit, page or quote.
// A yes or no answer is the text "yes" or "no".
export interface Term {
  key: string
  found: boolean
  value?: string | number
  unit?: string
  page?: number
  quote?: string
}

// Integration contract, section 3: the severity and category of an attention point.
export type FindingSeverity = 'info' | 'attention' | 'high'
export type FindingCategory =
  | 'non_compete'
  | 'long_notice'
  | 'long_probation'
  | 'penalties'
  | 'salary_mismatch'
  | 'missing_info'
  | 'one_sided_change'
  | 'working_time'
  | 'other'

// Integration contract, section 13: one attention point. The title and explanation are written by the backend,
// in the summary language. Page and quote can be missing (Database Schema, findings).
export interface Finding {
  category: FindingCategory
  severity: FindingSeverity
  title: string
  explanation: string
  page?: number | null
  quote?: string | null
}

// Integration contract, section 13: GET /documents/{id}.
export interface DocumentResult {
  id: string
  status: DocumentStatus
  document_type: DocumentType
  language: 'ar' | 'en' | 'mixed'
  page_count: number
  expires_at: string
  // null until the document is ready: there is no result to show yet.
  analysis: {
    id: string
    summary: string
    summary_language: 'ar' | 'en'
    terms: Term[]
    // Sorted high severity first by the backend (Database Schema, findings.sort_order).
    findings: Finding[]
  } | null
}
