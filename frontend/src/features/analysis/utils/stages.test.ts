import { expect, it } from 'vitest'
import { isFinished, STAGES, stageIndex } from './stages'

it('maps every status to a progress stage', () => {
  expect(stageIndex('uploaded')).toBe(0)
  expect(stageIndex('scanning')).toBe(0)
  expect(stageIndex('extracting')).toBe(1)
  expect(stageIndex('classifying')).toBe(2)
  expect(stageIndex('analyzing')).toBe(2)
  expect(stageIndex('indexing')).toBe(3)
  expect(stageIndex('ready')).toBe(STAGES.length)
})

it('knows when processing has ended', () => {
  expect(isFinished('analyzing')).toBe(false)
  expect(isFinished('ready')).toBe(true)
  expect(isFinished('failed')).toBe(true)
  expect(isFinished('rejected')).toBe(true)
})
