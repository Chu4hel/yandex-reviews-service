import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Organization, OrganizationStatus } from '@/types/organization'
import {
  getOrganizationsApi,
  getOrganizationApi,
  connectOrganizationApi,
  getOrganizationStatusApi,
  syncOrganizationApi,
  deleteOrganizationApi,
} from '@/services/api'

export const useOrganizationsStore = defineStore('organizations', () => {
  const organizations = ref<Organization[]>([])
  const currentOrganization = ref<Organization | null>(null)
  const isLoading = ref<boolean>(false)
  const error = ref<string | null>(null)

  const fetchOrganizations = async (): Promise<void> => {
    isLoading.value = true
    error.value = null
    try {
      const response = await getOrganizationsApi()
      organizations.value = response.data
    } catch (err: unknown) {
      error.value = err instanceof Error ? err.message : 'Не удалось загрузить список организаций'
    } finally {
      isLoading.value = false
    }
  }

  const fetchOrganization = async (id: number): Promise<Organization | null> => {
    isLoading.value = true
    error.value = null
    try {
      const response = await getOrganizationApi(id)
      currentOrganization.value = response.data
      return response.data
    } catch (err: unknown) {
      error.value = err instanceof Error ? err.message : 'Не удалось загрузить организацию'
      return null
    } finally {
      isLoading.value = false
    }
  }

  const connectOrganization = async (url: string): Promise<Organization> => {
    isLoading.value = true
    error.value = null
    try {
      const response = await connectOrganizationApi(url)
      const existingIdx = organizations.value.findIndex((o) => o.id === response.data.id)
      if (existingIdx >= 0) {
        organizations.value[existingIdx] = response.data
      } else {
        organizations.value.unshift(response.data)
      }
      currentOrganization.value = response.data
      return response.data
    } catch (err: unknown) {
      if (typeof err === 'object' && err !== null && 'response' in err) {
        const axiosErr = err as { response?: { data?: { message?: string } } }
        const msg = axiosErr.response?.data?.message ?? 'Ошибка подключения карточки'
        error.value = msg
        throw new Error(msg, { cause: err })
      }
      const msg = err instanceof Error ? err.message : 'Не удалось подключить организацию'
      error.value = msg
      throw new Error(msg, { cause: err })
    } finally {
      isLoading.value = false
    }
  }

  const updateStatus = async (id: number): Promise<OrganizationStatus | null> => {
    try {
      const response = await getOrganizationStatusApi(id)
      const statusData = response.data

      // Обновление состояния в общем списке организаций
      const org = organizations.value.find((o) => o.id === id)
      if (org) {
        org.sync_status = statusData.sync_status
        org.sync_progress = statusData.sync_progress
        org.sync_message = statusData.sync_message
        org.db_reviews_count = statusData.db_reviews_count
        org.last_synced_at = statusData.last_synced_at
        org.last_sync_error = statusData.last_sync_error
        org.rating = statusData.rating
        org.ratings_count = statusData.ratings_count
        org.reviews_count = statusData.reviews_count
      }

      // Обновление детального представления активной организации
      if (currentOrganization.value?.id === id) {
        currentOrganization.value.sync_status = statusData.sync_status
        currentOrganization.value.sync_progress = statusData.sync_progress
        currentOrganization.value.sync_message = statusData.sync_message
        currentOrganization.value.db_reviews_count = statusData.db_reviews_count
        currentOrganization.value.last_synced_at = statusData.last_synced_at
        currentOrganization.value.last_sync_error = statusData.last_sync_error
        currentOrganization.value.rating = statusData.rating
        currentOrganization.value.ratings_count = statusData.ratings_count
        currentOrganization.value.reviews_count = statusData.reviews_count
      }

      return statusData
    } catch {
      return null
    }
  }

  const triggerSync = async (id: number, syncNow = false): Promise<void> => {
    try {
      const response = await syncOrganizationApi(id, syncNow)
      const updated = response.data
      const idx = organizations.value.findIndex((o) => o.id === id)
      if (idx >= 0) {
        organizations.value[idx] = updated
      }
      if (currentOrganization.value?.id === id) {
        currentOrganization.value = updated
      }
    } catch (err: unknown) {
      throw err
    }
  }

  const deleteOrganization = async (id: number): Promise<void> => {
    isLoading.value = true
    error.value = null
    try {
      await deleteOrganizationApi(id)
      organizations.value = organizations.value.filter((o) => o.id !== id)
      if (currentOrganization.value?.id === id) {
        currentOrganization.value = null
      }
    } catch (err: unknown) {
      if (typeof err === 'object' && err !== null && 'response' in err) {
        const axiosErr = err as { response?: { data?: { message?: string } } }
        const msg = axiosErr.response?.data?.message ?? 'Ошибка удаления организации'
        error.value = msg
        throw new Error(msg, { cause: err })
      }
      const msg = err instanceof Error ? err.message : 'Не удалось удалить организацию'
      error.value = msg
      throw new Error(msg, { cause: err })
    } finally {
      isLoading.value = false
    }
  }

  // Управление таймером обратного отсчёта при ограничении частоты запросов (429 Too Many Requests)
  const syncCooldowns = ref<Record<number, number>>({})
  let cooldownInterval: number | null = null

  const startCooldownTimer = (): void => {
    if (cooldownInterval !== null) return
    cooldownInterval = window.setInterval(() => {
      let hasActive = false
      for (const idStr of Object.keys(syncCooldowns.value)) {
        const id = Number(idStr)
        if (syncCooldowns.value[id] > 0) {
          syncCooldowns.value[id]--
          hasActive = true
        }
      }
      if (!hasActive && cooldownInterval !== null) {
        clearInterval(cooldownInterval)
        cooldownInterval = null
      }
    }, 1000)
  }

  const setSyncCooldown = (id: number, seconds: number): void => {
    syncCooldowns.value[id] = seconds
    startCooldownTimer()
  }

  const getSyncCooldown = (id: number): number => {
    return syncCooldowns.value[id] ?? 0
  }

  return {
    organizations,
    currentOrganization,
    isLoading,
    error,
    syncCooldowns,
    fetchOrganizations,
    fetchOrganization,
    connectOrganization,
    updateStatus,
    triggerSync,
    deleteOrganization,
    setSyncCooldown,
    getSyncCooldown,
  }
})
