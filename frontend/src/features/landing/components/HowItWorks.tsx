import { useTranslation } from 'react-i18next'

// The core user flow from the MVP Definition, section 5 (steps 2 to 5).
const STEPS = ['upload', 'process', 'result', 'ask'] as const

export function HowItWorks() {
  const { t } = useTranslation()

  return (
    <section id="how-it-works" className="scroll-mt-20 py-16 md:py-24">
      <div className="mx-auto max-w-6xl px-5">
        <h2 className="text-2xl font-semibold md:text-3xl">{t('howItWorks.title')}</h2>

        <ol className="mt-10 grid gap-8 md:grid-cols-4 md:gap-6">
          {STEPS.map((step, index) => (
            <li
              key={step}
              // On wide screens a dotted line runs from each number to the next one.
              className="relative flex gap-4 md:block md:after:absolute md:after:start-12 md:after:-end-4 md:after:top-5 md:after:border-t-2 md:after:border-dashed md:after:border-border md:after:content-[''] md:last:after:hidden"
            >
              <span className="relative grid size-10 shrink-0 place-items-center rounded-full border-2 border-teal bg-background font-bold text-teal-deep md:mb-4">
                {index + 1}
              </span>
              <div>
                <h3 className="text-lg font-semibold">{t(`howItWorks.steps.${step}.title`)}</h3>
                <p className="mt-1.5 text-sm text-muted-foreground">{t(`howItWorks.steps.${step}.body`)}</p>
              </div>
            </li>
          ))}
        </ol>
      </div>
    </section>
  )
}
