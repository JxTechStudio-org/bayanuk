import i18n from '@/app/i18n'
import { getGuestToken } from './guestToken'

// Technical Plan, section 7: JSON under /api/v1, one error format { error: { code, message } }.
const BASE_URL = '/api/v1'

export class ApiError extends Error {
  readonly status: number
  readonly code: string

  constructor(status: number, code: string, message: string) {
    super(message)
    this.status = status
    this.code = code
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
    throw new ApiError(0, 'network_error', '')
  }

  if (!response.ok) {
    const body = await response.json().catch(() => null)
    throw new ApiError(response.status, body?.error?.code ?? 'unknown', body?.error?.message ?? '')
  }

  return response.json() as Promise<T>
}
