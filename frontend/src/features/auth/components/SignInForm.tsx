import { LoaderCircle, Mail } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { errorMessage } from '@/lib/api'
import { useGoogleSignIn } from '../hooks/useGoogleSignIn'
import { useSignInLink } from '../hooks/useSignInLink'
import { saveReturnTo } from '../utils/returnTo'

// A simple check before sending. The backend checks the email again (validation_error).
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

interface Props {
  // The page to return to after sign-in.
  next: string
  onSent: (email: string) => void
}

export function SignInForm({ next, onSent }: Props) {
  const { t } = useTranslation()
  const link = useSignInLink()
  const google = useGoogleSignIn()
  const [email, setEmail] = useState('')
  const [invalid, setInvalid] = useState(false)
  const busy = link.isPending || google.isPending

  function sendLink(event: FormEvent) {
    event.preventDefault()
    const value = email.trim()
    if (!EMAIL.test(value)) {
      setInvalid(true)
      return
    }
    setInvalid(false)
    saveReturnTo(next)
    link.mutate(value, { onSuccess: () => onSent(value) })
  }

  function continueWithGoogle() {
    saveReturnTo(next)
    google.mutate()
  }

  const failed = link.error ?? google.error
  const message = invalid ? t('signIn.errors.email') : failed ? errorMessage(failed, t('signIn.errors.generic')) : null

  return (
    <div className="mt-6">
      <Button
        variant="outline"
        size="lg"
        className="h-12 w-full text-base font-semibold"
        disabled={busy}
        onClick={continueWithGoogle}
      >
        {google.isPending ? (
          <LoaderCircle aria-hidden="true" className="size-5 animate-spin motion-reduce:animate-none" />
        ) : null}
        {t('signIn.google')}
      </Button>

      <p className="my-5 flex items-center gap-3 text-sm text-muted-foreground before:h-px before:flex-1 before:bg-border after:h-px after:flex-1 after:bg-border">
        {t('signIn.or')}
      </p>

      <form noValidate onSubmit={sendLink}>
        <label htmlFor="sign-in-email" className="text-sm font-medium">
          {t('signIn.email')}
        </label>
        {/* An email address reads left to right, also on the Arabic page. */}
        <input
          id="sign-in-email"
          type="email"
          autoComplete="email"
          dir="ltr"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          aria-invalid={invalid || undefined}
          aria-describedby={message ? 'sign-in-error' : undefined}
          className="mt-2 block h-12 w-full rounded-lg border border-border bg-background px-3 text-base outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
        />
        <Button type="submit" size="lg" className="mt-4 h-12 w-full text-base font-semibold" disabled={busy}>
          {link.isPending ? (
            <LoaderCircle aria-hidden="true" className="size-5 animate-spin motion-reduce:animate-none" />
          ) : (
            <Mail aria-hidden="true" className="size-5" />
          )}
          {link.isPending ? t('signIn.sending') : t('signIn.send')}
        </Button>
      </form>

      {message ? (
        <p id="sign-in-error" role="alert" className="mt-3 text-sm text-destructive">
          {message}
        </p>
      ) : null}

      {/* Database Schema, users.terms_version: the version of the terms accepted is stored at sign-in. */}
      <p className="mt-6 text-sm text-muted-foreground">{t('signIn.consent')}</p>
    </div>
  )
}
