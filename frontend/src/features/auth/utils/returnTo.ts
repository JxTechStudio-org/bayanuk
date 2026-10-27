const STORAGE_KEY = 'bayanuk.returnTo'

// Only an address inside this site, such as "/d/123/result". Anything else goes home.
// The address is read the way the browser reads it, then its site is compared with ours,
// so every way of writing another site ("https://...", "//...", "/\...") is caught.
export function safePath(path: string | null | undefined): string {
  if (!path) return '/'
  try {
    const url = new URL(path, window.location.origin)
    return url.origin === window.location.origin ? url.pathname + url.search + url.hash : '/'
  } catch {
    return '/'
  }
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

// Reads the saved page without forgetting it, for a sign-in that has to start again.
export function peekReturnTo(): string {
  try {
    return safePath(localStorage.getItem(STORAGE_KEY))
  } catch {
    return '/'
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
