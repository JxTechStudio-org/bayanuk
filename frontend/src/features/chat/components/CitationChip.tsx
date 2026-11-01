import { PageRef } from '@/features/viewer/components/PageRef'
import type { Citation } from '../types'

// MVP 8: "Every answer cites at least one page and a short quote. Clicking the citation opens the viewer on that page."
// The quote is in the language of the document.
export function CitationChip({ citation }: { citation: Citation }) {
  return (
    <li className="rounded-lg border bg-background px-3 py-2 text-sm">
      <PageRef page={citation.page} />
      {/* As the quotes of the term cards: no quotation marks, which would face the wrong way in the other language. */}
      <p dir="auto" className="mt-1 text-muted-foreground">
        {citation.quote}
      </p>
    </li>
  )
}
