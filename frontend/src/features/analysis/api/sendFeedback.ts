import { postJson, request } from '@/lib/api'

// Integration contract, section 13: thumbs up (1) or down (-1) on the analysis. Answers 201.
// The analysis belongs to a document, so a guest sends the guest token, as with the document requests (section 5).
export function sendFeedback(analysisId: string, rating: 1 | -1, signedIn: boolean) {
  return request<void>(`/analyses/${analysisId}/feedback`, postJson({ rating }), {
    guestToken: signedIn ? undefined : 'guest',
  })
}
