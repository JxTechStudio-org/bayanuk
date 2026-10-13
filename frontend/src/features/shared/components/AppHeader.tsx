import { LanguageSwitcher } from './LanguageSwitcher'
import { Logo } from './Logo'

// Header for the pages after the landing page: the logo and the language switch, no section links.
export function AppHeader() {
  return (
    <header className="border-b border-tint bg-white">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
        <Logo />
        <LanguageSwitcher />
      </div>
    </header>
  )
}
