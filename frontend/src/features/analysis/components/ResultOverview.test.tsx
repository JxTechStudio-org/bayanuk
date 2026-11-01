import { render, screen, within } from '@testing-library/react'
import { afterEach, expect, it } from 'vitest'
import i18n from '@/app/i18n'
import ar from '@/locales/ar.json'
import en from '@/locales/en.json'
import type { Term } from '../types'
import { ResultOverview } from './ResultOverview'

const TERMS: Term[] = [
  { key: 'contract.type', found: true, value: 'fixed_term', page: 1, quote: '...' },
  { key: 'contract.duration_months', found: true, value: 12, unit: 'months', page: 1, quote: '...' },
  { key: 'probation_days', found: true, value: 90, unit: 'days', page: 2, quote: '...' },
  { key: 'notice.employee_days', found: true, value: 30, unit: 'days', page: 7, quote: '...' },
  { key: 'notice.employer_days', found: false },
  { key: 'compensation.total_monthly', found: true, value: 10000, unit: 'SAR', page: 3, quote: '...' },
]

// The box of one overview item, found by its label.
function item(label: string) {
  return within(screen.getByText(label).closest('div') as HTMLElement)
}

afterEach(async () => {
  await i18n.changeLanguage('ar')
})

it('shows each value in Arabic, with its page', () => {
  render(<ResultOverview documentType="employment_contract" terms={TERMS} />)
  expect(item(ar.result.overview.documentType).getByText(ar.result.documentTypes.employment_contract)).toBeInTheDocument()
  expect(item(ar.result.overview.contractType).getByText(ar.result.values.fixed_term)).toBeInTheDocument()
  expect(item(ar.result.overview.duration).getByText('12 شهرًا')).toBeInTheDocument()
  expect(item(ar.result.overview.probation).getByText('90 يومًا')).toBeInTheDocument()
  expect(item(ar.result.overview.probation).getByText('صفحة 2')).toBeInTheDocument()
  expect(item(ar.result.overview.totalMonthly).getByText(/10,000/)).toBeInTheDocument()
})

it('shows each value in English', async () => {
  await i18n.changeLanguage('en')
  render(<ResultOverview documentType="employment_contract" terms={TERMS} />)
  expect(item(en.result.overview.duration).getByText('12 months')).toBeInTheDocument()
  expect(item(en.result.overview.probation).getByText('90 days')).toBeInTheDocument()
  expect(item(en.result.overview.totalMonthly).getByText(/SAR\s10,000/)).toBeInTheDocument()
})

it('says "not found" for a missing value, never leaves it empty', () => {
  const terms = TERMS.map((term) => (term.key === 'probation_days' ? { key: term.key, found: false } : term))
  render(<ResultOverview documentType="job_offer" terms={terms} />)
  expect(item(ar.result.overview.probation).getByText(ar.result.notFound)).toBeInTheDocument()
})

it('shows both notice periods when they differ', () => {
  const terms = TERMS.map((term) =>
    term.key === 'notice.employer_days' ? { ...term, found: true, value: 60, unit: 'days', page: 7 } : term,
  )
  render(<ResultOverview documentType="employment_contract" terms={terms} />)
  expect(screen.getByText('أنت: 30 يومًا')).toBeInTheDocument()
  expect(screen.getByText('صاحب العمل: 60 يومًا')).toBeInTheDocument()
})
