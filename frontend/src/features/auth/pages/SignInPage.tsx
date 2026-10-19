import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate, useSearchParams } from 'react-router-dom'
import { CardLayout } from '@/features/shared/components/CardLayout'
import { CheckEmail } from '../components/CheckEmail'
import { SignInForm } from '../components/SignInForm'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { safePath } from '../utils/returnTo'

// /sign-in?next=/the/page: email magic link or Google (MVP 4.2). After the link is sent,
// the same page shows "Check your email". A signed-in user goes straight to the next page.
export function SignInPage() {
  const { t } = useTranslation()
  const [params] = useSearchParams()
  const next = safePath(params.get('next'))
  const { data: user } = useCurrentUser()
  const [sentTo, setSentTo] = useState<string | null>(null)

  if (user) return <Navigate to={next} replace />

  return (
    <CardLayout privacyLine={false}>
      {sentTo ? (
        <CheckEmail email={sentTo} onChangeEmail={() => setSentTo(null)} />
      ) : (
        <>
          <h1 className="text-xl font-semibold md:text-2xl">{t('signIn.title')}</h1>
          <p className="mt-2 text-muted-foreground">{t('signIn.body')}</p>
          <SignInForm next={next} onSent={setSentTo} />
        </>
      )}
    </CardLayout>
  )
}
