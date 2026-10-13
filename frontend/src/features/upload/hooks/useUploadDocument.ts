import { useMutation } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { uploadDocument } from '../api/uploadDocument'

// Sends the file, then opens the processing screen for the new document.
export function useUploadDocument() {
  const navigate = useNavigate()
  return useMutation({
    mutationFn: uploadDocument,
    onSuccess: (document) => navigate(`/d/${document.id}`),
  })
}
