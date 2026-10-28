// PDF files only (the manager's decision, replacing "PDF and DOCX" in MVP 4.2), up to 10 MB and 30 pages.
// The page count is checked by the API (Technical Plan, pipeline step 1).
export const MAX_FILE_SIZE = 10 * 1024 * 1024

export type FileProblem = 'type' | 'size'

export function validateFile(file: File): FileProblem | null {
  const name = file.name.toLowerCase()
  const typeOk = file.type === 'application/pdf' || name.endsWith('.pdf')
  if (!typeOk) return 'type'
  if (file.size > MAX_FILE_SIZE) return 'size'
  return null
}
