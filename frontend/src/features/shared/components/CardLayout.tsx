import { ShieldCheck } from 'lucide-react'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { AppHeader } from './AppHeader'

interface Props {
  children: ReactNode
  // The privacy line is about the uploaded document, so pages without one (sign-in) leave it out.
  privacyLine?: boolean
}

// The frame of the pages outside the landing page (processing, result, sign-in): the header,
// the soft background from the top of the landing page, one card for the content,
// and the privacy line from the landing page (MVP 9).
export function CardLayout({ children, privacyLine = true }: Props) {
  const { t } = useTranslation()

  return (
    <div className="flex min-h-svh flex-col">
      <AppHeader />
      <main className="flex-1 bg-linear-to-b from-muted to-background px-5 py-12 md:py-20">
        <div className="mx-auto w-full max-w-lg rounded-2xl border bg-background p-6 shadow-sm md:p-8">{children}</div>
        {privacyLine ? (
          <p className="mx-auto mt-6 flex max-w-lg items-start gap-2 text-sm text-muted-foreground">
            <ShieldCheck aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-teal-deep" />
            {t('hero.privacy')}
          </p>
        ) : null}
      </main>
    </div>
  )
}
