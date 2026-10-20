import { Clock, PauseCircle, WifiOff } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'

export type Notice = 'offline' | 'paused' | 'queued'

const ICONS = { offline: WifiOff, paused: PauseCircle, queued: Clock }

// One line under the stages that says why nothing is moving yet (integration contract, section 6).
export function ProcessingNotice({ notice }: { notice: Notice }) {
  const { t } = useTranslation()
  const Icon = ICONS[notice]

  return (
    // The pause lasts until the next day, so it uses the attention colour from the landing page to stand out.
    <p
      className={cn(
        'mt-6 flex items-start gap-2 rounded-lg p-3 text-sm',
        notice === 'paused' ? 'bg-amber-50 font-medium text-amber-800' : 'bg-muted text-muted-foreground',
      )}
    >
      <Icon aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
      {t(`processing.notices.${notice}`)}
    </p>
  )
}
