import { useTranslation } from 'react-i18next'
import { Link, Navigate, useParams } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { SaveInvite } from '@/features/auth/components/SaveInvite'
import { AppHeader } from '@/features/shared/components/AppHeader'
import { CardLayout } from '@/features/shared/components/CardLayout'
import { PageMessage } from '@/features/shared/components/PageMessage'
import { ApiError } from '@/lib/api'
import { ResultOverview } from '../components/ResultOverview'
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

  // MVP 5, step 4: the overview first, then the term cards and the summary.
  return (
    <div className="flex min-h-svh flex-col">
      <AppHeader />
      <main className="flex-1 bg-linear-to-b from-muted to-background px-5 py-10 md:py-14">
        <div className="mx-auto w-full max-w-5xl">
          <h1 className="text-2xl font-semibold md:text-3xl">{t('result.title')}</h1>
          <SaveInvite />
          <div className="mt-8">
            <ResultOverview documentType={data.document_type} terms={data.analysis.terms} />
          </div>
        </div>
      </main>
    </div>
  )
}
