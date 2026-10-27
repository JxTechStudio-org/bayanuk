import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import '@/app/i18n'
import ar from '@/locales/ar.json'
import { renderWithProviders } from '@/test/renderWithProviders'
import { DeleteDocument } from './DeleteDocument'

// The visitor is a guest. The delete request answers with this status (204 has no body).
function deleteAnswers(status: number) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation(async (input, init) => {
    const url = String(input)
    if (url === '/api/v1/me') return Response.json({ error: { code: 'unauthorized', message: '' } }, { status: 401 })
    if (url === '/api/v1/guest-token') return Response.json({ token: 'guest-1' }, { status: 201 })
    if (url === '/api/v1/documents/123' && init?.method === 'DELETE') {
      return status === 204
        ? new Response(null, { status })
        : Response.json({ error: { code: 'server_error', message: '' } }, { status })
    }
    return new Response(null, { status: 204 })
  })
}

function renderButton() {
  renderWithProviders(
    <Routes>
      <Route path="/d/:id/result" element={<DeleteDocument id="123" />} />
      <Route path="/" element={<p>home page</p>} />
    </Routes>,
    { path: '/d/123/result' },
  )
}

afterEach(() => {
  vi.restoreAllMocks()
})

it('asks before deleting, and deletes nothing on cancel', async () => {
  const user = userEvent.setup()
  const fetch = deleteAnswers(204)
  renderButton()
  await user.click(screen.getByRole('button', { name: ar.result.delete.button }))
  expect(screen.getByRole('alertdialog', { name: ar.result.delete.title })).toBeInTheDocument()

  await user.click(screen.getByRole('button', { name: ar.result.delete.cancel }))
  expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  expect(fetch.mock.calls.some(([, init]) => init?.method === 'DELETE')).toBe(false)
})

it('deletes the document and goes to the home page', async () => {
  const user = userEvent.setup()
  const fetch = deleteAnswers(204)
  renderButton()
  await user.click(screen.getByRole('button', { name: ar.result.delete.button }))
  await user.click(screen.getByRole('button', { name: ar.result.delete.confirm }))

  expect(await screen.findByText('home page')).toBeInTheDocument()
  expect(fetch.mock.calls.some(([url, init]) => url === '/api/v1/documents/123' && init?.method === 'DELETE')).toBe(true)
})

it('treats a document that is already gone (404) as deleted', async () => {
  const user = userEvent.setup()
  deleteAnswers(404)
  renderButton()
  await user.click(screen.getByRole('button', { name: ar.result.delete.button }))
  await user.click(screen.getByRole('button', { name: ar.result.delete.confirm }))
  expect(await screen.findByText('home page')).toBeInTheDocument()
})

it('keeps the dialog open with a message when deleting fails', async () => {
  const user = userEvent.setup()
  deleteAnswers(500)
  renderButton()
  await user.click(screen.getByRole('button', { name: ar.result.delete.button }))
  await user.click(screen.getByRole('button', { name: ar.result.delete.confirm }))
  expect(await screen.findByRole('alert')).toHaveTextContent(ar.result.delete.error)
  expect(screen.getByRole('alertdialog')).toBeInTheDocument()
})
