import { FileText } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useViewer } from '../hooks/useViewer'

// A page reference ("Page 3") on a term, an attention point or a citation. Pressed, it opens the viewer
// on that page (MVP 8 and the card). Without a viewer it is plain text.
export function PageRef({ page }: { page: number }) {
  const { t } = useTranslation()
  const viewer = useViewer()
  const label = t('result.page', { page })

  if (!viewer) return <span className="text-xs text-muted-foreground">{label}</span>
  return (
    <button
      type="button"
      onClick={() => viewer.openPage(page)}
      aria-label={t('viewer.openPage', { page })}
      className="inline-flex items-center gap-1 rounded-full border border-tint px-2 py-0.5 text-xs font-semibold text-teal-deep outline-none hover:bg-tint focus-visible:ring-3 focus-visible:ring-ring/50"
    >
      <FileText aria-hidden="true" className="size-3.5" />
      {label}
    </button>
  )
}
