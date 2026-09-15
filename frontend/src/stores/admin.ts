import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { CheckPoolStats, ProxyServerItem, SystemSettingsData } from '@/types/admin'
import {
  getAdminSettingsApi,
  getAdminProxiesApi,
  addProxiesApi,
  toggleProxyApi,
  deleteProxyApi,
  deleteInvalidProxiesApi,
  checkAllProxiesApi,
  pingProxyApi,
} from '@/services/api'

export const useAdminStore = defineStore('admin', () => {
  const settings = ref<SystemSettingsData | null>(null)
  const proxies = ref<ProxyServerItem[]>([])
  const pingingProxyIds = ref<number[]>([])
  const isLoadingSettings = ref<boolean>(false)
  const isLoadingProxies = ref<boolean>(false)
  const isSubmitting = ref<boolean>(false)
  const isDeletingInvalid = ref<boolean>(false)
  const isCheckingPool = ref<boolean>(false)
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

  const deleteInvalidProxies = async (key?: string): Promise<{ success: boolean; deletedCount: number; message: string }> => {
    isDeletingInvalid.value = true
    error.value = null
    try {
      const res = await deleteInvalidProxiesApi(key)
      proxies.value = proxies.value.filter((p) => p.is_active)
      actionSuccess.value = res.message
      await fetchSettings()
      return { success: true, deletedCount: res.deleted_count, message: res.message }
    } catch (err: unknown) {
      let msg = 'Не удалось удалить невалидные прокси'
      if (typeof err === 'object' && err !== null && 'response' in err) {
        const axiosErr = err as { response?: { data?: { message?: string } } }
        msg = axiosErr.response?.data?.message ?? msg
      } else if (err instanceof Error) {
        msg = err.message
      }
      error.value = msg
      return { success: false, deletedCount: 0, message: msg }
    } finally {
      isDeletingInvalid.value = false
    }
  }

  const pingProxy = async (id: number): Promise<boolean> => {
    if (pingingProxyIds.value.includes(id)) {
      return false
    }
    pingingProxyIds.value.push(id)
    error.value = null
    try {
      const res = await pingProxyApi(id)
      const index = proxies.value.findIndex((p) => p.id === id)
      if (index !== -1) {
        proxies.value[index] = res.proxy
      }
      if (res.success) {
        actionSuccess.value = `Пинг успешен (${res.ping_ms} мс)! Прокси активен.`
      } else if (res.is_captcha) {
        actionSuccess.value = `Прокси под капчей (${res.ping_ms} мс). Отправлен на 30 мин охлаждения.`
      } else {
        error.value = `Пинг не удался: ${res.error ?? 'Таймаут соединения'}`
      }
      await fetchSettings()
      return res.success
    } catch (err: unknown) {
      if (typeof err === 'object' && err !== null && 'response' in err) {
        const axiosErr = err as { response?: { data?: { message?: string } } }
        error.value = axiosErr.response?.data?.message ?? 'Не удалось выполнить пинг прокси'
      } else if (err instanceof Error) {
        error.value = err.message
      } else {
        error.value = 'Ошибка выполнения пинга прокси'
      }
      return false
    } finally {
      pingingProxyIds.value = pingingProxyIds.value.filter((item) => item !== id)
    }
  }

  const checkAllProxies = async (options?: {
    timeout?: number
    all?: boolean
    deleteDead?: boolean
  }): Promise<{ success: boolean; message: string; stats?: CheckPoolStats }> => {
    isCheckingPool.value = true
    error.value = null
    try {
      const res = await checkAllProxiesApi({
        timeout: options?.timeout ?? 6,
        all: options?.all ?? false,
        delete_dead: options?.deleteDead ?? false,
      })
      proxies.value = res.proxies
      actionSuccess.value = res.message
      await fetchSettings()
      return { success: true, message: res.message, stats: res.stats }
    } catch (err: unknown) {
      let msg = 'Не удалось выполнить массовую проверку пула прокси'
      if (typeof err === 'object' && err !== null && 'response' in err) {
        const axiosErr = err as { response?: { data?: { message?: string } } }
        msg = axiosErr.response?.data?.message ?? msg
      } else if (err instanceof Error) {
        msg = err.message
      }
      error.value = msg
      return { success: false, message: msg }
    } finally {
      isCheckingPool.value = false
    }
  }

  const clearMessages = (): void => {
    error.value = null
    actionSuccess.value = null
  }

  return {
    settings,
    proxies,
    pingingProxyIds,
    isLoadingSettings,
    isLoadingProxies,
    isSubmitting,
    isDeletingInvalid,
    isCheckingPool,
    error,
    actionSuccess,
    fetchSettings,
    fetchProxies,
    addProxies,
    toggleProxy,
    deleteProxy,
    deleteInvalidProxies,
    checkAllProxies,
    pingProxy,
    clearMessages,
  }
})
