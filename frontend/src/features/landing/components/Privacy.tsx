import { Ban, Clock, EyeOff, Lock, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

// From the MVP Definition: section 9 (trust and privacy, retention periods) and section 6 (masking).
// The retention periods (24 hours for guests, 30 days for signed-in users unless they keep it) were confirmed by the manager.
const POINTS = [
  { key: 'training', icon: Ban },
  { key: 'masking', icon: EyeOff },
  { key: 'storage', icon: Lock },
  { key: 'delete', icon: Trash2 },
  { key: 'retention', icon: Clock },
] as const

export function Privacy() {
  const { t } = useTranslation()

  return (
    <section id="privacy" className="scroll-mt-20 bg-ink py-16 text-white md:py-24">
      <div className="mx-auto max-w-6xl px-5">
        <h2 className="text-2xl font-semibold md:text-3xl">{t('privacy.title')}</h2>

        <ul className="mt-10 grid gap-x-8 gap-y-7 sm:grid-cols-2 lg:grid-cols-3">
          {POINTS.map(({ key, icon: Icon }) => (
            <li key={key} className="flex gap-3">
              <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-teal-light/15 text-teal-light">
                <Icon aria-hidden="true" className="size-[18px]" />
              </span>
              <div>
                <h3 className="font-semibold">{t(`privacy.points.${key}.title`)}</h3>
                <p className="mt-1 text-sm text-white/75">{t(`privacy.points.${key}.body`)}</p>
              </div>
            </li>
          ))}
        </ul>
      </div>
    </section>
  )
}
