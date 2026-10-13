import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import './app/i18n'
import { App } from './app/App'

// In development, a fake API answers until the backend is ready (see src/mocks).
async function startFakeApi() {
  if (import.meta.env.VITE_API_MOCK !== 'true') return
  const { worker } = await import('./mocks/browser')
  await worker.start({ onUnhandledRequest: 'bypass' })
}

startFakeApi().then(() => {
  createRoot(document.getElementById('root')!).render(
    <StrictMode>
      <App />
    </StrictMode>,
  )
})
