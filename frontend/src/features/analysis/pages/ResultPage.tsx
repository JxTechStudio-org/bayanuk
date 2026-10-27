import { CircleCheck } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link, Navigate, useParams } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { SaveInvite } from '@/features/auth/components/SaveInvite'
import { CardLayout } from '@/features/shared/components/CardLayout'
import { PageMessage } from '@/features/shared/components/PageMessage'
import { ApiError } from '@/lib/api'
import { useDocument } from '../hooks/useDocument'

// The result of a document (integration contract, section 13).
// While loading it says so. A missing document (404) and a deleted one (410) get the same messages
// as on the processing page. A document that is not ready yet goes back to the processing page.
export function ResultPage() {
  const { id = '' } = useParams()
  const { t } = useTranslation()
  const { data, error, isPending, refetch, isRefetching } = useDocument(id)

  if (isPending) {
    return (
      <CardLayout>
        <PageMessage title={t('result.loading')} waiting />
      </CardLayout>
    )
  }

  // An error after the result was shown (a check when the tab is opened again, for example) keeps the result.
  if (!data) {
    const status = error instanceof ApiError ? error.status : null
    const gone = status === 404 ? 'notFound' : status === 410 ? 'expired' : null
    return (
      <CardLayout>
        {gone ? (
          <PageMessage
            title={t(`processing.${gone}.title`)}
            action={
              <Button asChild size="lg" className="h-12 px-6 text-base font-semibold">
                <Link to="/">{t(`processing.${gone}.action`)}</Link>
              </Button>
            }
          />
        ) : (
          // A lost connection or a server error: the page can be loaded again.
          <PageMessage
            title={t('result.error')}
            action={
              <Button
                size="lg"
                className="h-12 px-6 text-base font-semibold"
                disabled={isRefetching}
                onClick={() => refetch()}
              >
                {t('result.retry')}
              </Button>
            }
          />
        )}
      </CardLayout>
    )
  }

  if (!data.analysis) return <Navigate to={`/d/${id}`} replace />

  return (
    <CardLayout>
      <span className="grid size-12 place-items-center rounded-full bg-teal-deep text-white">
        <CircleCheck aria-hidden="true" className="size-6" />
      </span>
      <h1 className="mt-4 text-xl font-semibold md:text-2xl">{t('result.ready')}</h1>
      <SaveInvite />
    </CardLayout>
  )
}
