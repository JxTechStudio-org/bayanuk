const STORAGE_KEY = 'bayanuk.guestToken'

// Integration contract, section 5: the backend issues the guest token, and the frontend
// keeps it in the browser. If storage is blocked, the token is kept for this visit only.
let memoryToken: string | null = null

export function readGuestToken(): string | null {
  try {
    return localStorage.getItem(STORAGE_KEY)
  } catch {
    return memoryToken
  }
}

export function saveGuestToken(token: string) {
  memoryToken = token
  try {
    localStorage.setItem(STORAGE_KEY, token)
  } catch {
    // Storage is blocked: the token stays in memory.
  }
}

export function clearGuestToken() {
  memoryToken = null
  try {
    localStorage.removeItem(STORAGE_KEY)
  } catch {
    // Storage is blocked: nothing was saved there.
  }
}
