import { expect, it } from 'vitest'
import ar from './ar.json'
import en from './en.json'

// Lists every key, including nested ones, as "hero.summary.title".
function allKeys(value: object, prefix = ''): string[] {
  return Object.entries(value).flatMap(([key, child]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof child === 'object' && child !== null ? allKeys(child, path) : [path]
  })
}

it('has the same keys in Arabic and English', () => {
  expect(allKeys(ar).sort()).toEqual(allKeys(en).sort())
})
