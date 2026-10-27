import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, expect, it, vi } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { renderWithProviders } from '@/test/renderWithProviders'
import { PageRef } from './PageRef'
import { ViewerLayout } from './ViewerLayout'

// The real viewer draws PDF pages, which jsdom cannot. This one says which page it shows.
vi.mock('./DocumentViewer', () => ({
  default: ({ page }: { page: number }) => <p>viewer on page {page}</p>,
}))

function screenIsWide(wide: boolean) {
  vi.stubGlobal('matchMedia', (query: string) => ({
    matches: wide,
    media: query,
    addEventListener: () => {},
    removeEventListener: () => {},
  }))
}

function renderResult() {
  renderWithProviders(
    <ViewerLayout documentId="d1">
      <PageRef page={7} />
    </ViewerLayout>,
  )
}

afterEach(() => {
  screenIsWide(false)
})

it('on a wide screen, shows the document beside the result and opens the pressed page there', async () => {
  screenIsWide(true)
  renderResult()
  expect(await screen.findByText('viewer on page 1')).toBeInTheDocument()

  await userEvent.click(screen.getByRole('button', { name: 'افتح الصفحة 7 من المستند' }))
  expect(await screen.findByText('viewer on page 7')).toBeInTheDocument()
  expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
})

it('on a phone, opens the document over the page on the pressed page, and closes back to the result', async () => {
  const user = userEvent.setup()
  renderResult()
  expect(screen.queryByText(/viewer on page/)).not.toBeInTheDocument()

  const pageButton = screen.getByRole('button', { name: 'افتح الصفحة 7 من المستند' })
  await user.click(pageButton)
  expect(await screen.findByRole('dialog', { name: ar.viewer.title })).toBeInTheDocument()
  expect(await screen.findByText('viewer on page 7')).toBeInTheDocument()

  await user.click(screen.getByRole('button', { name: ar.viewer.close }))
  expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  // Back where the user was.
  expect(pageButton).toHaveFocus()
})
