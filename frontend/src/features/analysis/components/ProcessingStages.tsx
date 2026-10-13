import { Check, LoaderCircle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { STAGES } from '../utils/stages'

// The four stages from the MVP Definition, section 5, step 3: done, in progress, or waiting.
export function ProcessingStages({ current }: { current: number }) {
  const { t } = useTranslation()

  return (
    <ol className="grid gap-3">
      {STAGES.map((stage, index) => {
        const done = index < current
        const active = index === current
        return (
          <li
            key={stage}
            aria-current={active ? 'step' : undefined}
            className={active ? 'flex items-center gap-3 font-semibold' : 'flex items-center gap-3 text-muted-foreground'}
          >
            <span
              className={
                done
                  ? 'grid size-7 place-items-center rounded-full bg-teal-deep text-white'
                  : active
                    ? 'grid size-7 place-items-center rounded-full border-2 border-teal text-teal-deep'
                    : 'grid size-7 place-items-center rounded-full border-2 border-border'
              }
            >
              {done ? <Check aria-hidden="true" className="size-4" /> : null}
              {active ? <LoaderCircle aria-hidden="true" className="size-4 animate-spin motion-reduce:animate-none" /> : null}
            </span>
            {t(`processing.stages.${stage}`)}
          </li>
        )
      })}
    </ol>
  )
}
