import { Check, LoaderCircle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { STAGES } from '../utils/stages'

// The four stages from the MVP Definition, section 5, step 3: done, in progress, or waiting.
// A line joins each stage to the next one, and turns teal once the stage is done.
export function ProcessingStages({ current }: { current: number }) {
  const { t } = useTranslation()

  return (
    <ol>
      {STAGES.map((stage, index) => {
        const done = index < current
        const active = index === current
        return (
          <li
            key={stage}
            aria-current={active ? 'step' : undefined}
            className={cn(
              'relative flex items-start gap-4 pb-6 last:pb-0',
              "after:absolute after:start-[15px] after:top-9 after:-bottom-1 after:w-0.5 after:content-[''] last:after:hidden",
              done ? 'after:bg-teal' : 'after:bg-border',
            )}
          >
            <span
              className={cn(
                'grid size-8 shrink-0 place-items-center rounded-full',
                done && 'bg-teal-deep text-white',
                active && 'bg-tint text-teal-deep',
                !done && !active && 'border-2 border-border',
              )}
            >
              {done ? <Check aria-hidden="true" className="size-4" /> : null}
              {active ? <LoaderCircle aria-hidden="true" className="size-4 animate-spin motion-reduce:animate-none" /> : null}
            </span>
            <span className={cn('pt-1', active && 'font-semibold', !done && !active && 'text-muted-foreground')}>
              {t(`processing.stages.${stage}`)}
            </span>
          </li>
        )
      })}
    </ol>
  )
}
