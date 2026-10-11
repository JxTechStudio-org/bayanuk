import { expect, it } from 'vitest'
import ar from './ar.json'
import en from './en.json'

it('has the same keys in Arabic and English', () => {
  expect(Object.keys(ar).sort()).toEqual(Object.keys(en).sort())
})