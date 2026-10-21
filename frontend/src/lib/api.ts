import i18n from '@/app/i18n'
import { clearGuestToken, readGuestToken, saveGuestToken } from './guestToken'

// Integration contract: JSON under /api/v1, one error format { error: { code, message } }.
const BASE_URL = '/api/v1'

// The HTTP status (0 when the API could not be reached) and the API's translated message.
export class ApiError extends Error {
  readonly status: number

  constructor(status: number, message: string) {
    super(message)
    this.status = status
  }
}

// The API could not be reached (no connection, for example).
const networkError = () => new ApiError(0, '')

// Integration contract, section 2: Laravel Sanctum keeps the CSRF token in the XSRF-TOKEN cookie,
// and the frontend sends it back in the X-XSRF-TOKEN header.
function readCsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)
  return match ? decodeURIComponent(match[1]) : null
}

async function fetchCsrfCookie() {
  try {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
  } catch {
    throw networkError()
  }
}

async function send(path: string, init: RequestInit, token?: string): Promise<Response> {
  const csrfToken = readCsrfToken()
  try {
    return await fetch(`${BASE_URL}${path}`, {
      ...init,
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Accept-Language': i18n.language,
        ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
        ...(token ? { 'X-Guest-Token': token } : {}),
        ...init.headers,
      },
    })
  } catch {
    throw networkError()
  }
}

async function readBody<T>(response: Response): Promise<T> {
  if (!response.ok) {
    const body = await response.json().catch(() => null)
    throw new ApiError(response.status, body?.error?.message ?? '')
  }
  return response.json() as Promise<T>
}

async function errorCode(response: Response): Promise<string | null> {
  if (response.ok) return null
  const body = await response.clone().json().catch(() => null)
  return body?.error?.code ?? null
}

// Integration contract, section 5: before the first request, the frontend asks the backend for a guest token.
// Requests made at the same moment share one call, so only one token is issued.
let pendingToken: Promise<string> | null = null

async function guestToken(): Promise<string> {
  const stored = readGuestToken()
  if (stored) return stored
  pendingToken ??= issueGuestToken().finally(() => {
    pendingToken = null
  })
  return pendingToken
}

async function issueGuestToken(): Promise<string> {
  if (!readCsrfToken()) await fetchCsrfCookie()
  const { token } = await readBody<{ token: string }>(await send('/guest-token', { method: 'POST' }))
  saveGuestToken(token)
  return token
}

export async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  // Section 2: get the CSRF cookie before the first request that changes data.
  if ((init.method ?? 'GET') !== 'GET' && !readCsrfToken()) await fetchCsrfCookie()

  let response = await send(path, init, await guestToken())

  // Section 2: an expired CSRF token (419) is refreshed, and the request is repeated once.
  // Section 5: a guest token the backend does not accept is deleted, and the request is repeated once with a new one.
  if (response.status === 419) {
    await fetchCsrfCookie()
    response = await send(path, init, await guestToken())
  } else if ((await errorCode(response)) === 'invalid_guest_token') {
    clearGuestToken()
    response = await send(path, init, await guestToken())
  }

  return readBody<T>(response)
}
