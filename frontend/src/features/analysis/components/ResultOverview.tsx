import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useFormatTerm } from '../hooks/useFormatTerm'
import type { DocumentType, Term } from '../types'

interface Props {
  documentType: DocumentType
  terms: Term[]
}

// MVP 5, step 4: the overview at the top of the result. Document type, contract type, duration, probation,
// notice period and total monthly pay. Each value shows its page; the term cards below show the quote.
export function ResultOverview({ documentType, terms }: Props) {
  const { t } = useTranslation()
  const format = useFormatTerm()
  // A term missing from the list is treated as not found.
  const term = (key: string) => terms.find((item) => item.key === key)
  const employeeNotice = term('notice.employee_days')
  const employerNotice = term('notice.employer_days')

  return (
    <section aria-labelledby="overview-title">
      <h2 id="overview-title" className="text-lg font-semibold">
        {t('result.overview.title')}
      </h2>
      <dl className="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3">
        <Item label={t('result.overview.documentType')}>
          <Value>{t(`result.documentTypes.${documentType}`)}</Value>
        </Item>
        <TermItem label={t('result.overview.contractType')} term={term('contract.type')} format={format} />
        <TermItem label={t('result.overview.duration')} term={term('contract.duration_months')} format={format} />
        <TermItem label={t('result.overview.probation')} term={term('probation_days')} format={format} />
        {/* MVP 6: the notice period of the employee and of the employer, if different. */}
        {employerNotice?.found && employerNotice.value !== employeeNotice?.value ? (
          <Item label={t('result.overview.notice')}>
            <Value>
              {t('result.overview.noticeEmployee', {
                value: employeeNotice?.found ? format(employeeNotice) : t('result.notFound'),
              })}
            </Value>
            <Value>{t('result.overview.noticeEmployer', { value: format(employerNotice) })}</Value>
          </Item>
        ) : (
          <TermItem label={t('result.overview.notice')} term={employeeNotice} format={format} />
        )}
        <TermItem label={t('result.overview.totalMonthly')} term={term('compensation.total_monthly')} format={format} />
      </dl>
    </section>
  )
}

function Item({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="rounded-xl border bg-background p-4">
      <dt className="text-sm text-muted-foreground">{label}</dt>
      <dd className="mt-1">{children}</dd>
    </div>
  )
}

function Value({ children }: { children: ReactNode }) {
  return <p className="font-semibold">{children}</p>
}

// MVP 6: a value that is not found says so, never guessed or left empty.
function TermItem({ label, term, format }: { label: string; term?: Term; format: (term: Term) => string }) {
  const { t } = useTranslation()
  return (
    <Item label={label}>
      {term?.found ? (
        <>
          {/* dir="auto": a value in the other language (an English name on the Arabic page) keeps its direction. */}
          <p dir="auto" className="font-semibold">
            {format(term)}
          </p>
          {term.page ? <p className="mt-1 text-xs text-muted-foreground">{t('result.page', { page: term.page })}</p> : null}
        </>
      ) : (
        <p className="text-muted-foreground">{t('result.notFound')}</p>
      )}
    </Item>
  )
}
