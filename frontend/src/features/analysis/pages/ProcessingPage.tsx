import { CircleAlert, FileX } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link, Navigate, useParams } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { AppHeader } from '@/features/shared/components/AppHeader'
import { ProcessingStages } from '../components/ProcessingStages'
import { useDocumentStatus } from '../hooks/useDocumentStatus'
import { stageIndex } from '../utils/stages'

// Shown right after upload. It polls the status and ends in one of three states:
// ready (go to the result), rejected (not a supported document), or failed (could not be read).
export function ProcessingPage() {
  const { id = '' } = useParams()
  const { t } = useTranslation()
  const { data, isError } = useDocumentStatus(id)

  if (data?.status === 'ready') return <Navigate to={`/d/${id}/result`} replace />

  const ended = isError || data?.status === 'rejected' || data?.status === 'failed'
  const outcome = isError ? 'notFound' : data?.status === 'rejected' ? 'rejected' : 'failed'
  const body = data?.status_detail || t(`processing.${outcome}.body`)

  return (
    <>
      <AppHeader />
      <main className="mx-auto w-full max-w-xl px-5 py-16">
        {ended ? (
          <div role="alert" className="rounded-2xl border p-6">
            {outcome === 'rejected' ? (
              <FileX aria-hidden="true" className="size-8 text-muted-foreground" />
            ) : (
              <CircleAlert aria-hidden="true" className="size-8 text-destructive" />
            )}
            <h1 className="mt-3 text-xl font-semibold">{t(`processing.${outcome}.title`)}</h1>
            {/* The API sends a user-safe reason (status_detail); our own text is the fallback. */}
            {body ? <p className="mt-2 text-muted-foreground">{body}</p> : null}
            <Button asChild size="lg" className="mt-6">
              <Link to="/">{t(`processing.${outcome}.action`)}</Link>
            </Button>
          </div>
        ) : (
          <div className="rounded-2xl border p-6">
            <h1 className="text-xl font-semibold">{t('processing.title')}</h1>
            <div className="mt-6" aria-live="polite">
              <ProcessingStages current={data ? stageIndex(data.status) : 0} />
            </div>
          </div>
        )}
      </main>
    </>
  )
}
