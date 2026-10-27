import { useTranslation } from 'react-i18next'
import { PageRef } from '@/features/viewer/components/PageRef'
import type { Term } from '../types'
import { TERM_LAYOUT } from '../utils/termGroups'
import { TermValue } from './TermValue'

// The key-term cards, grouped by area (MVP 5, step 4). Every found value shows its source page and quote,
// so the user can check it in the document (MVP 6).
export function TermCards({ terms }: { terms: Term[] }) {
  const { t } = useTranslation()

  return (
    <section aria-labelledby="terms-title">
      <h2 id="terms-title" className="text-lg font-semibold">
        {t('result.termsTitle')}
      </h2>
      {/* Beside the document viewer (lg) one column is narrow, so the cards stack there. */}
      <div className="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
        {TERM_LAYOUT.map((cell) => (
          <div key={cell[0].id} className="flex flex-col gap-4">
            {cell.map((group) => (
              <section
                key={group.id}
                aria-labelledby={`group-${group.id}`}
                // The last card of a cell grows to the cell's height, so the cards of a row end on one line.
                className="rounded-xl border bg-background p-5 last:grow"
              >
                <h3 id={`group-${group.id}`} className="font-semibold text-teal-deep">
                  {t(`result.groups.${group.id}`)}
                </h3>
                <dl className="mt-3 divide-y">
                  {group.keys.map((key) => (
                    <TermRow key={key} name={key} term={terms.find((item) => item.key === key)} />
                  ))}
                </dl>
              </section>
            ))}
          </div>
        ))}
      </div>
    </section>
  )
}

function TermRow({ name, term }: { name: string; term?: Term }) {
  const { t } = useTranslation()

  return (
    <div className="py-3 first:pt-0 last:pb-0">
      <dt className="text-sm text-muted-foreground">{t(`result.terms.${name}`)}</dt>
      <dd className="mt-1">
        <TermValue term={term} />
        {term?.found && term.quote ? (
          // The quote is in the language of the document, which may differ from the page.
          <blockquote dir="auto" className="mt-2 border-s-2 border-tint ps-3 text-sm text-muted-foreground">
            {term.quote}
          </blockquote>
        ) : null}
        {term?.found && term.page ? (
          <p className="mt-1">
            <PageRef page={term.page} />
          </p>
        ) : null}
      </dd>
    </div>
  )
}
