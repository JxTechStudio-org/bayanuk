import { afterEach, expect, it } from 'vitest'
import { safePath, saveReturnTo, takeReturnTo } from './returnTo'

afterEach(() => {
  localStorage.clear()
})

it('keeps an address inside the site', () => {
  expect(safePath('/d/123/result')).toBe('/d/123/result')
})

it('sends any other address home', () => {
  expect(safePath('https://example.com')).toBe('/')
  expect(safePath('//example.com')).toBe('/')
  expect(safePath(null)).toBe('/')
})

it('returns the saved page once', () => {
  saveReturnTo('/d/123/result')
  expect(takeReturnTo()).toBe('/d/123/result')
  expect(takeReturnTo()).toBe('/')
})
