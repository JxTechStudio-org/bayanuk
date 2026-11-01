import { useTranslation } from 'react-i18next'
import { useFormatTerm } from '../hooks/useFormatTerm'
import type { Term } from '../types'

// The value of a term, or "not found" (MVP 6: a value that is not found is never guessed or left empty).
// A term missing from the result is not found too.
// dir="auto": a value in the other language (an English name on the Arabic page) keeps its direction.
export function TermValue({ term }: { term?: Term }) {
  const { t } = useTranslation()
  const format = useFormatTerm()

  if (!term?.found) return <p className="text-muted-foreground">{t('result.notFound')}</p>
  return (
    <p dir="auto" className="font-semibold">
      {format(term)}
    </p>
  )
}
