import { ChevronLeft, ChevronRight, LoaderCircle } from 'lucide-react'
import { useCallback, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Document, Page, pdfjs } from 'react-pdf'
import { Button } from '@/components/ui/button'
import { useDocumentFile } from '../hooks/useDocumentFile'

// react-pdf asks for the PDF.js worker to be set in the file that renders the PDF.
pdfjs.GlobalWorkerOptions.workerSrc = new URL('pdfjs-dist/build/pdf.worker.min.mjs', import.meta.url).toString()

// The whole file is read once (no partial reads), so the pages still open after the 5-minute URL expires.
// The files are 10 MB at most (MVP 4.2).
const OPTIONS = { disableRange: true, disableStream: true }

interface Props {
  documentId: string
  page: number
  onPageChange: (page: number) => void
}

// Technical Plan, section 4: DocumentViewer (react-pdf). It shows one page of the original file at a time,
// as wide as its box, with the previous and next pages. Every uploaded file is a PDF (decision 144).
// An expired or failed URL is asked again once, with no error shown (contract, section 9).
export default function DocumentViewer({ documentId, page, onPageChange }: Props) {
  const { t } = useTranslation()
  const file = useDocumentFile(documentId)
  const [pageCount, setPageCount] = useState(0)
  const [width, setWidth] = useState(0)
  // The URL that already failed once and was asked again. A second failure shows the error.
  const [retriedUrl, setRetriedUrl] = useState<string | null>(null)
  const [failed, setFailed] = useState(false)

  // The page as wide as the box, measured when the box appears and when it changes size.
  const measure = useCallback((box: HTMLDivElement | null) => {
    if (!box) return
    const observer = new ResizeObserver(([entry]) => setWidth(Math.floor(entry.contentRect.width)))
    observer.observe(box)
    return () => observer.disconnect()
  }, [])

  function onLoadError() {
    const url = file.data?.url ?? null
    if (url && retriedUrl !== url) {
      setRetriedUrl(url)
      file.refetch()
      return
    }
    setFailed(true)
  }

  const shown = Math.min(page, pageCount || page)
  const loading = (
    <p role="status" className="flex items-center gap-2 p-4 text-sm text-muted-foreground">
      <LoaderCircle aria-hidden="true" className="size-4 animate-spin motion-reduce:animate-none" />
      {t('viewer.loading')}
    </p>
  )

  if (file.isError || failed) {
    return (
      <div role="alert" className="p-4 text-sm">
        <p className="text-destructive">{t('viewer.error')}</p>
        <Button
          variant="outline"
          className="mt-2 h-9 px-3"
          onClick={() => {
            setFailed(false)
            setRetriedUrl(null)
            file.refetch()
          }}
        >
          {t('result.retry')}
        </Button>
      </div>
    )
  }
  if (!file.data) return loading

  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center justify-between gap-2 border-b px-3 py-2 text-sm">
        <Button
          variant="ghost"
          size="icon"
          aria-label={t('viewer.previous')}
          disabled={shown <= 1}
          onClick={() => onPageChange(shown - 1)}
        >
          <ChevronRight aria-hidden="true" className="ltr:rotate-180" />
        </Button>
        <p aria-live="polite">{pageCount ? t('viewer.pageOf', { page: shown, total: pageCount }) : null}</p>
        <Button
          variant="ghost"
          size="icon"
          aria-label={t('viewer.next')}
          disabled={!pageCount || shown >= pageCount}
          onClick={() => onPageChange(shown + 1)}
        >
          <ChevronLeft aria-hidden="true" className="ltr:rotate-180" />
        </Button>
      </div>
      <div ref={measure} className="min-h-0 flex-1 overflow-auto bg-muted p-3">
        <Document
          // A new answer to the file request (a new URL, or the same one asked again) reads the file again.
          key={file.dataUpdatedAt}
          file={file.data.url}
          options={OPTIONS}
          loading={loading}
          error={loading}
          onLoadSuccess={({ numPages }) => setPageCount(numPages)}
          onLoadError={onLoadError}
        >
          {width ? (
            // The page is drawn as a picture; its words are in the result, with their quotes.
            <div role="img" aria-label={t('viewer.pageImage', { page: shown })}>
              <Page
                pageNumber={shown}
                width={width - 24}
                loading={loading}
                renderTextLayer={false}
                renderAnnotationLayer={false}
                className="mx-auto shadow-sm"
              />
            </div>
          ) : null}
        </Document>
      </div>
    </div>
  )
}
