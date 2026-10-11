import { useTranslation } from 'react-i18next'

export function HomePage() {
  const { t, i18n } = useTranslation()
  const nextLanguage = i18n.language === 'ar' ? 'en' : 'ar'

  return (
    <div className="min-h-screen bg-ink p-8">
      <h1 className="text-4xl font-bold text-white">{t('brand')}</h1>
      <p className="mt-2 text-teal-light">{t('tagline')}</p>
      <button
        type="button"
        onClick={() => i18n.changeLanguage(nextLanguage)}
        className="mt-6 rounded-lg bg-teal-light px-4 py-2 font-medium text-ink"
      >
        {t('switchLanguage')}
      </button>
    </div>
  )
}