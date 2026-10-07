import { createI18n } from 'vue-i18n'
import en from './en.js'
import sw from './sw.js'

export const SUPPORTED_LOCALES = ['en', 'sw']

function readSavedLocale() {
  try {
    const saved = localStorage.getItem('shulepay_lang')
    return SUPPORTED_LOCALES.includes(saved) ? saved : 'en'
  } catch {
    // Storage can be blocked (private mode, disabled cookies) — fall back to English.
    return 'en'
  }
}

const saved = readSavedLocale()

export const i18n = createI18n({
  legacy: false,
  locale: saved,
  fallbackLocale: 'en',
  messages: { en, sw },
})

// The <html lang> attribute was only updated on a switch, so after a reload it
// kept the static value from index.html even though the UI came up in the saved
// language — wrong for screen readers, hyphenation and browser translation.
document.documentElement.setAttribute('lang', saved)

export function setLocale(lang) {
  if (!SUPPORTED_LOCALES.includes(lang)) return
  i18n.global.locale.value = lang
  try {
    localStorage.setItem('shulepay_lang', lang)
  } catch {
    // Non-fatal: the choice just won't survive a reload.
  }
  document.documentElement.setAttribute('lang', lang)
}

/** Currently active UI language: 'en' | 'sw'. Reactive when read inside a render/computed. */
export function currentLocale() {
  return i18n.global.locale.value
}

/**
 * BCP-47 tag for Intl / toLocale*String, following the UI language so month and
 * weekday names switch with it. A hardcoded 'sw-TZ' or 'en-US' at a call site
 * keeps printing the wrong language after the header switch is used.
 */
export function intlLocale() {
  return currentLocale() === 'sw' ? 'sw-TZ' : 'en-GB'
}
