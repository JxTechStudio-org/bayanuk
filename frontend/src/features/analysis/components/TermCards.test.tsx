import { render, screen, within } from '@testing-library/react'
import { afterEach, expect, it } from 'vitest'
import i18n from '@/app/i18n'
import ar from '@/locales/ar.json'
import en from '@/locales/en.json'
import type { Term } from '../types'
import { TermCards } from './TermCards'

const TERMS: Term[] = [
  { key: 'compensation.basic_salary', found: true, value: 8000, unit: 'SAR', page: 3, quote: 'الراتب الأساسي ثمانية آلاف ريال' },
  { key: 'benefits.medical_insurance', found: true, value: 'yes', page: 5, quote: 'تأمين طبي للموظف وعائلته' },
  { key: 'notice.employer_days', found: false },
]

// The row of one term, found by its label.
function row(label: string) {
  return within(screen.getByText(label).closest('div') as HTMLElement)
}

afterEach(async () => {
  await i18n.changeLanguage('ar')
})

it('shows each found value with its page and quote, right to left in Arabic', () => {
  render(<TermCards terms={TERMS} />)
  expect(document.documentElement.dir).toBe('rtl')
  expect(screen.getByRole('heading', { name: ar.result.groups.compensation })).toBeInTheDocument()

  const salary = row(ar.result.terms.compensation.basic_salary)
  expect(salary.getByText(/8,000/)).toBeInTheDocument()
  expect(salary.getByText('صفحة 3')).toBeInTheDocument()
  expect(salary.getByText('الراتب الأساسي ثمانية آلاف ريال')).toBeInTheDocument()
  expect(row(ar.result.terms.benefits.medical_insurance).getByText(ar.result.values.yes)).toBeInTheDocument()
})

it('says "not found" without a page or quote, in Arabic', () => {
  render(<TermCards terms={TERMS} />)
  const notice = row(ar.result.terms.notice.employer_days)
  expect(notice.getByText(ar.result.notFound)).toBeInTheDocument()
  expect(notice.queryByText(/صفحة/)).not.toBeInTheDocument()
  // A term missing from the result is not found too.
  expect(row(ar.result.terms.exclusivity).getByText(ar.result.notFound)).toBeInTheDocument()
})

it('shows the same, left to right in English', async () => {
  await i18n.changeLanguage('en')
  render(<TermCards terms={TERMS} />)
  expect(document.documentElement.dir).toBe('ltr')

  const salary = row(en.result.terms.compensation.basic_salary)
  expect(salary.getByText(/SAR\s8,000/)).toBeInTheDocument()
  expect(salary.getByText('Page 3')).toBeInTheDocument()
  // The quote stays in the language of the document.
  expect(salary.getByText('الراتب الأساسي ثمانية آلاف ريال')).toBeInTheDocument()
  expect(row(en.result.terms.notice.employer_days).getByText(en.result.notFound)).toBeInTheDocument()
})
