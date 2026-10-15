// MVP Definition, section 4.2: PDF and DOCX files, up to 10 MB and 30 pages.
// The page count is checked by the API (Technical Plan, pipeline step 1), because a browser cannot count DOCX pages.
export const MAX_FILE_SIZE = 10 * 1024 * 1024

const ACCEPTED_TYPES = [
  'application/pdf',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
]

export type FileProblem = 'type' | 'size'

export function validateFile(file: File): FileProblem | null {
  const name = file.name.toLowerCase()
  const typeOk = ACCEPTED_TYPES.includes(file.type) || name.endsWith('.pdf') || name.endsWith('.docx')
  if (!typeOk) return 'type'
  if (file.size > MAX_FILE_SIZE) return 'size'
  return null
}
