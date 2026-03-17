import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export type Theme = 'light' | 'dark'

const THEME_STORAGE_KEY = 'app-theme'

const getInitialTheme = (): Theme => {
  // Only use this in browser environment
  if (typeof window === 'undefined') return 'light'

  // Check localStorage first
  const stored = localStorage.getItem(THEME_STORAGE_KEY)
  if (stored === 'light' || stored === 'dark') {
    return stored
  }

  // Check system preference
  if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    return 'dark'
  }

  return 'light'
}

const applyThemeToDOM = (theme: Theme) => {
  const html = document.documentElement
  if (theme === 'dark') {
    html.classList.add('dark')
  } else {
    html.classList.remove('dark')
  }
}

export const useThemeStore = defineStore('theme', () => {
  const theme = ref<Theme>(getInitialTheme())

  const isDark = computed(() => theme.value === 'dark')

  const setTheme = (newTheme: Theme) => {
    theme.value = newTheme
    localStorage.setItem(THEME_STORAGE_KEY, newTheme)
    applyThemeToDOM(newTheme)
  }

  const toggleTheme = () => {
    const newTheme = isDark.value ? 'light' : 'dark'
    setTheme(newTheme)
  }

  const applyTheme = () => {
    const savedTheme = getInitialTheme()
    theme.value = savedTheme
    applyThemeToDOM(savedTheme)
  }

  return {
    theme,
    isDark,
    toggleTheme,
    setTheme,
    applyTheme,
  }
})
