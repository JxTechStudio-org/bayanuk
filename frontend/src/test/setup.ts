import '@testing-library/jest-dom/vitest'
import { cleanup, configure } from '@testing-library/react'
import { afterEach, beforeEach, vi } from 'vitest'

// A page that asks who is signed in, then loads its data, makes two requests before it shows anything.
// findBy... waits up to 3 seconds for that (the default is 1), so a slower computer does not fail the tests.
configure({ asyncUtilTimeout: 3000 })

// jsdom has no screen size: every media query answers "no", as on a phone. A test can set another answer.
// It has no ResizeObserver either; boxes keep their size.
vi.stubGlobal('matchMedia', (query: string) => ({
  matches: false,
  media: query,
  addEventListener: () => {},
  removeEventListener: () => {},
}))
vi.stubGlobal(
  'ResizeObserver',
  class {
    observe() {}
    disconnect() {}
  },
)

// Each test starts as a guest who already has a token, so it can focus on its own screen.
// src/lib/api.test.ts covers how the token and the CSRF cookie are obtained.
beforeEach(() => {
  localStorage.setItem('bayanuk.guestToken', 'test-guest-token')
})

// Remove what each test rendered and stored, so tests do not affect each other.
afterEach(() => {
  cleanup()
  localStorage.clear()
  document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/'
})
