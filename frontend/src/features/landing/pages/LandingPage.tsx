import { useTranslation } from 'react-i18next'
import { Header } from '../components/Header'

// Puts the landing page sections together, top to bottom.
export function LandingPage() {
  const { t } = useTranslation()

  return (
    <>
      <Header />
      <main className="min-h-screen bg-ink p-8">
        <h1 className="text-4xl font-bold text-white">{t('brand')}</h1>
        <p className="mt-2 text-teal-light">{t('tagline')}</p>
      </main>
    </>
  )
}
