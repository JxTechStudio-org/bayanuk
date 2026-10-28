import { useMutation } from '@tanstack/react-query'
import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { CardLayout } from '@/features/shared/components/CardLayout'
import { PageMessage } from '@/features/shared/components/PageMessage'
import { ApiError, errorMessage } from '@/lib/api'
import { verifySignInLink } from '../api/verifySignInLink'
import { useFinishSignIn } from '../hooks/useFinishSignIn'
import { peekReturnTo } from '../utils/returnTo'

// Integration contract, section 10: the codes for a link that cannot be used, and their texts.
const LINK_PROBLEMS = { link_expired: 'expired', link_used: 'used', link_invalid: 'invalid' } as const
type LinkCode = keyof typeof LINK_PROBLEMS

// /auth/verify?token=...: the page the email link opens. It sends the token with POST, never GET,
// because email scanners open links on their own (integration contract, section 10).
export function VerifyPage() {
  const { t } = useTranslation()
  const token = useSearchParams()[0].get('token')
  const finishSignIn = useFinishSignIn()
  const verify = useMutation({
    mutationFn: verifySignInLink,
    onSuccess: (user) => finishSignIn(user),
  })
  const { mutate } = verify
  const sent = useRef(false)

  useEffect(() => {
    // The link works only once. React runs effects twice in development, so the token is sent once only.
    if (!token || sent.current) return
    sent.current = true
    mutate(token)
  }, [token, mutate])

  const code = verify.error instanceof ApiError ? verify.error.code : null
  // A page opened without a token is treated like a link that is not valid.
  const problem = !token ? 'invalid' : code && code in LINK_PROBLEMS ? LINK_PROBLEMS[code as LinkCode] : null

  return (
    <CardLayout privacyLine={false}>
      {problem ? (
        <PageMessage
          title={t(`auth.${problem}.title`)}
          body={t(`auth.${problem}.body`)}
          action={
            <Button asChild size="lg" className="h-12 px-6 text-base font-semibold">
              <Link to={`/sign-in?next=${encodeURIComponent(peekReturnTo())}`}>{t('auth.newLink')}</Link>
            </Button>
          }
        />
      ) : verify.isError ? (
        <PageMessage
          title={t('auth.failed.title')}
          body={errorMessage(verify.error, t('auth.failed.body'))}
          action={
            <Button size="lg" className="h-12 px-6 text-base font-semibold" onClick={() => token && mutate(token)}>
              {t('auth.tryAgain')}
            </Button>
          }
        />
      ) : (
        <PageMessage title={t('auth.waiting')} waiting />
      )}
    </CardLayout>
  )
}
