import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { ProxyServerItem, SystemSettingsData } from '@/types/admin'
import {
  getAdminSettingsApi,
  getAdminProxiesApi,
  addProxiesApi,
  toggleProxyApi,
  deleteProxyApi,
} from '@/services/api'

export const useAdminStore = defineStore('admin', () => {
  const settings = ref<SystemSettingsData | null>(null)
  const proxies = ref<ProxyServerItem[]>([])
  const isLoadingSettings = ref<boolean>(false)
  const isLoadingProxies = ref<boolean>(false)
  const isSubmitting = ref<boolean>(false)
  const error = ref<string | null>(null)
  const actionSuccess = ref<string | null>(null)

  const fetchSettings = async (): Promise<void> => {
    isLoadingSettings.value = true
    error.value = null
    try {
      settings.value = await getAdminSettingsApi()
    } catch (err: unknown) {
      if (typeof err === 'object' && err !== null && 'response' in err) {
        const axiosErr = err as { response?: { data?: { message?: string } } }
        error.value = axiosErr.response?.data?.message ?? 'Не удалось загрузить системные настройки'
      } else if (err instanceof Error) {
        error.value = err.message
      } else {
        error.value = 'Ошибка загрузки системных настроек'
      }
    } finally {
      isLoadingSettings.value = false
    }
  }

  const fetchProxies = async (): Promise<void> => {
    isLoadingProxies.value = true
    error.value = null
    try {
      const res = await getAdminProxiesApi()
      proxies.value = res.data
    } catch (err: unknown) {
      if (typeof err === 'object' && err !== null && 'response' in err) {
        const axiosErr = err as { response?: { data?: { message?: string } } }
        error.value = axiosErr.response?.data?.message ?? 'Не удалось загрузить список прокси'
      } else if (err instanceof Error) {
        error.value = err.message
      } else {
        error.value = 'Ошибка загрузки списка прокси'
      }
    } finally {
      isLoadingProxies.value = false
    }
  }

  const addProxies = async (rawProxies: string[]): Promise<boolean> => {
    isSubmitting.value = true
    error.value = null
    actionSuccess.value = null
    try {
      const res = await addProxiesApi(rawProxies)
      actionSuccess.value = res.message ?? `Добавлено прокси: ${res.count}`
      await fetchProxies()
      await fetchSettings()
      return true
    } catch (err: unknown) {
      if (typeof err === 'object' && err !== null && 'response' in err) {
        const axiosErr = err as { response?: { data?: { message?: string } } }
        error.value = axiosErr.response?.data?.message ?? 'Не удалось добавить прокси'
      } else if (err instanceof Error) {
        error.value = err.message
      } else {
        error.value = 'Ошибка добавления прокси'
      }
      return false
    } finally {
      isSubmitting.value = false
    }
  }

  const toggleProxy = async (id: number): Promise<boolean> => {
    error.value = null
    try {
      const res = await toggleProxyApi(id)
      const index = proxies.value.findIndex((p) => p.id === id)
      if (index !== -1) {
        proxies.value[index] = res.data
      }
      await fetchSettings()
      return true
    } catch (err: unknown) {
      if (typeof err === 'object' && err !== null && 'response' in err) {
        const axiosErr = err as { response?: { data?: { message?: string } } }
        error.value = axiosErr.response?.data?.message ?? 'Не удалось изменить статус прокси'
      } else if (err instanceof Error) {
        error.value = err.message
      } else {
        error.value = 'Ошибка изменения статуса прокси'
      }
      return false
    }
  }

  const deleteProxy = async (id: number): Promise<boolean> => {
    error.value = null
    try {
      await deleteProxyApi(id)
      proxies.value = proxies.value.filter((p) => p.id !== id)
      await fetchSettings()
      return true
    } catch (err: unknown) {
      if (typeof err === 'object' && err !== null && 'response' in err) {
        const axiosErr = err as { response?: { data?: { message?: string } } }
        error.value = axiosErr.response?.data?.message ?? 'Не удалось удалить прокси'
      } else if (err instanceof Error) {
        error.value = err.message
      } else {
        error.value = 'Ошибка удаления прокси'
      }
      return false
    }
  }

  const clearMessages = (): void => {
    error.value = null
    actionSuccess.value = null
  }

  return {
    settings,
    proxies,
    isLoadingSettings,
    isLoadingProxies,
    isSubmitting,
    error,
    actionSuccess,
    fetchSettings,
    fetchProxies,
    addProxies,
    toggleProxy,
    deleteProxy,
    clearMessages,
  }
})
