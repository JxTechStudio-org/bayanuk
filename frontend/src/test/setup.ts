import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach } from 'vitest'

// Remove what each test rendered, so tests do not affect each other.
afterEach(() => {
  cleanup()
})