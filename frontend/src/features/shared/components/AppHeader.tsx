import { AccountMenu } from '@/features/auth/components/AccountMenu'
import { LanguageSwitcher } from './LanguageSwitcher'
import { Logo } from './Logo'

// Header for the pages after the landing page: the logo, the account menu and the language switch,
// no section links.
export function AppHeader() {
  return (
    <header className="border-b border-tint bg-white">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6">
        <Logo />
        <div className="flex items-center gap-2">
          <AccountMenu />
          <LanguageSwitcher />
        </div>
      </div>
    </header>
  )
}
