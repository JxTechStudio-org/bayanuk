import { ApiError, request } from '@/lib/api'
import type { User } from '../types'

// GET /me: the signed-in user, or null for a guest (the API answers 401).
export async function getCurrentUser(): Promise<User | null> {
  try {
    return await request<User>('/me')
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) return null
    throw error
  }
}
