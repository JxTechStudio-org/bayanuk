import { LoaderCircle, Mail } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { ApiError, errorMessage } from '@/lib/api'
import { useSignInLink } from '../hooks/useSignInLink'
import { saveReturnTo } from '../utils/returnTo'

// A simple check before sending. The backend checks the email again (validation_error).
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

interface Props {
  // The page to return to after sign-in.
  next: string
  onSent: (email: string) => void
}

// The email field that sends a sign-in link (integration contract, section 10).
export function MagicLinkForm({ next, onSent }: Props) {
  const { t } = useTranslation()
  const link = useSignInLink()
  const [email, setEmail] = useState('')
  const [invalid, setInvalid] = useState(false)

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

  // A validation_error names what is wrong with the email (integration contract, section 10).
  const emailProblem = link.error instanceof ApiError ? link.error.details.email?.[0] : undefined
  const message = invalid
    ? t('signIn.errors.email')
    : (emailProblem ?? (link.isError ? errorMessage(link.error, t('signIn.errors.generic')) : null))

  return (
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
        aria-invalid={invalid || Boolean(emailProblem) || undefined}
        aria-describedby={message ? 'sign-in-error' : undefined}
        className="mt-2 block h-12 w-full rounded-lg border border-border bg-background px-3 text-base outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
      />
      <Button type="submit" size="lg" className="mt-4 h-12 w-full text-base font-semibold" disabled={link.isPending}>
        {link.isPending ? (
          <LoaderCircle aria-hidden="true" className="size-5 animate-spin motion-reduce:animate-none" />
        ) : (
          <Mail aria-hidden="true" className="size-5" />
        )}
        {link.isPending ? t('signIn.sending') : t('signIn.send')}
      </Button>
      {message ? (
        <p id="sign-in-error" role="alert" className="mt-3 text-sm text-destructive">
          {message}
        </p>
      ) : null}
    </form>
  )
}
