import { expect, it } from 'vitest'
import { MAX_FILE_SIZE, validateFile } from './validateFile'

function fileOf(name: string, type: string, size = 1000) {
  const file = new File(['x'], name, { type })
  Object.defineProperty(file, 'size', { value: size })
  return file
}

it('accepts PDF and DOCX', () => {
  expect(validateFile(fileOf('offer.pdf', 'application/pdf'))).toBeNull()
  expect(
    validateFile(fileOf('contract.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')),
  ).toBeNull()
})

it('refuses other types', () => {
  expect(validateFile(fileOf('photo.jpg', 'image/jpeg'))).toBe('type')
  expect(validateFile(fileOf('old.doc', 'application/msword'))).toBe('type')
})

it('refuses files over 10 MB', () => {
  expect(validateFile(fileOf('big.pdf', 'application/pdf', MAX_FILE_SIZE + 1))).toBe('size')
})
