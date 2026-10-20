import { delay, http, HttpResponse } from 'msw'
import type { DocumentStatus } from '@/features/analysis/types'
import type { User } from '@/features/auth/types'

// A fake API that follows docs/integration-contract.md. Only used in development, when VITE_API_MOCK=true.
//
// Pick the outcome by file name: a name with "reject" ends as rejected, "fail" ends as failed,
// and "budget" pauses while analyzing, as when the daily AI budget is reached.
// Upload errors (section 4): a name with "limit" gets the daily upload limit, and "nobudget"
// the daily AI budget. A wrong type or a file over 10 MB gets its error too, if sent.
//
// Sign-in: there is no email, so the sign-in link is printed in the browser console.
// An email with "limit" gets the "too many requests" answer. The links /auth/verify?token=expired
// and /auth/verify?token=used show those two errors. Google signs in at once as a test user.
const FLOW: DocumentStatus[] = ['uploaded', 'scanning', 'extracting', 'classifying', 'analyzing', 'indexing', 'ready']
const SECONDS_PER_STEP = 2
const CSRF_TOKEN = 'mock-csrf-token'

interface FakeDocument {
  // The owner: a guest token, or a user once signed in.
  owner: string
  startedAt: number
  outcome: 'ready' | 'rejected' | 'failed' | 'paused'
}

// The fake data is kept in sessionStorage, so it survives a page reload (Google sign-in reloads the page)
// and is gone when the tab closes. The real API keeps the session in a cookie and the rest in the database.
const STORAGE_KEY = 'bayanuk.fakeApi'
const saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? '{}')
const guestTokens = new Set<string>(saved.guestTokens ?? [])
const documents = new Map<string, FakeDocument>(saved.documents ?? [])
const signInLinks = new Map<string, { email: string; used: boolean }>(saved.signInLinks ?? [])
let currentUser: User | null = saved.currentUser ?? null

function save() {
  sessionStorage.setItem(
    STORAGE_KEY,
    JSON.stringify({
      guestTokens: [...guestTokens],
      documents: [...documents],
      signInLinks: [...signInLinks],
      currentUser,
    }),
  )
}

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
  unauthorized: { ar: 'سجّل دخولك للمتابعة.', en: 'Sign in to continue.' },
  validation_error: { ar: 'تحقق من الحقول المحددة.', en: 'Please check the highlighted fields.' },
  magic_link_limit_reached: {
    ar: 'طلبت روابط كثيرة. انتظر قليلًا ثم حاول مرة ثانية.',
    en: 'You asked for too many links. Wait a little, then try again.',
  },
  link_expired: { ar: 'انتهت صلاحية الرابط.', en: 'This link has expired.' },
  link_used: { ar: 'استُخدم هذا الرابط من قبل.', en: 'This link was already used.' },
  link_invalid: { ar: 'الرابط غير صحيح.', en: 'This link is not valid.' },
}

function error(request: Request, status: number, code: string, details?: Record<string, string[]>) {
  const language = request.headers.get('Accept-Language') === 'en' ? 'en' : 'ar'
  const message = MESSAGES[code]?.[language] ?? ''
  return HttpResponse.json({ error: { code, message, ...(details ? { details } : {}) } }, { status })
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

// Who owns the documents of this request: the signed-in user, or the guest.
function ownerOf(request: Request) {
  return currentUser?.id ?? guestTokenOf(request)
}

// Signs the user in and moves the guest's documents to the account (section 5, the guest claim).
function signIn(email: string, request: Request): User {
  currentUser = { id: crypto.randomUUID(), name: null, email, locale: 'ar', keep_documents: false }
  const guestToken = guestTokenOf(request)
  for (const document of documents.values()) {
    if (guestToken && document.owner === guestToken) document.owner = currentUser.id
  }
  save()
  return currentUser
}

export const handlers = [
  http.get('/sanctum/csrf-cookie', () => {
    return new HttpResponse(null, { status: 204, headers: { 'Set-Cookie': `XSRF-TOKEN=${CSRF_TOKEN}; Path=/` } })
  }),

  http.post('/api/v1/guest-token', ({ request }) => {
    if (!hasCsrfToken(request)) return error(request, 419, 'csrf_token_mismatch')
    const token = crypto.randomUUID()
    guestTokens.add(token)
    save()
    return HttpResponse.json({ token }, { status: 201 })
  }),

  http.get('/api/v1/me', ({ request }) => {
    return currentUser ? HttpResponse.json(currentUser) : error(request, 401, 'unauthorized')
  }),

  http.post('/api/v1/auth/magic-link', async ({ request }) => {
    if (!hasCsrfToken(request)) return error(request, 419, 'csrf_token_mismatch')
    const { email } = (await request.json()) as { email?: string }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      return error(request, 422, 'validation_error', { email: ['Enter a valid email address.'] })
    }
    if (email.includes('limit')) return error(request, 429, 'magic_link_limit_reached')

    const token = crypto.randomUUID()
    signInLinks.set(token, { email: email.toLowerCase(), used: false })
    save()
    await delay(600)
    console.info(`[Fake API] Sign-in link for ${email}: ${location.origin}/auth/verify?token=${token}`)
    return new HttpResponse(null, { status: 202 })
  }),

  http.post('/api/v1/auth/magic-link/verify', async ({ request }) => {
    if (!hasCsrfToken(request)) return error(request, 419, 'csrf_token_mismatch')
    const { token = '' } = (await request.json()) as { token?: string }
    await delay(600)
    if (token === 'expired') return error(request, 410, 'link_expired')
    const link = signInLinks.get(token)
    if (token === 'used' || link?.used) return error(request, 410, 'link_used')
    if (!link) return error(request, 400, 'link_invalid')

    link.used = true
    return HttpResponse.json({ user: signIn(link.email, request) })
  }),

  // Google cannot be reached from here: the fake API signs in a test user and returns the frontend page
  // the real backend would redirect to after Google.
  http.post('/api/v1/auth/google/redirect', ({ request }) => {
    if (!hasCsrfToken(request)) return error(request, 419, 'csrf_token_mismatch')
    signIn('google.user@example.com', request)
    return HttpResponse.json({ url: '/auth/callback' })
  }),

  http.post('/api/v1/auth/logout', ({ request }) => {
    if (!hasCsrfToken(request)) return error(request, 419, 'csrf_token_mismatch')
    currentUser = null
    save()
    return new HttpResponse(null, { status: 204 })
  }),

  http.post('/api/v1/documents', async ({ request }) => {
    if (!hasCsrfToken(request)) return error(request, 419, 'csrf_token_mismatch')
    const owner = ownerOf(request)
    if (!owner) return error(request, 400, 'invalid_guest_token')

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
      owner,
      startedAt: Date.now(),
      outcome: name.includes('reject')
        ? 'rejected'
        : name.includes('fail')
          ? 'failed'
          : name.includes('budget')
            ? 'paused'
            : 'ready',
    })
    save()
    await delay(600)
    return HttpResponse.json({ id, status: 'uploaded' }, { status: 202 })
  }),

  http.get('/api/v1/documents/:id/status', ({ params, request }) => {
    const owner = ownerOf(request)
    if (!owner) return error(request, 400, 'invalid_guest_token')

    // Section 4: the same code for a missing document and someone else's document.
    const document = documents.get(String(params.id))
    if (!document || document.owner !== owner) return error(request, 404, 'document_not_found')

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
