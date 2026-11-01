import { FileText, Files, FileX } from 'lucide-react'
import { useTranslation } from 'react-i18next'

// From the MVP Definition, section 4.1 (document types) and 4.2 (file limits).
const DOCUMENTS = [
  { key: 'offer', icon: FileText, supported: true },
  { key: 'contract', icon: Files, supported: true },
  { key: 'other', icon: FileX, supported: false },
] as const

export function SupportedFiles() {
  const { t } = useTranslation()

  return (
    <section id="documents" className="scroll-mt-20 bg-muted py-16 md:py-24">
      <div className="mx-auto max-w-6xl px-5">
        <h2 className="text-2xl font-semibold md:text-3xl">{t('supportedFiles.title')}</h2>
        <p className="mt-2 text-muted-foreground">{t('supportedFiles.subtitle')}</p>

        <div className="mt-9 grid gap-5 md:grid-cols-3">
          {DOCUMENTS.map(({ key, icon: Icon, supported }) => (
            <div key={key} className="rounded-2xl border bg-background p-6">
              <div
                className={
                  supported
                    ? 'grid size-11 place-items-center rounded-xl bg-tint text-teal-deep'
                    : 'grid size-11 place-items-center rounded-xl bg-muted text-muted-foreground'
                }
              >
                <Icon aria-hidden="true" className="size-5" />
              </div>
              <h3 className="mt-4 text-lg font-semibold">{t(`supportedFiles.${key}.title`)}</h3>
              <p className="mt-1 text-sm text-muted-foreground">{t(`supportedFiles.${key}.body`)}</p>
              {supported ? (
                <div className="mt-3 flex gap-2">
                  <span className="rounded-md border px-2 py-0.5 text-xs font-semibold">PDF</span>
                </div>
              ) : null}
            </div>
          ))}
        </div>

        <p className="mt-6 text-sm text-muted-foreground">{t('supportedFiles.limits')}</p>
      </div>
    </section>
  )
}
