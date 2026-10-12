import { useTranslation } from 'react-i18next'
import contractHandshake from '@/assets/landing/contract-handshake.jpg'
import contractReview from '@/assets/landing/contract-review.jpg'

// The two primary user groups from the MVP Definition (section 3).
const GROUPS = [
  { key: 'graduates', photo: contractReview },
  { key: 'switchers', photo: contractHandshake },
] as const

export function TargetUsers() {
  const { t } = useTranslation()

  return (
    <section id="who" className="scroll-mt-20 py-16 md:py-24">
      <div className="mx-auto max-w-6xl px-5">
        <p className="text-sm font-medium text-teal-deep">{t('targetUsers.eyebrow')}</p>
        <h2 className="mt-1 text-2xl font-semibold md:text-3xl">{t('targetUsers.title')}</h2>

        <div className="mt-9 grid gap-6 md:grid-cols-2">
          {GROUPS.map((group) => (
            <article
              key={group.key}
              className="overflow-hidden rounded-2xl border bg-background transition hover:-translate-y-1 hover:shadow-lg motion-reduce:transition-none motion-reduce:hover:translate-y-0"
            >
              <img
                src={group.photo}
                alt={t(`targetUsers.${group.key}.photoAlt`)}
                loading="lazy"
                className="h-60 w-full object-cover"
              />
              <div className="p-6">
                <span className="inline-block rounded-full bg-tint px-2.5 py-0.5 text-xs font-medium text-teal-deep">
                  {t(`targetUsers.${group.key}.tag`)}
                </span>
                <h3 className="mt-3 text-lg font-semibold">{t(`targetUsers.${group.key}.title`)}</h3>
                <p className="mt-1.5 text-muted-foreground">{t(`targetUsers.${group.key}.body`)}</p>
              </div>
            </article>
          ))}
        </div>
      </div>
    </section>
  )
}
