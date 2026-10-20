import { LoaderCircle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { errorMessage } from '@/lib/api'
import { useGoogleSignIn } from '../hooks/useGoogleSignIn'
import { saveReturnTo } from '../utils/returnTo'

// "Continue with Google" (integration contract, section 11). Google's logo is not shown: it is a trademark
// with its own rules, and the text names Google.
export function GoogleButton({ next }: { next: string }) {
  const { t } = useTranslation()
  const google = useGoogleSignIn()

  function continueWithGoogle() {
    // The page to return to after Google.
    saveReturnTo(next)
    google.mutate()
  }

  return (
    <div>
      <Button
        variant="outline"
        size="lg"
        className="h-12 w-full text-base font-semibold"
        disabled={google.isPending}
        onClick={continueWithGoogle}
      >
        {google.isPending ? (
          <LoaderCircle aria-hidden="true" className="size-5 animate-spin motion-reduce:animate-none" />
        ) : null}
        {t('signIn.google')}
      </Button>
      {google.isError ? (
        <p role="alert" className="mt-3 text-sm text-destructive">
          {errorMessage(google.error, t('signIn.errors.generic'))}
        </p>
      ) : null}
    </div>
  )
}
