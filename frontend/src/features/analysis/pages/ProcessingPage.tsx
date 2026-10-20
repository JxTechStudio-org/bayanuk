import { CircleAlert, FileSearch, FileText, FileX, PauseCircle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link, Navigate, useLocation, useParams } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { ApiError } from '@/lib/api'
import { cn } from '@/lib/utils'
import { AnalysisLayout } from '../components/AnalysisLayout'
import { ProcessingNotice, type Notice } from '../components/ProcessingNotice'
import { ProcessingStages } from '../components/ProcessingStages'
import { useDocumentStatus } from '../hooks/useDocumentStatus'
import { stageIndex } from '../utils/stages'

// After this many failed checks in a row, the page says the connection is lost.
const FAILURES_BEFORE_OFFLINE_NOTICE = 3

// Shown right after upload. It polls the status and ends in one of three states:
// ready (go to the result), rejected (not a supported document), or failed (could not be read).
export function ProcessingPage() {
  const { id = '' } = useParams()
  const { t } = useTranslation()
  const { data, error, failureCount, failureReason, fetchStatus } = useDocumentStatus(id)
  // Sent by the upload (useUploadDocument). A page opened from a link has no name, and the line is hidden.
  const fileName = (useLocation().state as { fileName?: string } | null)?.fileName

  if (data?.status === 'ready') return <Navigate to={`/d/${id}/result`} replace />

  // Only a 404 (not found) or a 410 (deleted after the retention period) ends the page with an error.
  // Other errors (a lost connection, for example) keep the stages on screen, and the next check tries again.
  const errorStatus = error instanceof ApiError ? error.status : null
  const outcome =
    errorStatus === 404
      ? 'notFound'
      : errorStatus === 410
        ? 'expired'
        : data?.status === 'rejected' || data?.status === 'failed'
          ? data.status
          : null

  if (outcome) {
    // A rejected or deleted document is expected behavior, not an error, so it gets the calm style.
    const calm = outcome === 'rejected' || outcome === 'expired'
    // status_detail is a code, and each code has its own translated reason.
    // A code this version does not know yet shows the title only.
    const body = data?.status_detail ? t(`processing.reasons.${data.status_detail}`, { defaultValue: '' }) : null
    return (
      <AnalysisLayout>
        <div role="alert">
          <span
            className={cn(
              'grid size-12 place-items-center rounded-full',
              calm ? 'bg-muted text-muted-foreground' : 'bg-destructive/10 text-destructive',
            )}
          >
            {calm ? (
              <FileX aria-hidden="true" className="size-6" />
            ) : (
              <CircleAlert aria-hidden="true" className="size-6" />
            )}
          </span>
          <h1 className="mt-4 text-xl font-semibold md:text-2xl">{t(`processing.${outcome}.title`)}</h1>
          {fileName ? <FileName name={fileName} /> : null}
          {body ? <p className="mt-2 text-muted-foreground">{body}</p> : null}
          <Button asChild size="lg" className="mt-6 h-12 px-6 text-base font-semibold">
            <Link to="/">{t(`processing.${outcome}.action`)}</Link>
          </Button>
        </div>
      </AnalysisLayout>
    )
  }

  // Why nothing is moving yet, most important first. The checks keep running in every case:
  // the browser is offline (checks wait for the connection) or several checks failed to reach the API,
  // the daily AI budget paused the document, or the document waits in the queue.
  const offline =
    fetchStatus === 'paused' ||
    (failureReason instanceof ApiError && failureReason.status === 0 && failureCount >= FAILURES_BEFORE_OFFLINE_NOTICE)
  const paused = data?.status_detail === 'ai_budget_reached'
  const notice: Notice | null = offline
    ? 'offline'
    : paused
      ? 'paused'
      : data?.status === 'uploaded'
        ? 'queued'
        : null

  return (
    <AnalysisLayout>
      {/* A paused document says so in the title too, so the pause cannot be missed. */}
      {paused ? (
        <span className="grid size-12 place-items-center rounded-full bg-amber-50 text-amber-800">
          <PauseCircle aria-hidden="true" className="size-6" />
        </span>
      ) : (
        <span className="grid size-12 place-items-center rounded-full bg-tint text-teal-deep">
          <FileSearch aria-hidden="true" className="size-6" />
        </span>
      )}
      <h1 className="mt-4 text-xl font-semibold md:text-2xl">{t(paused ? 'processing.pausedTitle' : 'processing.title')}</h1>
      {fileName ? <FileName name={fileName} /> : null}
      <div className="mt-6" aria-live="polite">
        <ProcessingStages current={data ? stageIndex(data.status) : 0} paused={paused} />
        {notice ? <ProcessingNotice notice={notice} /> : null}
      </div>
    </AnalysisLayout>
  )
}

// Database Schema: the original file name is "shown to the owner only".
// dir="auto" keeps an English name readable on the Arabic page, and the reverse.
function FileName({ name }: { name: string }) {
  return (
    <p className="mt-2 flex items-center gap-2 text-sm text-muted-foreground">
      <FileText aria-hidden="true" className="size-4 shrink-0" />
      <span dir="auto" className="truncate">
        {name}
      </span>
    </p>
  )
}
