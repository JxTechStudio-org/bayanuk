import { CloudUpload, LoaderCircle } from 'lucide-react'
import { useCallback, useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { ApiError } from '@/lib/api'
import { useUploadDocument } from '../hooks/useUploadDocument'
import { type FileProblem, validateFile } from '../utils/validateFile'

// MVP: PDF or Word (DOCX) only.
const ACCEPTED = '.pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document'

function hasFiles(event: DragEvent) {
  return Array.from(event.dataTransfer?.types ?? []).includes('Files')
}

// The "Choose a file" button. A file can also be dropped anywhere on the page.
// The file is checked here first (MVP 5, step 2), then sent to the API.
export function UploadEntry() {
  const { t } = useTranslation()
  const input = useRef<HTMLInputElement>(null)
  const [problem, setProblem] = useState<FileProblem | null>(null)
  const [dragging, setDragging] = useState(false)
  const upload = useUploadDocument()
  const { mutate } = upload

  const handleFile = useCallback(
    (file: File) => {
      const found = validateFile(file)
      setProblem(found)
      if (!found) mutate(file)
    },
    [mutate],
  )

  useEffect(() => {
    const onDragOver = (event: DragEvent) => {
      if (!hasFiles(event)) return
      event.preventDefault()
      setDragging(true)
    }
    const onDragLeave = (event: DragEvent) => {
      // relatedTarget is null when the pointer leaves the window.
      if (!event.relatedTarget) setDragging(false)
    }
    const onDrop = (event: DragEvent) => {
      if (!hasFiles(event)) return
      event.preventDefault()
      setDragging(false)
      const file = event.dataTransfer?.files[0]
      if (file) handleFile(file)
    }
    window.addEventListener('dragover', onDragOver)
    window.addEventListener('dragleave', onDragLeave)
    window.addEventListener('drop', onDrop)
    return () => {
      window.removeEventListener('dragover', onDragOver)
      window.removeEventListener('dragleave', onDragLeave)
      window.removeEventListener('drop', onDrop)
    }
  }, [handleFile])

  // The API sends a translated message; our own text is the fallback.
  const apiMessage =
    upload.error instanceof ApiError && upload.error.message ? upload.error.message : t('upload.errors.generic')
  const message = problem ? t(`upload.errors.${problem}`) : upload.isError ? apiMessage : null

  return (
    <div>
      <Button
        size="lg"
        className="h-12 px-6 text-base font-semibold"
        disabled={upload.isPending}
        onClick={() => input.current?.click()}
      >
        {upload.isPending ? (
          <LoaderCircle aria-hidden="true" className="size-5 animate-spin motion-reduce:animate-none" />
        ) : (
          <CloudUpload aria-hidden="true" className="size-5" />
        )}
        {upload.isPending ? t('upload.uploading') : t('upload.choose')}
      </Button>
      <input
        ref={input}
        type="file"
        accept={ACCEPTED}
        hidden
        onChange={(event) => {
          const file = event.target.files?.[0]
          if (file) handleFile(file)
          event.target.value = ''
        }}
      />
      {message ? (
        <p role="alert" className="mt-3 text-sm text-destructive">
          {message}
        </p>
      ) : null}

      {dragging ? (
        <div className="pointer-events-none fixed inset-0 z-[60] grid place-items-center bg-ink/75 p-6">
          <div className="rounded-2xl border-2 border-dashed border-teal-light px-10 py-8 text-center text-lg font-semibold text-white">
            <CloudUpload aria-hidden="true" className="mx-auto mb-3 size-10 text-teal-light" />
            {t('upload.dropHere')}
          </div>
        </div>
      ) : null}
    </div>
  )
}
