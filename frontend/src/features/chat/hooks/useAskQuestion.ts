import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useCurrentUser } from '@/features/auth/hooks/useCurrentUser'
import { ApiError } from '@/lib/api'
import { askQuestion } from '../api/askQuestion'
import type { ChatHistory } from '../types'
import { chatKey } from './useChat'

// Sends a question. On an answer, the question and the answer are added to the history on screen,
// with the questions left from the backend (contract, section 8: the frontend never calculates it).
// A question is never repeated on its own: each one may use one of the questions left.
export function useAskQuestion(documentId: string) {
  const queryClient = useQueryClient()
  const { data: user } = useCurrentUser()

  return useMutation({
    mutationFn: (question: string) => askQuestion(documentId, question, Boolean(user)),
    retry: false,
    onSuccess: (answer, question) => {
      const now = new Date().toISOString()
      queryClient.setQueryData<ChatHistory>(chatKey(documentId), (history) => ({
        messages: [
          ...(history?.messages ?? []),
          { role: 'user', content: question, citations: [], answer_found: null, created_at: now },
          {
            role: 'assistant',
            content: answer.answer,
            citations: answer.citations,
            answer_found: answer.answer_found,
            created_at: now,
          },
        ],
        remaining_questions: answer.remaining_questions,
      }))
    },
    // question_limit_reached: the backend says no question is left, so the page shows 0 and stops asking.
    onError: (error) => {
      if (error instanceof ApiError && error.code === 'question_limit_reached') {
        queryClient.setQueryData<ChatHistory>(chatKey(documentId), (history) =>
          history ? { ...history, remaining_questions: 0 } : history,
        )
      }
    },
  })
}
