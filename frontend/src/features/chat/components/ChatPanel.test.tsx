import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, expect, it, vi } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { renderWithProviders } from '@/test/renderWithProviders'
import type { ChatHistory } from '../types'
import { ChatPanel } from './ChatPanel'

const EMPTY: ChatHistory = { messages: [], remaining_questions: 3 }

// The visitor is a guest. GET /documents/d1/chat answers with `history`, and POST answers with `answer`.
function chatAnswers(history: ChatHistory, answer: { body: unknown; status?: number }) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation(async (input, init) => {
    const url = String(input)
    if (url === '/api/v1/me') return Response.json({ error: { code: 'unauthorized', message: '' } }, { status: 401 })
    if (url === '/api/v1/documents/d1/chat') {
      return init?.method === 'POST' ? Response.json(answer.body, { status: answer.status ?? 200 }) : Response.json(history)
    }
    return new Response(null, { status: 204 })
  })
}

async function ask(question: string) {
  const user = userEvent.setup()
  await user.type(await screen.findByLabelText(ar.chat.inputLabel), question)
  await user.click(screen.getByRole('button', { name: ar.chat.send }))
}

afterEach(() => {
  vi.restoreAllMocks()
})

it('shows the answer with its citations (page and quote), and the questions left from the backend', async () => {
  const fetch = chatAnswers(EMPTY, {
    body: {
      answer: 'فترة الإشعار 30 يومًا.',
      citations: [{ page: 7, quote: 'قبل ثلاثين يومًا' }],
      answer_found: true,
      remaining_questions: 2,
    },
  })
  renderWithProviders(<ChatPanel documentId="d1" />)
  expect(await screen.findByText('بقي لك 3 أسئلة')).toBeInTheDocument()

  await ask('ما مدة الإشعار؟')

  const answer = (await screen.findByText('فترة الإشعار 30 يومًا.')).closest('li') as HTMLElement
  const sources = within(within(answer).getByRole('list', { name: ar.chat.sources }))
  expect(sources.getByText('صفحة 7')).toBeInTheDocument()
  expect(sources.getByText('قبل ثلاثين يومًا')).toBeInTheDocument()
  expect(screen.getByText('بقي لك سؤالان')).toBeInTheDocument()
  // The question box is empty again after the answer.
  expect(screen.getByLabelText(ar.chat.inputLabel)).toHaveValue('')

  const [, init] = fetch.mock.calls.find(([, options]) => options?.method === 'POST') ?? []
  expect(JSON.parse(String(init?.body))).toEqual({ question: 'ما مدة الإشعار؟' })
})

it('says clearly when the answer is not in the document', async () => {
  chatAnswers(EMPTY, {
    body: { answer: 'لم أجد إجابة.', citations: [], answer_found: false, remaining_questions: 2 },
  })
  renderWithProviders(<ChatPanel documentId="d1" />)
  await ask('هل يوجد بدل طعام؟')
  const answer = (await screen.findByText('لم أجد إجابة.')).closest('li') as HTMLElement
  expect(within(answer).getByText(ar.chat.notFound)).toBeInTheDocument()
})

it('asks a suggested question with one tap', async () => {
  chatAnswers(EMPTY, { body: { answer: '...', citations: [], answer_found: true, remaining_questions: 2 } })
  renderWithProviders(<ChatPanel documentId="d1" />)
  await userEvent.click(await screen.findByRole('button', { name: ar.chat.suggested.noticePeriod }))
  expect(await screen.findByText(ar.chat.suggested.noticePeriod, { selector: 'p' })).toBeInTheDocument()
})

it('stops asking when no question is left, and invites a guest to sign in', async () => {
  chatAnswers({ messages: [], remaining_questions: 0 }, { body: {} })
  renderWithProviders(<ChatPanel documentId="d1" />)
  expect(await screen.findByText(ar.chat.noneLeft)).toBeInTheDocument()
  expect(screen.getByRole('link', { name: ar.chat.signInForMore })).toBeInTheDocument()
  expect(screen.getByLabelText(ar.chat.inputLabel)).toBeDisabled()
})

it('shows question_limit_reached and stops asking', async () => {
  chatAnswers(EMPTY, {
    body: { error: { code: 'question_limit_reached', message: 'وصلت إلى حد الأسئلة.' } },
    status: 429,
  })
  renderWithProviders(<ChatPanel documentId="d1" />)
  await ask('سؤال')
  expect(await screen.findByText(ar.chat.noneLeft)).toBeInTheDocument()
  expect(screen.getByLabelText(ar.chat.inputLabel)).toBeDisabled()
})

it('shows ai_budget_reached in the chat and keeps the question in the box', async () => {
  chatAnswers(EMPTY, {
    body: { error: { code: 'ai_budget_reached', message: 'توقفت الأسئلة لهذا اليوم.' } },
    status: 429,
  })
  renderWithProviders(<ChatPanel documentId="d1" />)
  await ask('ما مدة الإشعار؟')
  expect(await screen.findByRole('alert')).toHaveTextContent('توقفت الأسئلة لهذا اليوم.')
  expect(screen.getByLabelText(ar.chat.inputLabel)).toHaveValue('ما مدة الإشعار؟')
  // The question was not counted: the questions left did not change.
  expect(screen.getByText('بقي لك 3 أسئلة')).toBeInTheDocument()
})
