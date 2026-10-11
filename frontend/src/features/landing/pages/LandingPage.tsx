import { useTranslation } from 'react-i18next'

export function LandingPage() {
  const { t } = useTranslation()

  return (
    <div className="min-h-screen bg-ink p-8">
      <h1 className="text-4xl font-bold text-white">{t('brand')}</h1>
      <p className="mt-2 text-teal-light">{t('tagline')}</p>
    </div>
  )
}