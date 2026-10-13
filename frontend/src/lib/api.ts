import i18n from '@/app/i18n'
import { getGuestToken } from './guestToken'

// Technical Plan, section 7: JSON under /api/v1, one error format { error: { code, message } }.
const BASE_URL = '/api/v1'

// The HTTP status (0 when the API could not be reached) and the API's translated message.
export class ApiError extends Error {
  readonly status: number

  constructor(status: number, message: string) {
    super(message)
    this.status = status
  }
}

export async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  let response: Response
  try {
    response = await fetch(`${BASE_URL}${path}`, {
      ...init,
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Accept-Language': i18n.language,
        'X-Guest-Token': getGuestToken(),
        ...init.headers,
      },
    })
  } catch {
    throw new ApiError(0, '')
  }

  if (!response.ok) {
    const body = await response.json().catch(() => null)
    throw new ApiError(response.status, body?.error?.message ?? '')
  }

  return response.json() as Promise<T>
}
