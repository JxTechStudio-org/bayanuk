import { screen } from '@testing-library/react'
import { useEffect } from 'react'
import { afterEach, expect, it, vi } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { renderWithProviders } from '@/test/renderWithProviders'
import DocumentViewer from './DocumentViewer'

// The URLs the file request gives, one per call, and the error each URL gives when PDF.js reads it (none: it opens).
let urls: string[] = []
const loadErrors = new Map<string, Error>()

// jsdom cannot run PDF.js, so react-pdf is replaced by a stand-in that "reads" the URL.
vi.mock('react-pdf', () => ({
  pdfjs: { GlobalWorkerOptions: {} },
  Document: ({
    file,
    onLoadSuccess,
    onLoadError,
    children,
  }: {
    file: string
    onLoadSuccess: (pdf: { numPages: number }) => void
    onLoadError: (error: Error) => void
    children: React.ReactNode
  }) => {
    useEffect(() => {
      const error = loadErrors.get(file)
      if (error) onLoadError(error)
      else onLoadSuccess({ numPages: 9 })
    }, [file]) // eslint-disable-line react-hooks/exhaustive-deps -- read each URL once, as PDF.js does
    return <div data-file={file}>{children}</div>
  },
  Page: ({ pageNumber }: { pageNumber: number }) => <p>pdf page {pageNumber}</p>,
}))

function fileAnswers() {
  let call = 0
  return vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
    if (String(input) === '/api/v1/me') return Response.json({ error: { code: 'unauthorized', message: '' } }, { status: 401 })
    const url = urls[Math.min(call++, urls.length - 1)]
    return Response.json({ url, expires_at: new Date(Date.now() + 5 * 60_000).toISOString() })
  })
}

afterEach(() => {
  vi.restoreAllMocks()
  loadErrors.clear()
})

it('shows the page it is asked for, with the page count', async () => {
  urls = ['/storage/a']
  fileAnswers()
  renderWithProviders(<DocumentViewer documentId="d1" page={3} onPageChange={() => {}} />)
  expect(await screen.findByText('صفحة 3 من 9')).toBeInTheDocument()
})

it('asks for a new URL when the first one fails (expired), without showing an error', async () => {
  urls = ['/storage/expired', '/storage/new']
  loadErrors.set('/storage/expired', new Error('Unexpected server response (403)'))
  const fetch = fileAnswers()
  renderWithProviders(<DocumentViewer documentId="d1" page={1} onPageChange={() => {}} />)

  expect(await screen.findByText('صفحة 1 من 9')).toBeInTheDocument()
  expect(document.querySelector('[data-file]')).toHaveAttribute('data-file', '/storage/new')
  expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  expect(fetch.mock.calls.filter(([url]) => url === '/api/v1/documents/d1/file')).toHaveLength(2)
})

it('shows an error with "Try again" when the new URL fails too', async () => {
  urls = ['/storage/broken']
  loadErrors.set('/storage/broken', new Error('Unexpected server response (500)'))
  fileAnswers()
  renderWithProviders(<DocumentViewer documentId="d1" page={1} onPageChange={() => {}} />)
  expect(await screen.findByRole('alert')).toHaveTextContent(ar.viewer.error)
})
