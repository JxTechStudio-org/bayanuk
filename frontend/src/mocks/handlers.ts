import { delay, http, HttpResponse } from 'msw'
import type { DocumentStatus } from '@/features/analysis/types'

// A fake API that follows the contract in the Technical Plan (section 7) and the status flow
// in the Database Schema. Only used in development, when VITE_API_MOCK=true.
//
// Pick the outcome by file name: a name with "reject" ends as rejected, "fail" ends as failed.
const FLOW: DocumentStatus[] = ['uploaded', 'scanning', 'extracting', 'classifying', 'analyzing', 'indexing', 'ready']
const SECONDS_PER_STEP = 2

interface FakeDocument {
  startedAt: number
  outcome: 'ready' | 'rejected' | 'failed'
}

const documents = new Map<string, FakeDocument>()

export const handlers = [
  http.post('/api/v1/documents', async ({ request }) => {
    const form = await request.formData()
    const file = form.get('file')
    const name = file instanceof File ? file.name.toLowerCase() : ''
    // Document IDs are UUIDs (Technical Plan, section 8).
    const id = crypto.randomUUID()
    documents.set(id, {
      startedAt: Date.now(),
      outcome: name.includes('reject') ? 'rejected' : name.includes('fail') ? 'failed' : 'ready',
    })
    await delay(600)
    return HttpResponse.json({ id, status: 'uploaded' }, { status: 202 })
  }),

  http.get('/api/v1/documents/:id/status', ({ params }) => {
    const document = documents.get(String(params.id))
    if (!document) {
      return HttpResponse.json({ error: { code: 'not_found', message: '' } }, { status: 404 })
    }
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
