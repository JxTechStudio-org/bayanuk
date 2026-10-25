import { BookmarkPlus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link, useLocation } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { useCurrentUser } from '../hooks/useCurrentUser'

// MVP 5, step 6: "Guests are invited to sign in to keep the analysis." It never blocks the page:
// the guest can read everything without signing in. Signed-in users do not see it.
export function SaveInvite() {
  const { t } = useTranslation()
  const { pathname } = useLocation()
  const { data: user, isPending } = useCurrentUser()

  if (isPending || user) return null

  return (
    <aside className="mt-6 rounded-xl border border-tint bg-tint/40 p-4">
      <p className="flex items-start gap-2 font-semibold">
        <BookmarkPlus aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-teal-deep" />
        {t('account.invite.title')}
      </p>
      <p className="mt-1 text-sm text-muted-foreground">{t('account.invite.body')}</p>
      <Button asChild variant="outline" className="mt-3 h-10 px-4 font-semibold">
        <Link to={`/sign-in?next=${encodeURIComponent(pathname)}`}>{t('account.signIn')}</Link>
      </Button>
    </aside>
  )
}
