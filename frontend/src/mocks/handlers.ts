import { delay, http, HttpResponse } from 'msw'
import type { DocumentStatus } from '@/features/analysis/types'

// A fake API that follows docs/integration-contract.md. Only used in development, when VITE_API_MOCK=true.
//
// Pick the outcome by file name: a name with "reject" ends as rejected, "fail" ends as failed,
// and "budget" pauses while analyzing, as when the daily AI budget is reached.
// Upload errors (section 4): a name with "limit" gets the daily upload limit, and "nobudget"
// the daily AI budget. A wrong type or a file over 10 MB gets its error too, if sent.
const FLOW: DocumentStatus[] = ['uploaded', 'scanning', 'extracting', 'classifying', 'analyzing', 'indexing', 'ready']
const SECONDS_PER_STEP = 2
const CSRF_TOKEN = 'mock-csrf-token'

interface FakeDocument {
  guestToken: string
  startedAt: number
  outcome: 'ready' | 'rejected' | 'failed' | 'paused'
}

const guestTokens = new Set<string>()
const documents = new Map<string, FakeDocument>()

// Section 4: every error has a code and a message in the interface language (Accept-Language).
// The real messages are written by the backend; these stand in for them.
const MESSAGES: Record<string, { ar: string; en: string }> = {
  file_type_not_supported: {
    ar: 'نوع الملف غير مدعوم. ارفع ملف PDF أو Word.',
    en: 'This file type is not supported. Upload a PDF or Word file.',
  },
  file_too_large: {
    ar: 'حجم الملف يتجاوز الحد المسموح به (10 ميجابايت).',
    en: 'The file is larger than the allowed limit (10 MB).',
  },
  upload_limit_reached: {
    ar: 'وصلت للحد اليومي للرفع. حاول مرة ثانية غدًا.',
    en: 'You reached the daily upload limit. Try again tomorrow.',
  },
  ai_budget_reached: {
    ar: 'توقفت التحليلات الجديدة لهذا اليوم. حاول مرة ثانية غدًا.',
    en: 'New analyses are paused for today. Try again tomorrow.',
  },
  document_not_found: { ar: 'لم نجد هذا المستند.', en: 'Document not found.' },
  invalid_guest_token: { ar: 'رقم الزائر غير صحيح.', en: 'The guest token is not valid.' },
  csrf_token_mismatch: { ar: 'انتهت الجلسة. حاول مرة ثانية.', en: 'The session expired. Try again.' },
}

function error(request: Request, status: number, code: string) {
  const language = request.headers.get('Accept-Language') === 'en' ? 'en' : 'ar'
  return HttpResponse.json({ error: { code, message: MESSAGES[code]?.[language] ?? '' } }, { status })
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
    if (!hasCsrfToken(request)) return error(request, 419, 'csrf_token_mismatch')
    const token = crypto.randomUUID()
    guestTokens.add(token)
    return HttpResponse.json({ token }, { status: 201 })
  }),

  http.post('/api/v1/documents', async ({ request }) => {
    if (!hasCsrfToken(request)) return error(request, 419, 'csrf_token_mismatch')
    const guestToken = guestTokenOf(request)
    if (!guestToken) return error(request, 400, 'invalid_guest_token')

    const form = await request.formData()
    const file = form.get('file')
    const name = file instanceof File ? file.name.toLowerCase() : ''
    if (!/\.(pdf|docx)$/.test(name)) return error(request, 422, 'file_type_not_supported')
    if (file instanceof File && file.size > 10 * 1024 * 1024) return error(request, 422, 'file_too_large')
    if (name.includes('nobudget')) return error(request, 429, 'ai_budget_reached')
    if (name.includes('limit')) return error(request, 429, 'upload_limit_reached')
    // Document IDs are UUIDs (Technical Plan, section 8).
    const id = crypto.randomUUID()
    documents.set(id, {
      guestToken,
      startedAt: Date.now(),
      outcome: name.includes('reject')
        ? 'rejected'
        : name.includes('fail')
          ? 'failed'
          : name.includes('budget')
            ? 'paused'
            : 'ready',
    })
    await delay(600)
    return HttpResponse.json({ id, status: 'uploaded' }, { status: 202 })
  }),

  http.get('/api/v1/documents/:id/status', ({ params, request }) => {
    const guestToken = guestTokenOf(request)
    if (!guestToken) return error(request, 400, 'invalid_guest_token')

    // Section 4: the same code for a missing document and another guest's document.
    const document = documents.get(String(params.id))
    if (!document || document.guestToken !== guestToken) return error(request, 404, 'document_not_found')

    const step = Math.floor((Date.now() - document.startedAt) / 1000 / SECONDS_PER_STEP)

    // Rejected after classifying, failed while extracting (Technical Plan, section 5).
    // Section 6: status_detail is a code.
    if (document.outcome === 'rejected' && step >= FLOW.indexOf('classifying')) {
      return HttpResponse.json({ id: params.id, status: 'rejected', status_detail: 'not_employment_document' })
    }
    if (document.outcome === 'failed' && step >= FLOW.indexOf('extracting')) {
      return HttpResponse.json({ id: params.id, status: 'failed', status_detail: 'file_unreadable' })
    }
    if (document.outcome === 'paused' && step >= FLOW.indexOf('analyzing')) {
      return HttpResponse.json({ id: params.id, status: 'analyzing', status_detail: 'ai_budget_reached' })
    }
    const status = FLOW[Math.min(step, FLOW.length - 1)]
    return HttpResponse.json({ id: params.id, status, status_detail: null })
  }),
]
