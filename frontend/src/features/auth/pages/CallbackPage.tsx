import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { CardLayout } from '@/features/shared/components/CardLayout'
import { getCurrentUser } from '../api/getCurrentUser'
import { AuthMessage } from '../components/AuthMessage'
import { useFinishSignIn } from '../hooks/useFinishSignIn'
import { peekReturnTo } from '../utils/returnTo'

// /auth/callback: the page the backend sends the user to after Google (integration contract, section 11).
// On success the session cookie is already set, so the page loads the user and finishes the sign-in.
// On failure the address has ?error=google_cancelled, google_invalid_state or google_sign_in_failed.
export function CallbackPage() {
  const { t } = useTranslation()
  const error = useSearchParams()[0].get('error')
  const finishSignIn = useFinishSignIn()
  const [failed, setFailed] = useState(false)
  const started = useRef(false)

  useEffect(() => {
    if (error || started.current) return
    started.current = true
    getCurrentUser()
      .then((user) => (user ? finishSignIn(user) : setFailed(true)))
      .catch(() => setFailed(true))
  }, [error, finishSignIn])

  // Cancelling is the user's own choice, so it has its own, calmer text. Every other failure shares one.
  const problem = error === 'google_cancelled' ? 'googleCancelled' : error || failed ? 'googleFailed' : null

  return (
    <CardLayout privacyLine={false}>
      {problem ? (
        <AuthMessage
          title={t(`auth.${problem}.title`)}
          body={t(`auth.${problem}.body`)}
          action={
            <Button asChild size="lg" className="h-12 px-6 text-base font-semibold">
              <Link to={`/sign-in?next=${encodeURIComponent(peekReturnTo())}`}>{t('auth.tryAgain')}</Link>
            </Button>
          }
        />
      ) : (
        <AuthMessage title={t('auth.waiting')} waiting />
      )}
    </CardLayout>
  )
}
