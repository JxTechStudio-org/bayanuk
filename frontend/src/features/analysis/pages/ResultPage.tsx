import { useTranslation } from 'react-i18next'
import { AppHeader } from '@/features/shared/components/AppHeader'

// Placeholder: the result dashboard is a later task. Processing ends here when the document is ready.
export function ResultPage() {
  const { t } = useTranslation()

  return (
    <>
      <AppHeader />
      <main className="mx-auto w-full max-w-xl px-5 py-16">
        <h1 className="text-xl font-semibold">{t('result.ready')}</h1>
      </main>
    </>
  )
}
