import { Clock, PauseCircle, WifiOff } from 'lucide-react'
import { useTranslation } from 'react-i18next'

export type Notice = 'offline' | 'paused' | 'queued'

const ICONS = { offline: WifiOff, paused: PauseCircle, queued: Clock }

// One line under the stages that says why nothing is moving yet (integration contract, section 6).
export function ProcessingNotice({ notice }: { notice: Notice }) {
  const { t } = useTranslation()
  const Icon = ICONS[notice]

  return (
    <p className="mt-6 flex items-start gap-2 rounded-lg bg-muted p-3 text-sm text-muted-foreground">
      <Icon aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
      {t(`processing.notices.${notice}`)}
    </p>
  )
}
