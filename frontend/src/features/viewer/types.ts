// Integration contract, section 9: GET /documents/{id}/file. The URL is valid for 5 minutes.
export interface DocumentFile {
  url: string
  expires_at: string
}
