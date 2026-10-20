import { FileText } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { CardLayout } from '@/features/shared/components/CardLayout'

// Placeholder: the documents list (GET /documents, integration contract section 7) is a later task.
// The page exists now so the sign-in task can protect it.
export function MyDocumentsPage() {
  const { t } = useTranslation()

  return (
    <CardLayout privacyLine={false}>
      <span className="grid size-12 place-items-center rounded-full bg-tint text-teal-deep">
        <FileText aria-hidden="true" className="size-6" />
      </span>
      <h1 className="mt-4 text-xl font-semibold md:text-2xl">{t('account.documents')}</h1>
      <Button asChild size="lg" className="mt-6 h-12 px-6 text-base font-semibold">
        <Link to="/">{t('account.upload')}</Link>
      </Button>
    </CardLayout>
  )
}
