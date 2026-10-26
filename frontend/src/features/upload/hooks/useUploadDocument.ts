import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { currentUserQuery } from '@/features/auth/hooks/useCurrentUser'
import { uploadDocument } from '../api/uploadDocument'

// Sends the file, then opens the processing screen for the new document.
// The file name goes with it, so the processing screen can show which file is being read.
export function useUploadDocument() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (file: File) => {
      // Waits until it is known whether the visitor is signed in, so a signed-in user never sends a guest token,
      // even when the file is chosen before GET /me has answered (integration contract, section 5).
      const user = await queryClient.ensureQueryData(currentUserQuery)
      return uploadDocument(file, Boolean(user))
    },
    onSuccess: (document, file) => navigate(`/d/${document.id}`, { state: { fileName: file.name } }),
  })
}
