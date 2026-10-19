const STORAGE_KEY = 'bayanuk.returnTo'

// Only an address inside this site, such as "/d/123/result". Anything else (another site, "//...") goes home.
export function safePath(path: string | null | undefined): string {
  return path && path.startsWith('/') && !path.startsWith('//') ? path : '/'
}

// Integration contract, section 11: the page the user was on is saved before leaving for sign-in,
// and the user returns to it afterwards. It is kept in the browser, because the email link
// and Google both come back in a new page load.
export function saveReturnTo(path: string) {
  try {
    localStorage.setItem(STORAGE_KEY, safePath(path))
  } catch {
    // Storage is blocked: the user returns to the home page.
  }
}

// Reads the saved page once, then forgets it.
export function takeReturnTo(): string {
  try {
    const path = localStorage.getItem(STORAGE_KEY)
    localStorage.removeItem(STORAGE_KEY)
    return safePath(path)
  } catch {
    return '/'
  }
}
