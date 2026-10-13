import { useTranslation } from 'react-i18next'

export function Footer() {
  const { t } = useTranslation()

  return (
    <footer className="border-t py-8 text-sm text-muted-foreground">
      <div className="mx-auto max-w-6xl space-y-2 px-5">
        {/* The disclaimer wording is from the MVP Definition, section 9. */}
        <p>{t('footer.disclaimer')}</p>
        <p>{t('footer.studio')}</p>
      </div>
    </footer>
  )
}
