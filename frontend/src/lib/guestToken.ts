const STORAGE_KEY = 'bayanuk.guestToken'

// Technical Plan, section 7: guests send an X-Guest-Token header,
// a random value created by the frontend and kept in the browser.
function createToken(): string {
  const bytes = new Uint8Array(32)
  crypto.getRandomValues(bytes)
  return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('')
}

let memoryToken: string | null = null

export function getGuestToken(): string {
  try {
    let token = localStorage.getItem(STORAGE_KEY)
    if (!token) {
      token = createToken()
      localStorage.setItem(STORAGE_KEY, token)
    }
    return token
  } catch {
    // Storage is blocked: keep one token for this visit.
    memoryToken ??= createToken()
    return memoryToken
  }
}
