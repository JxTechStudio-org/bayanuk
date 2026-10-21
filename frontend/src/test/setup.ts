import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach, beforeEach } from 'vitest'

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
