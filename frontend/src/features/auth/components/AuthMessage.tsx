import { CircleAlert, LoaderCircle } from 'lucide-react'
import type { ReactNode } from 'react'

interface Props {
  title: string
  body?: string
  // Shown under the text, for example a link to ask for a new sign-in link.
  action?: ReactNode
  // While waiting, a spinner replaces the alert icon.
  waiting?: boolean
}

// The one message the sign-in link page and the Google return page show: waiting, or a problem.
export function AuthMessage({ title, body, action, waiting = false }: Props) {
  return (
    <div role={waiting ? 'status' : 'alert'}>
      <span
        className={
          waiting
            ? 'grid size-12 place-items-center rounded-full bg-tint text-teal-deep'
            : 'grid size-12 place-items-center rounded-full bg-muted text-muted-foreground'
        }
      >
        {waiting ? (
          <LoaderCircle aria-hidden="true" className="size-6 animate-spin motion-reduce:animate-none" />
        ) : (
          <CircleAlert aria-hidden="true" className="size-6" />
        )}
      </span>
      <h1 className="mt-4 text-xl font-semibold md:text-2xl">{title}</h1>
      {body ? <p className="mt-2 text-muted-foreground">{body}</p> : null}
      {action ? <div className="mt-6">{action}</div> : null}
    </div>
  )
}
