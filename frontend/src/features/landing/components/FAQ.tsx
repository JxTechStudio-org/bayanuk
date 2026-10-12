import { useTranslation } from 'react-i18next'
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion'

// Every answer comes from the MVP Definition: sections 4.1, 4.2, 8 and 9.
const QUESTIONS = ['legal', 'documents', 'account', 'signIn', 'answers'] as const

export function FAQ() {
  const { t, i18n } = useTranslation()

  return (
    <section id="faq" className="scroll-mt-20 py-16 md:py-24">
      <div className="mx-auto max-w-3xl px-5">
        <h2 className="text-2xl font-semibold md:text-3xl">{t('faq.title')}</h2>

        {/* dir tells the accordion which way the arrow keys go */}
        <Accordion type="single" collapsible dir={i18n.dir()} className="mt-8">
          {QUESTIONS.map((key) => (
            <AccordionItem key={key} value={key}>
              <AccordionTrigger className="text-base">{t(`faq.items.${key}.question`)}</AccordionTrigger>
              <AccordionContent className="text-base text-muted-foreground">
                {t(`faq.items.${key}.answer`)}
              </AccordionContent>
            </AccordionItem>
          ))}
        </Accordion>
      </div>
    </section>
  )
}
