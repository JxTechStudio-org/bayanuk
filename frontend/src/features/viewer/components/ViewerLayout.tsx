import { X } from 'lucide-react'
import { Dialog } from 'radix-ui'
import { lazy, Suspense, useRef, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { useMediaQuery } from '@/features/shared/hooks/useMediaQuery'
import { ViewerContext } from '../viewerContext'

// The PDF library is large, so it loads only when the viewer is shown.
const DocumentViewer = lazy(() => import('./DocumentViewer'))

// Tailwind's lg: wide enough for the result and the document side by side.
const WIDE = '(min-width: 64rem)'

// The card: "Document viewer showing the original file side by side with the results", and on small screens
// "decide how the viewer behaves". On a wide screen the document stays beside the result while it scrolls.
// On a phone there is no room for both: a page reference opens the document over the whole screen,
// and closing it goes back to the same place.
export function ViewerLayout({ documentId, children }: { documentId: string; children: ReactNode }) {
  const { t, i18n } = useTranslation()
  const wide = useMediaQuery(WIDE)
  const [page, setPage] = useState(1)
  const [open, setOpen] = useState(false)
  // The page reference that opened the document on a phone, to go back to it on close.
  const openedFrom = useRef<HTMLElement | null>(null)

  function openPage(next: number) {
    setPage(next)
    if (wide) return
    openedFrom.current = document.activeElement instanceof HTMLElement ? document.activeElement : null
    setOpen(true)
  }

  const viewer = (
    <Suspense fallback={null}>
      <DocumentViewer documentId={documentId} page={page} onPageChange={setPage} />
    </Suspense>
  )

  return (
    <ViewerContext.Provider value={{ page, openPage }}>
      <div className="lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,0.85fr)] lg:items-start lg:gap-8">
        <div className="min-w-0">{children}</div>
        {wide ? (
          <aside
            aria-label={t('viewer.title')}
            className="sticky top-4 mt-8 h-[calc(100svh-2rem)] overflow-hidden rounded-xl border bg-background"
          >
            {viewer}
          </aside>
        ) : null}
      </div>
      {wide ? null : (
        <Dialog.Root open={open} onOpenChange={setOpen}>
          <Dialog.Portal>
            <Dialog.Content
              dir={i18n.dir()}
              className="fixed inset-0 z-[90] flex flex-col bg-background"
              onCloseAutoFocus={(event) => {
                event.preventDefault()
                openedFrom.current?.focus()
              }}
            >
              <div className="flex items-center justify-between gap-2 border-b px-4 py-2">
                <Dialog.Title className="font-semibold">{t('viewer.title')}</Dialog.Title>
                <Dialog.Description className="sr-only">{t('viewer.description')}</Dialog.Description>
                <Dialog.Close asChild>
                  <Button variant="outline" className="h-9 px-3">
                    <X aria-hidden="true" />
                    {t('viewer.close')}
                  </Button>
                </Dialog.Close>
              </div>
              <div className="min-h-0 flex-1">{viewer}</div>
            </Dialog.Content>
          </Dialog.Portal>
        </Dialog.Root>
      )}
    </ViewerContext.Provider>
  )
}
