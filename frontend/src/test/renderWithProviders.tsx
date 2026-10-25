import { QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { createQueryClient } from '@/app/queryClient'

// Renders a component with what the app gives it in real use: the app's query client and a router.
// Reads are not retried, so a test sees an error at once.
// `path` is the address the router starts at, for pages that read it (for example /d/123).
export function renderWithProviders(ui: ReactNode, { path = '/' }: { path?: string } = {}) {
  const client = createQueryClient()
  client.setDefaultOptions({ queries: { retry: false }, mutations: { retry: false } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[path]}>{ui}</MemoryRouter>
    </QueryClientProvider>,
  )
}
