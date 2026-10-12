import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import logoAr from '@/assets/brand/bayanuk-logo-ar.svg'
import logoEn from '@/assets/brand/bayanuk-logo-en.svg'
import { LanguageSwitcher } from '@/features/shared/components/LanguageSwitcher'

export function Header() {
  const { t, i18n } = useTranslation()
  // The identity file pairs the Arabic logo with the Arabic interface and the English logo with the English one.
  const logo = i18n.language === 'ar' ? logoAr : logoEn

  return (
    <header className="border-b border-tint bg-white">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
        <Link to="/">
          <img src={logo} alt={t('brand')} className="h-12 w-auto" />
        </Link>
        <LanguageSwitcher />
      </div>
    </header>
  )
}
