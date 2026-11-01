// Integration contract, section 14. A citation is a page and a short quote from the document.
export interface Citation {
  page: number
  quote: string
}

// One message of the history, with the columns of chat_messages (Database Schema).
// answer_found is null on the user's messages.
export interface ChatMessage {
  role: 'user' | 'assistant'
  content: string
  citations: Citation[]
  answer_found: boolean | null
  created_at: string
}

// GET /documents/{id}/chat
export interface ChatHistory {
  messages: ChatMessage[]
  remaining_questions: number
}

// POST /documents/{id}/chat
export interface ChatAnswer {
  answer: string
  citations: Citation[]
  answer_found: boolean
  remaining_questions: number
}
