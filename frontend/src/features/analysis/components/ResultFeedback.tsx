import { useMutation } from '@tanstack/react-query'
import { ThumbsDown, ThumbsUp } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { useCurrentUser } from '@/features/auth/hooks/useCurrentUser'
import { errorMessage } from '@/lib/api'
import { sendFeedback } from '../api/sendFeedback'

// Result Dashboard card: "Feedback on the result (helpful / not helpful)". After a rating, a thank-you replaces the buttons.
// The contract also allows a field and a comment (section 13); the card asks for the rating only.
export function ResultFeedback({ analysisId }: { analysisId: string }) {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()
  const feedback = useMutation({ mutationFn: (rating: 1 | -1) => sendFeedback(analysisId, rating, Boolean(user)) })

  return (
    <section aria-labelledby="feedback-title" className="rounded-xl border bg-background p-5 md:p-6">
      <h2 id="feedback-title" className="font-semibold">
        {t('result.feedback.question')}
      </h2>
      {feedback.isSuccess ? (
        <p role="status" className="mt-3 text-teal-deep">
          {t('result.feedback.thanks')}
        </p>
      ) : (
        <>
          <div className="mt-3 flex flex-wrap gap-2">
            <Button
              variant="outline"
              className="h-10 px-4"
              disabled={feedback.isPending}
              onClick={() => feedback.mutate(1)}
            >
              <ThumbsUp aria-hidden="true" />
              {t('result.feedback.helpful')}
            </Button>
            <Button
              variant="outline"
              className="h-10 px-4"
              disabled={feedback.isPending}
              onClick={() => feedback.mutate(-1)}
            >
              <ThumbsDown aria-hidden="true" />
              {t('result.feedback.notHelpful')}
            </Button>
          </div>
          {feedback.isError ? (
            <p role="alert" className="mt-3 text-sm text-destructive">
              {errorMessage(feedback.error, t('result.feedback.error'))}
            </p>
          ) : null}
        </>
      )}
    </section>
  )
}
