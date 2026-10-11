import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import ar from '../locales/ar.json'
import en from '../locales/en.json'

const STORAGE_KEY = 'bayanuk.locale'

// Browser storage can be blocked by the user's settings.
// The site must still work then, just without remembering the choice.
function readSavedLanguage(): string | null {
  try {
    return localStorage.getItem(STORAGE_KEY)
  } catch {
    return null
  }
}

function saveLanguage(language: string) {
  try {
    localStorage.setItem(STORAGE_KEY, language)
  } catch {
    // Storage is blocked: nothing to do.
  }
}

// Language saved from an earlier visit, otherwise Arabic.
const initialLanguage = readSavedLanguage() === 'en' ? 'en' : 'ar'

// Sets <html lang>, <html dir> (which flips the whole layout) and the tab title.
function applyLanguage(language: string) {
  document.documentElement.lang = language
  document.documentElement.dir = language === 'ar' ? 'rtl' : 'ltr'
  document.title = language === 'ar' ? ar.title : en.title
}

i18n.use(initReactI18next).init({
  resources: {
    ar: { translation: ar },
    en: { translation: en },
  },
  lng: initialLanguage,
  fallbackLng: 'ar',
  interpolation: { escapeValue: false },
})

applyLanguage(initialLanguage)

i18n.on('languageChanged', (language) => {
  applyLanguage(language)
  saveLanguage(language)
})

export default i18n