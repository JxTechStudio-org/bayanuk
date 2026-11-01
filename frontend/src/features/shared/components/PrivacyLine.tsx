import { ShieldCheck } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'

// The privacy line from the landing page (MVP 9: "The product must make privacy visible, not just promise it"),
// on the pages about an uploaded document: processing and result.
export function PrivacyLine({ className }: { className?: string }) {
  const { t } = useTranslation()

  return (
    <p className={cn('flex items-start gap-2 text-sm text-muted-foreground', className)}>
      <ShieldCheck aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-teal-deep" />
      {t('hero.privacy')}
    </p>
  )
}
