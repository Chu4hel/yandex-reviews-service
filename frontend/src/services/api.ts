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

// Request interceptor to attach JWT/Sanctum Bearer token
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Response interceptor to handle unauthenticated requests
api.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_user')
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
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

// Authentication API
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

// Organizations API
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
  sort?: string
): Promise<PaginatedReviewsResponse> => {
  const params: Record<string, string | number> = { page }
  if (rating !== undefined && rating > 0) {
    params.rating = rating
  }
  if (sort) {
    params.sort = sort
  }
  const response = await api.get<PaginatedReviewsResponse>(`/organizations/${id}/reviews`, { params })
  return response.data
}

export const getOrganizationSnapshotsApi = async (id: number): Promise<{ data: OrganizationSnapshot[] }> => {
  const response = await api.get<{ data: OrganizationSnapshot[] }>(`/organizations/${id}/snapshots`)
  return response.data
}

export const exportOrganizationReviewsApi = async (id: number, rating?: number): Promise<Blob> => {
  const params: Record<string, string | number> = {}
  if (rating !== undefined && rating > 0) {
    params.rating = rating
  }
  const response = await api.get(`/organizations/${id}/export`, {
    params,
    responseType: 'blob',
  })
  return response.data as Blob
}


// Admin API
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

