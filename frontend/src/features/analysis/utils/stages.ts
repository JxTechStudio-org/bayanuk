import type { DocumentStatus } from '../types'

// The four progress labels from the MVP Definition, section 5, step 3.
export const STAGES = ['checking', 'reading', 'findingTerms', 'findingAttention'] as const
export type Stage = (typeof STAGES)[number]

// Database Schema: "The frontend polls the status and maps each value to a friendly progress label."
const STAGE_OF: Partial<Record<DocumentStatus, Stage>> = {
  uploaded: 'checking',
  scanning: 'checking',
  extracting: 'reading',
  classifying: 'findingTerms',
  analyzing: 'findingTerms',
  indexing: 'findingAttention',
}

/** Index of the stage in progress, or STAGES.length once the document is ready. */
export function stageIndex(status: DocumentStatus): number {
  if (status === 'ready') return STAGES.length
  const stage = STAGE_OF[status]
  return stage ? STAGES.indexOf(stage) : 0
}

export function isFinished(status: DocumentStatus): boolean {
  return status === 'ready' || status === 'failed' || status === 'rejected'
}
