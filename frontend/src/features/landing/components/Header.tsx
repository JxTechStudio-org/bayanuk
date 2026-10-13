import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import logoAr from '@/assets/brand/bayanuk-logo-ar.svg'
import logoEn from '@/assets/brand/bayanuk-logo-en.svg'
import { LanguageSwitcher } from '@/features/shared/components/LanguageSwitcher'

// Links to the main sections of this page. Most labels reuse the section's own title;
// privacy has a shorter label so the bar stays light.
const SECTIONS = [
  { id: 'who', title: 'targetUsers.title' },
  { id: 'how-it-works', title: 'howItWorks.title' },
  { id: 'privacy', title: 'nav.privacy' },
  { id: 'faq', title: 'faq.title' },
] as const

export function Header() {
  const { t, i18n } = useTranslation()
  // The identity file pairs the Arabic logo with the Arabic interface and the English logo with the English one.
  const logo = i18n.language === 'ar' ? logoAr : logoEn

  return (
    <header className="sticky top-0 z-50 border-b border-tint bg-white/95 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-7xl items-center gap-8 px-4 sm:px-6">
        <Link to="/">
          <img src={logo} alt={t('brand')} className="h-12 w-auto" />
        </Link>
        <nav aria-label={t('nav.label')} className="hidden gap-6 text-sm text-muted-foreground lg:flex">
          {SECTIONS.map((section) => (
            <a key={section.id} href={`#${section.id}`} className="hover:text-ink">
              {t(section.title)}
            </a>
          ))}
        </nav>
        <div className="ms-auto">
          <LanguageSwitcher />
        </div>
      </div>
    </header>
  )
}
