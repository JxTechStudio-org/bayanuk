import { ChevronDown, FileText, LogIn, LogOut, UserRound } from 'lucide-react'
import { DropdownMenu } from 'radix-ui'
import { useTranslation } from 'react-i18next'
import { Link, useLocation } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { useSignOut } from '../hooks/useSignOut'

// The account part of the header. A guest sees "Sign in"; a signed-in user sees a menu with
// their email, My documents and Sign out. Nothing shows while it is not known yet.
export function AccountMenu() {
  const { t, i18n } = useTranslation()
  const { pathname, search } = useLocation()
  const { data: user, isPending } = useCurrentUser()
  const signOut = useSignOut()

  if (isPending) return null

  if (!user) {
    // On the sign-in page itself, the link would lead to the same page.
    if (pathname === '/sign-in') return null
    // After sign-in, the user comes back to this page.
    const next = encodeURIComponent(pathname + search)
    return (
      <Button asChild variant="ghost" className="h-9 px-3 text-sm font-semibold">
        <Link to={`/sign-in?next=${next}`}>
          <LogIn aria-hidden="true" className="size-4 rtl:-scale-x-100" />
          {t('account.signIn')}
        </Link>
      </Button>
    )
  }

  return (
    <DropdownMenu.Root dir={i18n.dir()}>
      <DropdownMenu.Trigger asChild>
        <Button variant="outline" className="h-9 max-w-48 px-3 text-sm" aria-label={t('account.menu')}>
          <UserRound aria-hidden="true" className="size-4" />
          {/* On a phone the header has room for the icon only. */}
          <span dir="ltr" className="hidden truncate sm:inline">
            {user.email}
          </span>
          <ChevronDown aria-hidden="true" className="hidden size-4 sm:block" />
        </Button>
      </DropdownMenu.Trigger>
      <DropdownMenu.Portal>
        <DropdownMenu.Content
          align="end"
          sideOffset={6}
          className="z-[70] min-w-48 rounded-lg border bg-background p-1 text-sm shadow-md"
        >
          <DropdownMenu.Label dir="ltr" className="truncate px-3 py-2 text-xs text-muted-foreground sm:hidden">
            {user.email}
          </DropdownMenu.Label>
          <DropdownMenu.Item asChild className="flex cursor-pointer items-center gap-2 rounded-md px-3 py-2 outline-none data-highlighted:bg-muted">
            <Link to="/documents">
              <FileText aria-hidden="true" className="size-4" />
              {t('account.documents')}
            </Link>
          </DropdownMenu.Item>
          <DropdownMenu.Item
            className="flex cursor-pointer items-center gap-2 rounded-md px-3 py-2 outline-none data-highlighted:bg-muted"
            disabled={signOut.isPending}
            onSelect={() => signOut.mutate()}
          >
            <LogOut aria-hidden="true" className="size-4 rtl:-scale-x-100" />
            {t('account.signOut')}
          </DropdownMenu.Item>
        </DropdownMenu.Content>
      </DropdownMenu.Portal>
    </DropdownMenu.Root>
  )
}
