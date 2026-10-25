import { LoaderCircle, MailCheck } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { errorMessage } from '@/lib/api'
import { useSignInLink } from '../hooks/useSignInLink'

interface Props {
  email: string
  onChangeEmail: () => void
}

// Shown after the sign-in link is sent: where it went, how long it works, and a way to send it again.
export function CheckEmail({ email, onChangeEmail }: Props) {
  const { t } = useTranslation()
  const resend = useSignInLink()

  return (
    <div>
      <span className="grid size-12 place-items-center rounded-full bg-tint text-teal-deep">
        <MailCheck aria-hidden="true" className="size-6" />
      </span>
      <h1 className="mt-4 text-xl font-semibold md:text-2xl">{t('signIn.checkEmail.title')}</h1>
      <p className="mt-2 text-muted-foreground">
        {t('signIn.checkEmail.sentTo')}{' '}
        <span dir="ltr" className="font-semibold text-foreground">
          {email}
        </span>
      </p>
      {/* Database Schema, login_tokens: a link expires 15 minutes after it is created, and works once. */}
      <p className="mt-2 text-muted-foreground">{t('signIn.checkEmail.validFor')}</p>

      <div className="mt-6 flex flex-wrap gap-3">
        <Button
          variant="outline"
          size="lg"
          className="h-12 px-5 text-base font-semibold"
          disabled={resend.isPending}
          onClick={() => resend.mutate(email)}
        >
          {resend.isPending ? (
            <LoaderCircle aria-hidden="true" className="size-5 animate-spin motion-reduce:animate-none" />
          ) : null}
          {t('signIn.checkEmail.resend')}
        </Button>
        <Button variant="ghost" size="lg" className="h-12 px-5 text-base" onClick={onChangeEmail}>
          {t('signIn.checkEmail.changeEmail')}
        </Button>
      </div>

      {resend.isSuccess ? (
        <p role="status" className="mt-3 text-sm text-teal-deep">
          {t('signIn.checkEmail.resent')}
        </p>
      ) : null}
      {resend.isError ? (
        <p role="alert" className="mt-3 text-sm text-destructive">
          {errorMessage(resend.error, t('signIn.errors.generic'))}
        </p>
      ) : null}
    </div>
  )
}
