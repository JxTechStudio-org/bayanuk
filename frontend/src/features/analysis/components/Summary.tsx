import { useTranslation } from 'react-i18next'

interface Props {
  summary: string
  // The summary is written in the summary language, which may differ from the page (Database Schema, analyses).
  language: 'ar' | 'en'
}

// MVP 5, step 4: the plain-language summary, after the terms.
export function Summary({ summary, language }: Props) {
  const { t } = useTranslation()

  return (
    <section aria-labelledby="summary-title" className="rounded-xl border bg-background p-5 md:p-6">
      <h2 id="summary-title" className="text-lg font-semibold">
        {t('result.summaryTitle')}
      </h2>
      <p lang={language} dir={language === 'ar' ? 'rtl' : 'ltr'} className="mt-3 leading-relaxed">
        {summary}
      </p>
    </section>
  )
}
