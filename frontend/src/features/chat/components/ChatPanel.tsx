import { LoaderCircle, SearchX, Send } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useLocation } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { useCurrentUser } from '@/features/auth/hooks/useCurrentUser'
import { ApiError, errorMessage } from '@/lib/api'
import { cn } from '@/lib/utils'
import { useAskQuestion } from '../hooks/useAskQuestion'
import { useChat } from '../hooks/useChat'
import type { ChatMessage } from '../types'
import { CitationChip } from './CitationChip'
import { SuggestedQuestions } from './SuggestedQuestions'

// MVP 8, "Ask your document": answers come from the document only, with the page and a quote.
// The questions left come from the backend (contract, section 8). The question stays in the box
// until it is answered, so after an error (the daily AI budget, for example) it can be sent again.
export function ChatPanel({ documentId }: { documentId: string }) {
  const { t } = useTranslation()
  const { pathname } = useLocation()
  const { data: user } = useCurrentUser()
  const chat = useChat(documentId)
  const ask = useAskQuestion(documentId)
  const [question, setQuestion] = useState('')

  const noneLeft = chat.data?.remaining_questions === 0
  const canAsk = Boolean(chat.data) && !noneLeft && !ask.isPending

  function send(text: string) {
    const trimmed = text.trim()
    if (!trimmed || !canAsk) return
    setQuestion(trimmed)
    // After the answer the box is emptied, unless a new question was typed while waiting.
    ask.mutate(trimmed, { onSuccess: () => setQuestion((current) => (current.trim() === trimmed ? '' : current)) })
  }

  function onSubmit(event: FormEvent) {
    event.preventDefault()
    send(question)
  }

  // The limit error is shown by the "none left" line below; any other error shows its message.
  const askError =
    ask.isError && !(ask.error instanceof ApiError && ask.error.code === 'question_limit_reached')
      ? errorMessage(ask.error, t('chat.errors.generic'))
      : null

  return (
    <section aria-labelledby="chat-title" className="rounded-xl border bg-background p-5 md:p-6">
      <h2 id="chat-title" className="text-lg font-semibold">
        {t('chat.title')}
      </h2>
      <p className="mt-1 text-sm text-muted-foreground">{t('chat.intro')}</p>

      {chat.isPending ? (
        <p role="status" className="mt-4 flex items-center gap-2 text-muted-foreground">
          <LoaderCircle aria-hidden="true" className="size-4 animate-spin motion-reduce:animate-none" />
          {t('chat.loading')}
        </p>
      ) : chat.isError ? (
        <div role="alert" className="mt-4 text-sm">
          <p className="text-destructive">{t('chat.errors.history')}</p>
          <Button variant="outline" className="mt-2 h-9 px-3" onClick={() => chat.refetch()}>
            {t('result.retry')}
          </Button>
        </div>
      ) : (
        <>
          <ol aria-live="polite" className="mt-4 flex flex-col gap-3">
            {chat.data.messages.map((message, index) => (
              // Messages have no id in the answer; the history only grows at the end.
              <Message key={index} message={message} />
            ))}
            {ask.isPending ? (
              <>
                {/* The question that was sent, even if the box is edited while waiting. */}
                <Message
                  message={{ role: 'user', content: ask.variables ?? '', citations: [], answer_found: null, created_at: '' }}
                />
                <li role="status" className="flex items-center gap-2 text-sm text-muted-foreground">
                  <LoaderCircle aria-hidden="true" className="size-4 animate-spin motion-reduce:animate-none" />
                  {t('chat.thinking')}
                </li>
              </>
            ) : null}
          </ol>

          {chat.data.messages.length === 0 && !ask.isPending ? (
            <div className="mt-4">
              <SuggestedQuestions onAsk={send} disabled={!canAsk} />
            </div>
          ) : null}

          {askError ? (
            <p role="alert" className="mt-4 text-sm text-destructive">
              {askError}
            </p>
          ) : null}

          {noneLeft ? (
            <div role="status" className="mt-4 rounded-lg bg-muted p-3 text-sm">
              <p>{t('chat.noneLeft')}</p>
              {/* MVP 4.2: guests sign in to "chat longer" (3 questions for guests, 20 for signed-in users). */}
              {user ? null : (
                <Link
                  to={`/sign-in?next=${encodeURIComponent(pathname)}`}
                  className="mt-1 inline-block font-semibold text-teal-deep underline underline-offset-4"
                >
                  {t('chat.signInForMore')}
                </Link>
              )}
            </div>
          ) : (
            <p className="mt-4 text-sm text-muted-foreground">
              {t('chat.remaining', { count: chat.data.remaining_questions })}
            </p>
          )}

          <form onSubmit={onSubmit} className="mt-2 flex items-end gap-2">
            <label htmlFor="chat-question" className="sr-only">
              {t('chat.inputLabel')}
            </label>
            <textarea
              id="chat-question"
              dir="auto"
              rows={2}
              value={question}
              disabled={noneLeft}
              placeholder={t('chat.placeholder')}
              onChange={(event) => setQuestion(event.target.value)}
              onKeyDown={(event) => {
                // Enter sends; Shift + Enter starts a new line.
                if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
                  event.preventDefault()
                  send(question)
                }
              }}
              className="min-h-12 flex-1 resize-none rounded-lg border bg-background px-3 py-2 outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:opacity-50"
            />
            <Button type="submit" className="h-12 px-4" disabled={!canAsk || !question.trim()}>
              <Send aria-hidden="true" className="rtl:-scale-x-100" />
              <span className="sr-only sm:not-sr-only">{t('chat.send')}</span>
            </Button>
          </form>
        </>
      )}
    </section>
  )
}

// MVP 8: the reply language follows the question, so each message keeps its own direction.
// An answer that is not in the document says so clearly.
function Message({ message }: { message: ChatMessage }) {
  const { t } = useTranslation()
  const mine = message.role === 'user'

  return (
    <li className={cn('max-w-[85%] rounded-xl px-4 py-3', mine ? 'self-end bg-tint' : 'self-start border bg-muted/50')}>
      <span className="sr-only">{t(mine ? 'chat.you' : 'chat.bayanuk')}: </span>
      {message.answer_found === false ? (
        <p className="mb-1 flex items-center gap-1.5 text-sm font-semibold text-muted-foreground">
          <SearchX aria-hidden="true" className="size-4" />
          {t('chat.notFound')}
        </p>
      ) : null}
      <p dir="auto" className="whitespace-pre-line">
        {message.content}
      </p>
      {message.citations.length > 0 ? (
        <ul aria-label={t('chat.sources')} className="mt-2 flex flex-col gap-1.5">
          {message.citations.map((citation, index) => (
            <CitationChip key={index} citation={citation} />
          ))}
        </ul>
      ) : null}
    </li>
  )
}
