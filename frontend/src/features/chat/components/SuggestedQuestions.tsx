import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'

// MVP 5, step 5: "Suggested questions are shown as chips", with the MVP's two examples.
const SUGGESTED_QUESTIONS = ['noticePeriod', 'resignProbation'] as const

export function SuggestedQuestions({ onAsk, disabled }: { onAsk: (question: string) => void; disabled: boolean }) {
  const { t } = useTranslation()

  return (
    <ul aria-label={t('chat.suggestedLabel')} className="flex flex-wrap gap-2">
      {SUGGESTED_QUESTIONS.map((key) => (
        <li key={key}>
          <Button
            variant="outline"
            className="h-auto rounded-full px-4 py-2 text-start whitespace-normal"
            disabled={disabled}
            onClick={() => onAsk(t(`chat.suggested.${key}`))}
          >
            {t(`chat.suggested.${key}`)}
          </Button>
        </li>
      ))}
    </ul>
  )
}
