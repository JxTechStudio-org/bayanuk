import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import logoAr from '@/assets/brand/bayanuk-logo-ar.svg'
import logoEn from '@/assets/brand/bayanuk-logo-en.svg'

// The identity file pairs the Arabic logo with the Arabic interface and the English logo with the English one.
export function Logo() {
  const { t, i18n } = useTranslation()
  const logo = i18n.language === 'ar' ? logoAr : logoEn

  return (
    <Link to="/">
      <img src={logo} alt={t('brand')} className="h-12 w-auto" />
    </Link>
  )
}
