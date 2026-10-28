import { delay, http, HttpResponse } from 'msw'
import type { DocumentStatus } from '@/features/analysis/types'
import type { ChatMessage } from '@/features/chat/types'
import { fakeAnswer } from './fakeChat'
import { FAKE_FINDINGS, FAKE_SUMMARIES, FAKE_TERMS } from './fakeResult'
// The original file of every fake PDF document: the fake contract, with the quotes on their pages.
import fakeContractUrl from './fake-contract.pdf?url'

// A fake API that follows docs/integration-contract.md. Only used in development, when VITE_API_MOCK=true.
//
// Pick the outcome by file name: a name with "reject" ends as rejected, "fail" ends as failed,
// and "budget" pauses while analyzing, as when the daily AI budget is reached.
// A name with "expired" gets ready, then its result answers 410, as a document deleted after the retention period.
// Upload errors (section 4): a name with "limit" gets the daily upload limit, and "nobudget"
// the daily AI budget. A wrong type or a file over 10 MB gets its error too, if sent.
//
// Viewer: every document shows the fake contract (fake-contract.pdf). The signed URL expires after 5 minutes,
// as the real one (section 9).
//
// Chat: a question about the notice period, the probation or the salary gets an answer with its page and quote;
// any other question gets "not found". A question with "nobudget" gets the daily AI budget error.
// Guests have 3 questions per document and signed-in users 20 (MVP 8).
//
// Sign-in: there is no email, so the sign-in link is printed in the browser console.
// An email with "limit" gets the "too many requests" answer. The links /auth/verify?token=expired
// and /auth/verify?token=used show those two errors. Google signs in at once as a test user.
const FLOW: DocumentStatus[] = ['uploaded', 'scanning', 'extracting', 'classifying', 'analyzing', 'indexing', 'ready']
const SECONDS_PER_STEP = 2
const CSRF_TOKEN = 'mock-csrf-token'

// The user the fake GET /me answers with: the users fields from the Database Schema, section 3.1.
interface FakeUser {
  id: string
  name: string | null
  email: string
  locale: 'ar' | 'en'
  keep_documents: boolean
}

interface FakeDocument {
  // The owner: a guest token, or a user once signed in.
  owner: string
  startedAt: number
  outcome: 'ready' | 'rejected' | 'failed' | 'paused' | 'expired'
  // The interface language at upload (Accept-Language): the language of the summary.
  language: 'ar' | 'en'
}

// The fake data is kept in localStorage, so it survives a page reload (Google sign-in reloads the page)
// and is shared by the browser's tabs: a sign-in link opened in a new tab works, as one opened from an email does.
// The real API keeps the session in a cookie and the rest in the database.
const STORAGE_KEY = 'bayanuk.fakeApi'
const guestTokens = new Set<string>()
const documents = new Map<string, FakeDocument>()
const signInLinks = new Map<string, { email: string; used: boolean }>()
// The chat of each document, by document id (Database Schema: chat_sessions and chat_messages).
const chats = new Map<string, { messages: ChatMessage[]; questionCount: number }>()
// The accounts, by email: signing in again with the same email opens the same account and its documents.
const users = new Map<string, FakeUser>()
let currentUser: FakeUser | null = null

function load() {
  const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}')
  guestTokens.clear()
  documents.clear()
  signInLinks.clear()
  chats.clear()
  users.clear()
  for (const token of saved.guestTokens ?? []) guestTokens.add(token)
  for (const [id, document] of saved.documents ?? []) documents.set(id, document)
  for (const [token, link] of saved.signInLinks ?? []) signInLinks.set(token, link)
  for (const [id, chat] of saved.chats ?? []) chats.set(id, chat)
  for (const [email, user] of saved.users ?? []) users.set(email, user)
  currentUser = saved.currentUser ?? null
}

load()
// Each tab runs its own copy of the fake API. When another tab changes the data (for example, signs in
// from the email link), this tab reads it again, so every tab sees the same session, like the real API.
window.addEventListener('storage', (event) => {
  if (event.key === STORAGE_KEY) load()
})

function save() {
  localStorage.setItem(
    STORAGE_KEY,
    JSON.stringify({
      guestTokens: [...guestTokens],
      documents: [...documents],
      signInLinks: [...signInLinks],
      chats: [...chats],
      users: [...users],
      currentUser,
    }),
  )
}

// Section 4: every error has a code and a message in the interface language (Accept-Language).
// The real messages are written by the backend; these stand in for them.
const MESSAGES: Record<string, { ar: string; en: string }> = {
  file_type_not_supported: {
    ar: 'نوع الملف غير مدعوم. ارفع ملف PDF.',
    en: 'This file type is not supported. Upload a PDF file.',
  },
  file_too_large: {
    ar: 'حجم الملف يتجاوز الحد المسموح به (10 ميجابايت).',
    en: 'The file is larger than the allowed limit (10 MB).',
  },
  upload_limit_reached: {
    ar: 'وصلت إلى الحد اليومي للرفع. حاول مرة أخرى غدًا.',
    en: 'You reached the daily upload limit. Try again tomorrow.',
  },
  ai_budget_reached: {
    ar: 'توقفت التحليلات الجديدة لهذا اليوم. حاول مرة أخرى غدًا.',
    en: 'New analyses are paused for today. Try again tomorrow.',
  },
  document_not_found: { ar: 'لم نجد هذا المستند.', en: 'Document not found.' },
  document_expired: { ar: 'حُذف هذا المستند بعد انتهاء مدة حفظه.', en: 'This document was deleted after its retention period.' },
  invalid_guest_token: { ar: 'رقم الزائر غير صحيح.', en: 'The guest token is not valid.' },
  csrf_token_mismatch: { ar: 'انتهت الجلسة. حاول مرة أخرى.', en: 'The session expired. Try again.' },
  unauthorized: { ar: 'سجّل دخولك للمتابعة.', en: 'Sign in to continue.' },
  validation_error: { ar: 'تحقق من الحقول المحددة.', en: 'Please check the highlighted fields.' },
  magic_link_limit_reached: {
    ar: 'طلبت روابط كثيرة. انتظر قليلًا ثم حاول مرة أخرى.',
    en: 'You asked for too many links. Wait a little, then try again.',
  },
  link_expired: { ar: 'انتهت صلاحية الرابط.', en: 'This link has expired.' },
  link_used: { ar: 'استُخدم هذا الرابط من قبل.', en: 'This link was already used.' },
  link_invalid: { ar: 'الرابط غير صحيح.', en: 'This link is not valid.' },
  question_limit_reached: {
    ar: 'وصلت إلى حد الأسئلة لهذا المستند.',
    en: 'You reached the question limit for this document.',
  },
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
function signIn(email: string, request: Request) {
  // The first sign-in creates the account; the next ones open it.
  const user = users.get(email) ?? { id: crypto.randomUUID(), name: null, email, locale: 'ar', keep_documents: false }
  users.set(email, user)
  currentUser = user
  const guestToken = guestTokenOf(request)
  for (const document of documents.values()) {
    if (guestToken && document.owner === guestToken) document.owner = user.id
  }
  save()
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
    signIn(link.email, request)
    return new HttpResponse(null, { status: 204 })
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
    if (!name.endsWith('.pdf')) return error(request, 422, 'file_type_not_supported')
    if (file instanceof File && file.size > 10 * 1024 * 1024) return error(request, 422, 'file_too_large')
    if (name.includes('nobudget')) return error(request, 429, 'ai_budget_reached')
    if (name.includes('limit')) return error(request, 429, 'upload_limit_reached')
    // Document IDs are UUIDs (Technical Plan, section 8).
    const id = crypto.randomUUID()
    documents.set(id, {
      owner,
      startedAt: Date.now(),
      language: request.headers.get('Accept-Language') === 'en' ? 'en' : 'ar',
      outcome: name.includes('reject')
        ? 'rejected'
        : name.includes('fail')
          ? 'failed'
          : name.includes('budget')
            ? 'paused'
            : name.includes('expired')
              ? 'expired'
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

    return HttpResponse.json({ id: params.id, ...statusOf(document) })
  }),

  http.get('/api/v1/documents/:id', ({ params, request }) => {
    const owner = ownerOf(request)
    if (!owner) return error(request, 400, 'invalid_guest_token')

    const document = documents.get(String(params.id))
    if (!document || document.owner !== owner) return error(request, 404, 'document_not_found')

    const { status } = statusOf(document)
    if (document.outcome === 'expired' && status === 'ready') return error(request, 410, 'document_expired')

    // Section 13. Before the document is ready there is no analysis yet.
    // A document saved by an older version of the fake API has no language; it gets Arabic.
    const language = document.language ?? 'ar'
    return HttpResponse.json({
      id: params.id,
      status,
      document_type: 'employment_contract',
      language: 'ar',
      page_count: 9,
      expires_at: new Date(document.startedAt + 24 * 60 * 60 * 1000).toISOString(),
      analysis:
        status === 'ready'
          ? {
              id: `${params.id}-analysis`,
              summary: FAKE_SUMMARIES[language],
              summary_language: language,
              terms: FAKE_TERMS,
              findings: FAKE_FINDINGS[language],
            }
          : null,
    })
  }),

  // Section 13: the analysis id of a fake document is "<document id>-analysis".
  http.post('/api/v1/analyses/:id/feedback', async ({ params, request }) => {
    if (!hasCsrfToken(request)) return error(request, 419, 'csrf_token_mismatch')
    const owner = ownerOf(request)
    if (!owner) return error(request, 400, 'invalid_guest_token')

    const document = documents.get(String(params.id).replace(/-analysis$/, ''))
    if (!document || document.owner !== owner) return error(request, 404, 'document_not_found')

    const { rating } = (await request.json()) as { rating?: number }
    if (rating !== 1 && rating !== -1) {
      return error(request, 422, 'validation_error', { rating: ['The rating must be 1 or -1.'] })
    }
    return new HttpResponse(null, { status: 201 })
  }),

  // Section 9: a signed URL of the file in storage, valid for 5 minutes.
  http.get('/api/v1/documents/:id/file', ({ params, request }) => {
    const owner = ownerOf(request)
    if (!owner) return error(request, 400, 'invalid_guest_token')
    const document = documents.get(String(params.id))
    if (!document || document.owner !== owner) return error(request, 404, 'document_not_found')

    const expiresAt = Date.now() + 5 * 60 * 1000
    return HttpResponse.json({
      url: `/fake-storage/${params.id}?expires=${expiresAt}`,
      expires_at: new Date(expiresAt).toISOString(),
    })
  }),

  // The fake storage behind the signed URL: an expired URL is refused, as by the real storage.
  http.get('/fake-storage/:id', async ({ params, request }) => {
    const expires = Number(new URL(request.url).searchParams.get('expires'))
    const document = documents.get(String(params.id))
    if (!document || !expires || Date.now() > expires) return new HttpResponse(null, { status: 403 })
    const pdf = await fetch(fakeContractUrl).then((response) => response.arrayBuffer())
    return new HttpResponse(pdf, { headers: { 'Content-Type': 'application/pdf' } })
  }),

  // Section 14: the history, and the questions left (section 8).
  http.get('/api/v1/documents/:id/chat', ({ params, request }) => {
    const owner = ownerOf(request)
    if (!owner) return error(request, 400, 'invalid_guest_token')
    const document = documents.get(String(params.id))
    if (!document || document.owner !== owner) return error(request, 404, 'document_not_found')

    const chat = chats.get(String(params.id)) ?? { messages: [], questionCount: 0 }
    return HttpResponse.json({ messages: chat.messages, remaining_questions: questionLimit() - chat.questionCount })
  }),

  http.post('/api/v1/documents/:id/chat', async ({ params, request }) => {
    if (!hasCsrfToken(request)) return error(request, 419, 'csrf_token_mismatch')
    const owner = ownerOf(request)
    if (!owner) return error(request, 400, 'invalid_guest_token')
    const id = String(params.id)
    const document = documents.get(id)
    if (!document || document.owner !== owner) return error(request, 404, 'document_not_found')

    const { question = '' } = (await request.json()) as { question?: string }
    if (!question.trim()) return error(request, 422, 'validation_error', { question: ['Write a question.'] })
    const chat = chats.get(id) ?? { messages: [], questionCount: 0 }
    if (chat.questionCount >= questionLimit()) return error(request, 429, 'question_limit_reached')
    // Section 8: refused before any AI call, and the question is not counted.
    if (question.includes('nobudget')) return error(request, 429, 'ai_budget_reached')

    await delay(800)
    chat.questionCount += 1
    const answer = fakeAnswer(question, questionLimit() - chat.questionCount)
    const now = new Date().toISOString()
    chat.messages.push(
      { role: 'user', content: question, citations: [], answer_found: null, created_at: now },
      { role: 'assistant', content: answer.answer, citations: answer.citations, answer_found: answer.answer_found, created_at: now },
    )
    chats.set(id, chat)
    save()
    return HttpResponse.json(answer)
  }),

  // Technical Plan, section 7.2: everything is deleted at once.
  http.delete('/api/v1/documents/:id', ({ params, request }) => {
    if (!hasCsrfToken(request)) return error(request, 419, 'csrf_token_mismatch')
    const owner = ownerOf(request)
    if (!owner) return error(request, 400, 'invalid_guest_token')

    const document = documents.get(String(params.id))
    if (!document || document.owner !== owner) return error(request, 404, 'document_not_found')

    documents.delete(String(params.id))
    chats.delete(String(params.id))
    save()
    return new HttpResponse(null, { status: 204 })
  }),
]

// MVP 8: 3 questions per document for guests, 20 for signed-in users.
function questionLimit() {
  return currentUser ? 20 : 3
}

// Where a document is in its processing, from the time since upload: one stage every SECONDS_PER_STEP.
// Rejected after classifying, failed while extracting (Technical Plan, section 5).
// Section 6: status_detail is a code.
function statusOf(document: FakeDocument): { status: DocumentStatus; status_detail: string | null } {
  const step = Math.floor((Date.now() - document.startedAt) / 1000 / SECONDS_PER_STEP)
  if (document.outcome === 'rejected' && step >= FLOW.indexOf('classifying')) {
    return { status: 'rejected', status_detail: 'not_employment_document' }
  }
  if (document.outcome === 'failed' && step >= FLOW.indexOf('extracting')) {
    return { status: 'failed', status_detail: 'file_unreadable' }
  }
  if (document.outcome === 'paused' && step >= FLOW.indexOf('analyzing')) {
    return { status: 'analyzing', status_detail: 'ai_budget_reached' }
  }
  return { status: FLOW[Math.min(step, FLOW.length - 1)], status_detail: null }
}
