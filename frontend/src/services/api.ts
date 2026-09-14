import axios from 'axios'
import type { LoginResponse, User } from '@/types/auth'
import type { Organization, OrganizationStatus } from '@/types/organization'
import type { PaginatedReviewsResponse } from '@/types/review'
import type { OrganizationSnapshot } from '@/types/snapshot'
import type { ProxyServerItem, SystemSettingsData } from '@/types/admin'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// Прикрепление токена авторизации Bearer к исходящим запросам
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

let lastRequestId: string | null = null

export const getLastRequestId = (): string | null => lastRequestId

export const extractRequestId = (error: unknown): string | undefined => {
  if (axios.isAxiosError(error)) {
    const headerId = error.response?.headers?.['x-request-id']
    if (typeof headerId === 'string' && headerId !== '') {
      return headerId
    }
    const bodyId = (error.response?.data as { request_id?: string } | undefined)?.request_id
    if (typeof bodyId === 'string' && bodyId !== '') {
      return bodyId
    }
  }
  return lastRequestId ?? undefined
}

// Перехват ответов: отслеживание X-Request-ID и обработка сброса сессии при 401
api.interceptors.response.use(
  (response) => {
    const reqId = response.headers?.['x-request-id']
    if (typeof reqId === 'string' && reqId !== '') {
      lastRequestId = reqId
    }
    return response
  },
  (error: unknown) => {
    if (axios.isAxiosError(error)) {
      const headerId = error.response?.headers?.['x-request-id']
      if (typeof headerId === 'string' && headerId !== '') {
        lastRequestId = headerId
      }

      if (error.response?.status === 401) {
        localStorage.removeItem('auth_token')
        localStorage.removeItem('auth_user')
        if (window.location.pathname !== '/login') {
          window.location.href = '/login'
        }
      }
    }
    return Promise.reject(error)
  }
)

export interface HealthCheckResponse {
  status: string
  service: string
  timestamp: string
}

export const checkHealth = async (): Promise<HealthCheckResponse> => {
  const response = await api.get<HealthCheckResponse>('/health')
  return response.data
}

// API аутентификации
export const loginApi = async (email: string, password: string): Promise<LoginResponse> => {
  const response = await api.post<LoginResponse>('/auth/login', { email, password })
  return response.data
}

export const logoutApi = async (): Promise<void> => {
  await api.post('/auth/logout')
}

export const getProfileApi = async (): Promise<{ user: User }> => {
  const response = await api.get<{ user: User }>('/auth/user')
  return response.data
}

// API организаций
export const getOrganizationsApi = async (): Promise<{ data: Organization[] }> => {
  const response = await api.get<{ data: Organization[] }>('/organizations')
  return response.data
}

export const connectOrganizationApi = async (url: string): Promise<{ data: Organization; message: string }> => {
  const response = await api.post<{ data: Organization; message: string }>('/organizations', { url })
  return response.data
}

export const getOrganizationApi = async (id: number): Promise<{ data: Organization }> => {
  const response = await api.get<{ data: Organization }>(`/organizations/${id}`)
  return response.data
}

export const getOrganizationStatusApi = async (id: number): Promise<{ data: OrganizationStatus }> => {
  const response = await api.get<{ data: OrganizationStatus }>(`/organizations/${id}/status`)
  return response.data
}

export const syncOrganizationApi = async (id: number, syncNow = false): Promise<{ data: Organization; message: string }> => {
  const response = await api.post<{ data: Organization; message: string }>(`/organizations/${id}/sync`, { sync_now: syncNow })
  return response.data
}

export const getOrganizationReviewsApi = async (
  id: number,
  page = 1,
  rating?: number,
  sort?: string,
  search?: string
): Promise<PaginatedReviewsResponse> => {
  const params: Record<string, string | number> = { page }
  if (rating !== undefined && rating > 0) {
    params.rating = rating
  }
  if (sort) {
    params.sort = sort
  }
  if (search && search.trim() !== '') {
    params.search = search.trim()
  }
  const response = await api.get<PaginatedReviewsResponse>(`/organizations/${id}/reviews`, { params })
  return response.data
}

export const getOrganizationSnapshotsApi = async (id: number): Promise<{ data: OrganizationSnapshot[] }> => {
  const response = await api.get<{ data: OrganizationSnapshot[] }>(`/organizations/${id}/snapshots`)
  return response.data
}

export const deleteOrganizationApi = async (id: number): Promise<{ message: string }> => {
  const response = await api.delete<{ message: string }>(`/organizations/${id}`)
  return response.data
}

export const extractRetryAfterSeconds = (error: unknown, defaultSeconds = 60): number => {
  if (axios.isAxiosError(error)) {
    const retryHeader = error.response?.headers?.['retry-after']
    if (retryHeader) {
      const parsed = parseInt(String(retryHeader), 10)
      if (!Number.isNaN(parsed) && parsed > 0) {
        return parsed
      }
    }
  }
  return defaultSeconds
}

export const exportOrganizationReviewsApi = async (
  id: number,
  rating?: number,
  search?: string,
  format: 'csv' | 'json' = 'csv'
): Promise<Blob> => {
  const params: Record<string, string | number> = { format }
  if (rating !== undefined && rating > 0) {
    params.rating = rating
  }
  if (search && search.trim() !== '') {
    params.search = search.trim()
  }
  const response = await api.get(`/organizations/${id}/export`, {
    params,
    responseType: 'blob',
  })
  return response.data as Blob
}

// Административный API
export const getAdminSettingsApi = async (): Promise<SystemSettingsData> => {
  const response = await api.get<SystemSettingsData>('/admin/settings')
  return response.data
}

export const getAdminProxiesApi = async (): Promise<{ data: ProxyServerItem[] }> => {
  const response = await api.get<{ data: ProxyServerItem[] }>('/admin/proxies')
  return response.data
}

export const addProxiesApi = async (proxies: string[]): Promise<{ message: string; count: number; proxies: ProxyServerItem[] }> => {
  const response = await api.post<{ message: string; count: number; proxies: ProxyServerItem[] }>('/admin/proxies', { proxies })
  return response.data
}

export const toggleProxyApi = async (id: number): Promise<{ data: ProxyServerItem }> => {
  const response = await api.post<{ data: ProxyServerItem }>(`/admin/proxies/${id}/toggle`)
  return response.data
}

export const deleteProxyApi = async (id: number): Promise<{ message: string }> => {
  const response = await api.delete<{ message: string }>(`/admin/proxies/${id}`)
  return response.data
}

