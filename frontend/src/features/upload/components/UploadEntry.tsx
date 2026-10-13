import { CloudUpload } from 'lucide-react'
import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'

// MVP: PDF or Word (DOCX) only.
const ACCEPTED = '.pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document'

interface UploadEntryProps {
  onSelect?: (file: File) => void
}

// The "Choose a file" button. It opens the file picker; sending the file to the API comes with the upload task.
export function UploadEntry({ onSelect }: UploadEntryProps) {
  const { t } = useTranslation()
  const input = useRef<HTMLInputElement>(null)

  return (
    <>
      <Button size="lg" className="h-12 px-6 text-base font-semibold" onClick={() => input.current?.click()}>
        <CloudUpload aria-hidden="true" className="size-5" />
        {t('upload.choose')}
      </Button>
      <input
        ref={input}
        type="file"
        accept={ACCEPTED}
        hidden
        onChange={(event) => {
          const file = event.target.files?.[0]
          if (file) onSelect?.(file)
          event.target.value = ''
        }}
      />
    </>
  )
}
