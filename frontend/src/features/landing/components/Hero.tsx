import { ShieldCheck } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import contractPages from '@/assets/landing/contract-pages.jpg'
import { Button } from '@/components/ui/button'
import { UploadEntry } from '@/features/upload/components/UploadEntry'

// Example values from a made-up contract, shown on top of the photo.
const SUMMARY = [
  { label: 'probation', value: 'probationValue', page: 1 },
  { label: 'salary', value: 'salaryValue', page: 2 },
  { label: 'notice', value: 'noticeValue', page: 3 },
] as const

export function Hero() {
  const { t } = useTranslation()

  return (
    <section className="border-b bg-linear-to-b from-muted to-background">
      <div className="mx-auto grid max-w-6xl items-center gap-10 px-5 py-12 md:grid-cols-2 md:gap-12 md:py-16">
        <div>
          <h1 className="text-3xl leading-snug font-semibold md:text-[2.6rem]">{t('hero.title')}</h1>
          <p className="mt-4 max-w-xl text-lg text-muted-foreground">{t('hero.subtitle')}</p>
          <div className="mt-7 flex flex-wrap gap-3">
            <UploadEntry />
            <Button asChild variant="outline" size="lg" className="h-12 px-6 text-base font-semibold">
              <a href="#how-it-works">{t('hero.howItWorks')}</a>
            </Button>
          </div>
          <p className="mt-5 flex items-start gap-2 text-sm text-muted-foreground">
            <ShieldCheck aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-teal-deep" />
            {t('hero.privacy')}
          </p>
        </div>

        <div className="relative md:pe-10 md:pb-10">
          <img
            src={contractPages}
            alt={t('hero.photoAlt')}
            className="aspect-4/3 w-full rounded-2xl object-cover object-[50%_70%] md:aspect-4/5 md:max-h-[460px]"
          />
          <figure
            aria-label={t('hero.summary.label')}
            className="relative mx-auto -mt-16 w-[88%] rounded-xl border bg-background p-4 shadow-xl md:absolute md:end-0 md:bottom-0 md:mx-0 md:mt-0 md:w-[300px]"
          >
            <figcaption className="mb-2.5 text-sm font-semibold">{t('hero.summary.title')}</figcaption>
            <ul className="grid gap-1.5 text-sm">
              {SUMMARY.map((item, index) => (
                <li
                  key={item.label}
                  className="flex animate-in items-center justify-between gap-2 rounded-lg bg-muted px-2.5 py-1.5 fade-in slide-in-from-bottom-1 fill-mode-both duration-500 motion-reduce:animate-none"
                  style={{ animationDelay: `${600 + index * 450}ms` }}
                >
                  <span>
                    {t(`hero.summary.${item.label}`)} <b className="font-semibold">{t(`hero.summary.${item.value}`)}</b>
                  </span>
                  <span className="text-xs whitespace-nowrap text-teal-deep">{t('hero.summary.page', { page: item.page })}</span>
                </li>
              ))}
              <li
                className="flex animate-in items-center justify-between gap-2 rounded-lg bg-amber-50 px-2.5 py-1.5 text-amber-800 fade-in slide-in-from-bottom-1 fill-mode-both duration-500 motion-reduce:animate-none"
                style={{ animationDelay: `${600 + SUMMARY.length * 450}ms` }}
              >
                <span>
                  {t('hero.summary.attention')} <b className="font-semibold">{t('hero.summary.nonCompete')}</b>
                </span>
                <span className="text-xs whitespace-nowrap">{t('hero.summary.page', { page: 4 })}</span>
              </li>
            </ul>
          </figure>
        </div>
      </div>
    </section>
  )
}
