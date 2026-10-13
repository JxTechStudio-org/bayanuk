import { CircleCheck } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { AppHeader } from '@/features/shared/components/AppHeader'

// Placeholder: the result dashboard is a later task. Processing ends here when the document is ready.
export function ResultPage() {
  const { t } = useTranslation()

  return (
    <div className="flex min-h-svh flex-col">
      <AppHeader />
      <main className="flex-1 bg-linear-to-b from-muted to-background px-5 py-12 md:py-20">
        <div className="mx-auto w-full max-w-lg rounded-2xl border bg-background p-6 shadow-sm md:p-8">
          <span className="grid size-12 place-items-center rounded-full bg-teal-deep text-white">
            <CircleCheck aria-hidden="true" className="size-6" />
          </span>
          <h1 className="mt-4 text-xl font-semibold md:text-2xl">{t('result.ready')}</h1>
        </div>
      </main>
    </div>
  )
}
