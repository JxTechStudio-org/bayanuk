import { CircleAlert, FileSearch, FileText, FileX } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link, Navigate, useLocation, useParams } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { ApiError } from '@/lib/api'
import { cn } from '@/lib/utils'
import { AnalysisLayout } from '../components/AnalysisLayout'
import { ProcessingStages } from '../components/ProcessingStages'
import { useDocumentStatus } from '../hooks/useDocumentStatus'
import { stageIndex } from '../utils/stages'

// Shown right after upload. It polls the status and ends in one of three states:
// ready (go to the result), rejected (not a supported document), or failed (could not be read).
export function ProcessingPage() {
  const { id = '' } = useParams()
  const { t } = useTranslation()
  const { data, error } = useDocumentStatus(id)
  // Sent by the upload (useUploadDocument). A page opened from a link has no name, and the line is hidden.
  const fileName = (useLocation().state as { fileName?: string } | null)?.fileName

  if (data?.status === 'ready') return <Navigate to={`/d/${id}/result`} replace />

  // Only a 404 means the document is not there. Other errors (a lost connection, for example)
  // keep the stages on screen, and the next check every 2 seconds tries again.
  const outcome =
    error instanceof ApiError && error.status === 404
      ? 'notFound'
      : data?.status === 'rejected' || data?.status === 'failed'
        ? data.status
        : null

  if (outcome) {
    // The API sends a user-safe reason (status_detail); our own text is the fallback.
    const body = data?.status_detail || t(`processing.${outcome}.body`)
    return (
      <AnalysisLayout>
        <div role="alert">
          <span
            className={cn(
              'grid size-12 place-items-center rounded-full',
              outcome === 'rejected' ? 'bg-muted text-muted-foreground' : 'bg-destructive/10 text-destructive',
            )}
          >
            {outcome === 'rejected' ? (
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

  return (
    <AnalysisLayout>
      <span className="grid size-12 place-items-center rounded-full bg-tint text-teal-deep">
        <FileSearch aria-hidden="true" className="size-6" />
      </span>
      <h1 className="mt-4 text-xl font-semibold md:text-2xl">{t('processing.title')}</h1>
      {fileName ? <FileName name={fileName} /> : null}
      <div className="mt-6" aria-live="polite">
        <ProcessingStages current={data ? stageIndex(data.status) : 0} />
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
