import { CircleCheck, Info, OctagonAlert, TriangleAlert, type LucideIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import type { Finding, FindingSeverity } from '../types'

interface Props {
  findings: Finding[]
  // The title and explanation are in the summary language, which may differ from the page.
  language: 'ar' | 'en'
}

// The severity is never shown by colour alone (card): each one has its own icon and its own word.
// Amber for attention, as on the landing page (decision 40).
const SEVERITY: Record<FindingSeverity, { icon: LucideIcon; className: string }> = {
  high: { icon: OctagonAlert, className: 'bg-destructive/10 text-destructive' },
  attention: { icon: TriangleAlert, className: 'bg-amber-50 text-amber-800' },
  info: { icon: Info, className: 'bg-tint text-teal-deep' },
}

// MVP 7: the attention points, each with its category, severity, "why it matters" and page.
// They come sorted high first from the backend, and are shown in that order.
export function FindingsList({ findings, language }: Props) {
  const { t } = useTranslation()

  return (
    <section aria-labelledby="findings-title">
      <h2 id="findings-title" className="text-lg font-semibold">
        {t('result.findings.title')}
      </h2>
      {findings.length === 0 ? (
        <p className="mt-4 flex items-start gap-2 rounded-xl border bg-background p-5 text-muted-foreground">
          <CircleCheck aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-teal-deep" />
          {t('result.findings.none')}
        </p>
      ) : (
        <ul className="mt-4 flex flex-col gap-4">
          {findings.map((finding, index) => {
            const { icon: Icon, className } = SEVERITY[finding.severity]
            return (
              // The answer has no id for a finding; the list is read once and never reordered.
              <li key={index} className="rounded-xl border bg-background p-5">
                <div className="flex flex-wrap items-center gap-2 text-sm">
                  <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-semibold', className)}>
                    <Icon aria-hidden="true" className="size-4" />
                    {t(`result.findings.severity.${finding.severity}`)}
                  </span>
                  <span className="text-muted-foreground">
                    {t(`result.findings.category.${finding.category}`, {
                      defaultValue: t('result.findings.category.other'),
                    })}
                  </span>
                </div>
                <h3 lang={language} dir="auto" className="mt-3 font-semibold">
                  {finding.title}
                </h3>
                <p lang={language} dir="auto" className="mt-1 leading-relaxed text-muted-foreground">
                  {finding.explanation}
                </p>
                {finding.quote ? (
                  <blockquote dir="auto" className="mt-3 border-s-2 border-tint ps-3 text-sm text-muted-foreground">
                    {finding.quote}
                  </blockquote>
                ) : null}
                {finding.page ? (
                  <p className="mt-1 text-xs text-muted-foreground">{t('result.page', { page: finding.page })}</p>
                ) : null}
              </li>
            )
          })}
        </ul>
      )}
    </section>
  )
}
