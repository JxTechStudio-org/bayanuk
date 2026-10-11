import { Globe } from 'lucide-react'
import { useTranslation } from 'react-i18next'

export function LanguageSwitcher() {
  const { t, i18n } = useTranslation()
  const nextLanguage = i18n.language === 'ar' ? 'en' : 'ar'

  return (
    <button
      type="button"
      onClick={() => i18n.changeLanguage(nextLanguage)}
      // The label is written in the other language, so it carries that language's tag.
      lang={nextLanguage}
      className="inline-flex h-9 items-center gap-1.5 rounded-full border border-ink/15 bg-white px-3 text-sm font-medium text-ink transition-colors hover:border-tint hover:bg-tint focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-teal-light"
    >
      <Globe aria-hidden="true" className="size-4 text-teal-deep" />
      {t('switchLanguage')}
    </button>
  )
}
