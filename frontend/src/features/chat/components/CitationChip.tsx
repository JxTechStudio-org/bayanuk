import { useTranslation } from 'react-i18next'
import type { Citation } from '../types'

// MVP 8: "Every answer cites at least one page and a short quote."
// The quote is in the language of the document.
export function CitationChip({ citation }: { citation: Citation }) {
  const { t } = useTranslation()

  return (
    <li className="rounded-lg border bg-background px-3 py-2 text-sm">
      <p className="text-xs font-semibold text-teal-deep">{t('result.page', { page: citation.page })}</p>
      {/* As the quotes of the term cards: no quotation marks, which would face the wrong way in the other language. */}
      <p dir="auto" className="mt-1 text-muted-foreground">
        {citation.quote}
      </p>
    </li>
  )
}
