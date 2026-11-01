import { CircleAlert, LoaderCircle, type LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

interface Props {
  title: string
  body?: string
  // Shown under the text, for example a link to ask for a new sign-in link or a button to try again.
  action?: ReactNode
  // While waiting, a spinner replaces the alert icon.
  waiting?: boolean
  // A problem shows in calm grey, or in red when it is an error (as "Document not found" on the processing page).
  tone?: 'calm' | 'error'
  // The icon of a problem, CircleAlert unless another fits better (FileX for a deleted document).
  icon?: LucideIcon
}

// A page that shows one message: waiting, or a problem.
// Used by the sign-in link page, the Google return page and the result page.
export function PageMessage({ title, body, action, waiting = false, tone = 'calm', icon: Icon = CircleAlert }: Props) {
  return (
    <div role={waiting ? 'status' : 'alert'}>
      <span
        className={cn(
          'grid size-12 place-items-center rounded-full',
          waiting
            ? 'bg-tint text-teal-deep'
            : tone === 'error'
              ? 'bg-destructive/10 text-destructive'
              : 'bg-muted text-muted-foreground',
        )}
      >
        {waiting ? (
          <LoaderCircle aria-hidden="true" className="size-6 animate-spin motion-reduce:animate-none" />
        ) : (
          <Icon aria-hidden="true" className="size-6" />
        )}
      </span>
      <h1 className="mt-4 text-xl font-semibold md:text-2xl">{title}</h1>
      {body ? <p className="mt-2 text-muted-foreground">{body}</p> : null}
      {action ? <div className="mt-6">{action}</div> : null}
    </div>
  )
}
