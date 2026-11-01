import type { ReactNode } from 'react'
import { AppHeader } from './AppHeader'
import { PrivacyLine } from './PrivacyLine'

interface Props {
  children: ReactNode
  // The privacy line is about the uploaded document, so pages without one (sign-in) leave it out.
  privacyLine?: boolean
}

// The frame of the pages outside the landing page (processing, result, sign-in): the header,
// the soft background from the top of the landing page, one card for the content,
// and the privacy line from the landing page (MVP 9).
export function CardLayout({ children, privacyLine = true }: Props) {
  return (
    <div className="flex min-h-svh flex-col">
      <AppHeader />
      <main className="flex-1 bg-linear-to-b from-muted to-background px-5 py-12 md:py-20">
        <div className="mx-auto w-full max-w-lg rounded-2xl border bg-background p-6 shadow-sm md:p-8">{children}</div>
        {privacyLine ? <PrivacyLine className="mx-auto mt-6 max-w-lg" /> : null}
      </main>
    </div>
  )
}
