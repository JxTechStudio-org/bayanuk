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
  // The browser reads "\" as "/", so this is "//example.com", another site.
  expect(safePath('/\\example.com')).toBe('/')
  expect(safePath('javascript:alert(1)')).toBe('/')
  expect(safePath(null)).toBe('/')
})

it('keeps the query and the hash of an address inside the site', () => {
  expect(safePath('/documents?page=2#top')).toBe('/documents?page=2#top')
})

it('returns the saved page once', () => {
  saveReturnTo('/d/123/result')
  expect(takeReturnTo()).toBe('/d/123/result')
  expect(takeReturnTo()).toBe('/')
})
