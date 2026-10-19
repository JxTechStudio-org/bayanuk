import { delay, http, HttpResponse } from 'msw'
import type { DocumentStatus } from '@/features/analysis/types'

// A fake API that follows docs/integration-contract.md. Only used in development, when VITE_API_MOCK=true.
//
// Pick the outcome by file name: a name with "reject" ends as rejected, "fail" ends as failed.
const FLOW: DocumentStatus[] = ['uploaded', 'scanning', 'extracting', 'classifying', 'analyzing', 'indexing', 'ready']
const SECONDS_PER_STEP = 2
const CSRF_TOKEN = 'mock-csrf-token'

interface FakeDocument {
  guestToken: string
  startedAt: number
  outcome: 'ready' | 'rejected' | 'failed'
}

const guestTokens = new Set<string>()
const documents = new Map<string, FakeDocument>()

// Section 4: one error format for every error.
function error(status: number, code: string) {
  return HttpResponse.json({ error: { code, message: '' } }, { status })
}

// Section 2: a request that changes data needs the CSRF token from the cookie.
function hasCsrfToken(request: Request) {
  return request.headers.get('X-XSRF-TOKEN') === CSRF_TOKEN
}

// Section 5: the guest token must be one the backend issued.
function guestTokenOf(request: Request) {
  const token = request.headers.get('X-Guest-Token')
  return token && guestTokens.has(token) ? token : null
}

export const handlers = [
  http.get('/sanctum/csrf-cookie', () => {
    return new HttpResponse(null, { status: 204, headers: { 'Set-Cookie': `XSRF-TOKEN=${CSRF_TOKEN}; Path=/` } })
  }),

  http.post('/api/v1/guest-token', ({ request }) => {
    if (!hasCsrfToken(request)) return error(419, 'csrf_token_mismatch')
    const token = crypto.randomUUID()
    guestTokens.add(token)
    return HttpResponse.json({ token }, { status: 201 })
  }),

  http.post('/api/v1/documents', async ({ request }) => {
    if (!hasCsrfToken(request)) return error(419, 'csrf_token_mismatch')
    const guestToken = guestTokenOf(request)
    if (!guestToken) return error(400, 'invalid_guest_token')

    const form = await request.formData()
    const file = form.get('file')
    const name = file instanceof File ? file.name.toLowerCase() : ''
    // Document IDs are UUIDs (Technical Plan, section 8).
    const id = crypto.randomUUID()
    documents.set(id, {
      guestToken,
      startedAt: Date.now(),
      outcome: name.includes('reject') ? 'rejected' : name.includes('fail') ? 'failed' : 'ready',
    })
    await delay(600)
    return HttpResponse.json({ id, status: 'uploaded' }, { status: 202 })
  }),

  http.get('/api/v1/documents/:id/status', ({ params, request }) => {
    const guestToken = guestTokenOf(request)
    if (!guestToken) return error(400, 'invalid_guest_token')

    // Section 4: the same code for a missing document and another guest's document.
    const document = documents.get(String(params.id))
    if (!document || document.guestToken !== guestToken) return error(404, 'document_not_found')

    const step = Math.floor((Date.now() - document.startedAt) / 1000 / SECONDS_PER_STEP)

    // Rejected after classifying, failed while extracting (Technical Plan, section 5).
    if (document.outcome === 'rejected' && step >= FLOW.indexOf('classifying')) {
      return HttpResponse.json({ id: params.id, status: 'rejected', status_detail: null })
    }
    if (document.outcome === 'failed' && step >= FLOW.indexOf('extracting')) {
      return HttpResponse.json({ id: params.id, status: 'failed', status_detail: null })
    }
    const status = FLOW[Math.min(step, FLOW.length - 1)]
    return HttpResponse.json({ id: params.id, status, status_detail: null })
  }),
]
