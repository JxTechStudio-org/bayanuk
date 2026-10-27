import { useTranslation } from 'react-i18next'
import type { Term } from '../types'
import { TERM_GROUPS } from '../utils/termGroups'
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
      <div className="mt-4 grid items-start gap-4 md:grid-cols-2">
        {TERM_GROUPS.map((group) => (
          <section
            key={group.id}
            aria-labelledby={`group-${group.id}`}
            className="rounded-xl border bg-background p-5"
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
          <p className="mt-1 text-xs text-muted-foreground">{t('result.page', { page: term.page })}</p>
        ) : null}
      </dd>
    </div>
  )
}
