import { LoaderCircle, Trash2 } from 'lucide-react'
import { AlertDialog } from 'radix-ui'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { errorMessage } from '@/lib/api'
import { useDeleteDocument } from '../hooks/useDeleteDocument'

// MVP 5, step 6: "Every user sees a clear Delete document button", with a confirmation step (Result Dashboard card).
// Radix AlertDialog, as the account menu uses Radix directly: it keeps the keyboard focus inside,
// closes with Escape, and screen readers read the title and the text.
export function DeleteDocument({ id }: { id: string }) {
  const { t, i18n } = useTranslation()
  const remove = useDeleteDocument(id)

  return (
    <AlertDialog.Root onOpenChange={() => remove.reset()}>
      <AlertDialog.Trigger asChild>
        <Button variant="destructive" className="h-10 px-4 font-semibold">
          <Trash2 aria-hidden="true" />
          {t('result.delete.button')}
        </Button>
      </AlertDialog.Trigger>
      <AlertDialog.Portal>
        <AlertDialog.Overlay className="fixed inset-0 z-[80] bg-black/40" />
        <AlertDialog.Content
          dir={i18n.dir()}
          className="fixed top-1/2 left-1/2 z-[90] w-[calc(100%-2.5rem)] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-2xl bg-background p-6 shadow-lg"
        >
          <AlertDialog.Title className="text-lg font-semibold">{t('result.delete.title')}</AlertDialog.Title>
          <AlertDialog.Description className="mt-2 text-muted-foreground">
            {t('result.delete.body')}
          </AlertDialog.Description>
          {remove.isError ? (
            <p role="alert" className="mt-4 text-sm text-destructive">
              {errorMessage(remove.error, t('result.delete.error'))}
            </p>
          ) : null}
          <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <AlertDialog.Cancel asChild>
              <Button variant="outline" className="h-10 px-4">
                {t('result.delete.cancel')}
              </Button>
            </AlertDialog.Cancel>
            {/* Not an AlertDialog.Action: that would close the dialog before the answer, and hide an error. */}
            <Button
              className="h-10 bg-destructive px-4 font-semibold text-white hover:bg-destructive/90"
              disabled={remove.isPending}
              onClick={() => remove.mutate()}
            >
              {remove.isPending ? (
                <LoaderCircle aria-hidden="true" className="animate-spin motion-reduce:animate-none" />
              ) : null}
              {t(remove.isPending ? 'result.delete.deleting' : 'result.delete.confirm')}
            </Button>
          </div>
        </AlertDialog.Content>
      </AlertDialog.Portal>
    </AlertDialog.Root>
  )
}
