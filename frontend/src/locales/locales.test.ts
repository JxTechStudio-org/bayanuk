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

// A text that changes with a number has one key per plural form, and Arabic has more forms than English
// (zero, one, two, few, many and other). Both languages must have the same texts, so the forms are put together.
const PLURAL_FORM = /_(zero|one|two|few|many|other)$/

function texts(value: object): string[] {
  return [...new Set(allKeys(value).map((key) => key.replace(PLURAL_FORM, '')))].sort()
}

it('has the same keys in Arabic and English', () => {
  expect(texts(ar)).toEqual(texts(en))
})

it('has every Arabic plural form for each number text', () => {
  const keys = allKeys(ar)
  const numberTexts = new Set(keys.filter((key) => PLURAL_FORM.test(key)).map((key) => key.replace(PLURAL_FORM, '')))
  for (const text of numberTexts) {
    for (const form of ['zero', 'one', 'two', 'few', 'many', 'other']) expect(keys).toContain(`${text}_${form}`)
  }
})
