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
      className="rounded-lg px-3 py-2 text-sm font-medium text-white hover:bg-white/10"
    >
      {t('switchLanguage')}
    </button>
  )
}