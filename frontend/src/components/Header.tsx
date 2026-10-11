import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { LanguageSwitcher } from './LanguageSwitcher'

export function Header() {
  const { t } = useTranslation()

  return (
    <header className="bg-ink">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
        {/* Text for now. Replaced by the logo in the next step. */}
        <Link to="/" className="text-xl font-bold text-white">
          {t('brand')}
        </Link>
        <LanguageSwitcher />
      </div>
    </header>
  )
}