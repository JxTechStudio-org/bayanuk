import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import ar from '../locales/ar.json'
import en from '../locales/en.json'

const STORAGE_KEY = 'bayanuk.locale'

// Language saved from an earlier visit, otherwise Arabic.
const saved = localStorage.getItem(STORAGE_KEY)
const initialLanguage = saved === 'en' ? 'en' : 'ar'

// Sets <html lang> and <html dir>, which flips the whole page layout.
function applyDirection(language: string) {
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

applyDirection(initialLanguage)

i18n.on('languageChanged', (language) => {
  applyDirection(language)
  localStorage.setItem(STORAGE_KEY, language)
})

export default i18n