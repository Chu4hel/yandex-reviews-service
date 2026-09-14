import { ref, computed } from 'vue'

export type ThemeMode = 'light' | 'dark' | 'system'

const THEME_STORAGE_KEY = 'georeviews_theme'

const theme = ref<ThemeMode>('system')
const systemPrefersDark = ref<boolean>(false)

let isInitialized = false

const updateHtmlClass = (): void => {
  if (typeof document === 'undefined') return
  const isDarkMode = theme.value === 'dark' || (theme.value === 'system' && systemPrefersDark.value)
  if (isDarkMode) {
    document.documentElement.classList.add('dark')
  } else {
    document.documentElement.classList.remove('dark')
  }
}

export const useTheme = () => {
  if (!isInitialized && typeof window !== 'undefined') {
    // 1. Чтение сохраненного значения темы
    const saved = localStorage.getItem(THEME_STORAGE_KEY) as ThemeMode | null
    if (saved === 'light' || saved === 'dark' || saved === 'system') {
      theme.value = saved
    }

    // 2. Определение системных предпочтений
    if (typeof window.matchMedia === 'function') {
      const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
      systemPrefersDark.value = mediaQuery.matches

      if (typeof mediaQuery.addEventListener === 'function') {
        mediaQuery.addEventListener('change', (e) => {
          systemPrefersDark.value = e.matches
          if (theme.value === 'system') {
            updateHtmlClass()
          }
        })
      }
    }

    updateHtmlClass()
    isInitialized = true
  }

  const setTheme = (newTheme: ThemeMode): void => {
    theme.value = newTheme
    if (typeof window !== 'undefined') {
      localStorage.setItem(THEME_STORAGE_KEY, newTheme)
    }
    updateHtmlClass()
  }

  const cycleTheme = (): void => {
    if (theme.value === 'light') {
      setTheme('dark')
    } else if (theme.value === 'dark') {
      setTheme('system')
    } else {
      setTheme('light')
    }
  }

  const isDark = computed<boolean>(() => {
    return theme.value === 'dark' || (theme.value === 'system' && systemPrefersDark.value)
  })

  return {
    theme,
    isDark,
    setTheme,
    cycleTheme,
  }
}
