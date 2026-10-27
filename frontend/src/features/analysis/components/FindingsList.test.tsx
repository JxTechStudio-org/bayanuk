import { render, screen, within } from '@testing-library/react'
import { afterEach, expect, it } from 'vitest'
import i18n from '@/app/i18n'
import ar from '@/locales/ar.json'
import en from '@/locales/en.json'
import type { Finding } from '../types'
import { FindingsList } from './FindingsList'

const FINDINGS: Finding[] = [
  {
    category: 'salary_mismatch',
    severity: 'high',
    title: 'الراتب لا يساوي مجموع البدلات',
    explanation: 'مجموع الراتب والبدلات لا يساوي الإجمالي المكتوب.',
    page: 3,
    quote: 'إجمالي الأجر الشهري عشرة آلاف ريال',
  },
  { category: 'non_compete', severity: 'attention', title: 'شرط عدم منافسة', explanation: '...', page: null, quote: null },
]

afterEach(async () => {
  await i18n.changeLanguage('ar')
})

it('shows each point with its severity in words, category, why it matters, quote and page', () => {
  render(<FindingsList findings={FINDINGS} language="ar" />)
  const [first, second] = screen.getAllByRole('listitem').map((item) => within(item))

  // The severity is a word, not only a colour (card).
  expect(first.getByText(ar.result.findings.severity.high)).toBeInTheDocument()
  expect(first.getByText(ar.result.findings.category.salary_mismatch)).toBeInTheDocument()
  expect(first.getByRole('heading', { name: 'الراتب لا يساوي مجموع البدلات' })).toHaveAttribute('lang', 'ar')
  expect(first.getByText('مجموع الراتب والبدلات لا يساوي الإجمالي المكتوب.')).toBeInTheDocument()
  expect(first.getByText('إجمالي الأجر الشهري عشرة آلاف ريال')).toBeInTheDocument()
  expect(first.getByText('صفحة 3')).toBeInTheDocument()

  expect(second.getByText(ar.result.findings.severity.attention)).toBeInTheDocument()
  // No page or quote: nothing is shown for them.
  expect(second.queryByText(/صفحة/)).not.toBeInTheDocument()
})

it('keeps the backend order (high first)', () => {
  render(<FindingsList findings={FINDINGS} language="ar" />)
  const titles = screen.getAllByRole('heading', { level: 3 }).map((heading) => heading.textContent)
  expect(titles).toEqual(['الراتب لا يساوي مجموع البدلات', 'شرط عدم منافسة'])
})

it('says so when there is no point, in English too', async () => {
  await i18n.changeLanguage('en')
  render(<FindingsList findings={[]} language="ar" />)
  expect(screen.getByText(en.result.findings.none)).toBeInTheDocument()
})

it('shows "Other" for a category this version does not know', () => {
  const unknown = { ...FINDINGS[1], category: 'new_category' } as unknown as Finding
  render(<FindingsList findings={[unknown]} language="ar" />)
  expect(screen.getByText(ar.result.findings.category.other)).toBeInTheDocument()
})

// MVP 7, wording rule: Bayanuk never says a clause is "illegal" or "unfair". None of our texts does.
it('never calls a clause illegal or unfair', () => {
  const texts = JSON.stringify([ar, en])
  for (const word of ['illegal', 'unfair', 'غير قانوني', 'غير عادل', 'مخالف']) {
    expect(texts.toLowerCase()).not.toContain(word)
  }
})
