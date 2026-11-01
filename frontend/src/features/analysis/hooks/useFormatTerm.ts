import { useTranslation } from 'react-i18next'
import type { Term } from '../types'

// Terms whose value is a code the frontend translates (integration contract, section 13).
// A code this version does not know yet is shown as it is.
const CODE_KEYS = ['contract.type', 'compensation.pay_frequency']
// Terms whose value is a date, written YYYY-MM-DD.
const DATE_KEYS = ['contract.start_date']
// The Intl unit for each unit of the contract (Database Schema, extracted_terms.unit).
const UNITS: Record<string, string> = { days: 'day', months: 'month', hours_per_week: 'hour-per-week' }

// Turns a found term into the text the user reads, in the interface language.
// Numbers use Latin digits in both languages, as everywhere else on the site ("10 ميجابايت").
// Intl writes the units and the currency in the right words and plural for each language
// ("90 يومًا", "90 days", "10,000 ر.س.", "SAR 10,000").
export function useFormatTerm() {
  const { t, i18n } = useTranslation()
  const locale = i18n.language === 'ar' ? 'ar-u-nu-latn' : 'en'

  return (term: Term): string => {
    const { key, value, unit } = term
    if (typeof value === 'number') {
      if (unit === 'SAR') {
        // Whole amounts without ".00"; an amount with halalas keeps its two decimals.
        const decimals = Number.isInteger(value) ? 0 : 2
        return new Intl.NumberFormat(locale, {
          style: 'currency',
          currency: 'SAR',
          minimumFractionDigits: decimals,
          maximumFractionDigits: decimals,
        }).format(value)
      }
      const intlUnit = unit ? UNITS[unit] : undefined
      return new Intl.NumberFormat(locale, intlUnit ? { style: 'unit', unit: intlUnit, unitDisplay: 'long' } : {}).format(
        value,
      )
    }
    if (value === 'yes' || value === 'no') return t(`result.values.${value}`)
    if (value && DATE_KEYS.includes(key)) {
      // The date has no time, so it is read and written in UTC and never moves to the day before.
      return new Intl.DateTimeFormat(locale, { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(value))
    }
    if (value && CODE_KEYS.includes(key)) return t(`result.values.${value}`, { defaultValue: value })
    return value ?? ''
  }
}
