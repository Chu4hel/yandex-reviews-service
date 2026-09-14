import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User } from '@/types/auth'
import { loginApi, logoutApi, getProfileApi } from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const user = ref<User | null>(
    localStorage.getItem('auth_user')
      ? (JSON.parse(localStorage.getItem('auth_user') as string) as User)
      : null
  )
  const isLoading = ref<boolean>(false)
  const error = ref<string | null>(null)

  const isAuthenticated = computed<boolean>(() => !!token.value)
  const isAdmin = computed<boolean>(() => !!user.value?.is_admin)

  const login = async (email: string, password: string): Promise<boolean> => {
    isLoading.value = true
    error.value = null
    try {
      const response = await loginApi(email, password)
      token.value = response.token
      user.value = response.user
      localStorage.setItem('auth_token', response.token)
      localStorage.setItem('auth_user', JSON.stringify(response.user))
      return true
    } catch (err: unknown) {
      if (typeof err === 'object' && err !== null && 'response' in err) {
        const axiosErr = err as { response?: { data?: { message?: string } } }
        error.value = axiosErr.response?.data?.message ?? 'Ошибка авторизации'
      } else if (err instanceof Error) {
        error.value = err.message
      } else {
        error.value = 'Произошла непредвиденная ошибка при входе'
      }
      return false
    } finally {
      isLoading.value = false
    }
  }

  const logout = async (): Promise<void> => {
    try {
      if (token.value) {
        await logoutApi()
      }
    } catch {
      // Игнорируем сетевые сбои при выходе из системы
    } finally {
      token.value = null
      user.value = null
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_user')
    }
  }

  const fetchProfile = async (): Promise<void> => {
    if (!token.value) return
    try {
      const response = await getProfileApi()
      user.value = response.user
      localStorage.setItem('auth_user', JSON.stringify(response.user))
    } catch {
      await logout()
    }
  }

  return {
    token,
    user,
    isLoading,
    error,
    isAuthenticated,
    isAdmin,
    login,
    logout,
    fetchProfile,
  }
})
