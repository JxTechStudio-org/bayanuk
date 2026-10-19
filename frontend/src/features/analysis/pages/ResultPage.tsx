import { CircleCheck } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { CardLayout } from '@/features/shared/components/CardLayout'

// Placeholder: the result dashboard is a later task. Processing ends here when the document is ready.
export function ResultPage() {
  const { t } = useTranslation()

  return (
    <CardLayout>
      <span className="grid size-12 place-items-center rounded-full bg-teal-deep text-white">
        <CircleCheck aria-hidden="true" className="size-6" />
      </span>
      <h1 className="mt-4 text-xl font-semibold md:text-2xl">{t('result.ready')}</h1>
    </CardLayout>
  )
}
