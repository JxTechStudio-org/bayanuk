import { useMutation } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { uploadDocument } from '../api/uploadDocument'

// Sends the file, then opens the processing screen for the new document.
// The file name goes with it, so the processing screen can show which file is being read.
export function useUploadDocument() {
  const navigate = useNavigate()
  return useMutation({
    mutationFn: uploadDocument,
    onSuccess: (document, file) => navigate(`/d/${document.id}`, { state: { fileName: file.name } }),
  })
}
