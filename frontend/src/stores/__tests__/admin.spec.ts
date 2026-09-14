import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAdminStore } from '../admin'
import * as api from '@/services/api'
import type { ProxyServerItem, SystemSettingsData } from '@/types/admin'

vi.mock('@/services/api', () => ({
  getAdminSettingsApi: vi.fn(),
  getAdminProxiesApi: vi.fn(),
  addProxiesApi: vi.fn(),
  toggleProxyApi: vi.fn(),
  deleteProxyApi: vi.fn(),
  pingProxyApi: vi.fn(),
}))

describe('admin store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  const mockSettings: SystemSettingsData = {
    environment: {
      php_version: '8.2.0',
      laravel_version: '12.0.0',
      server_time: '2026-09-13T21:00:00Z',
      queue_driver: 'database',
    },
    database_metrics: {
      organizations_count: 5,
      reviews_count: 120,
      snapshots_count: 10,
    },
    proxy_pool_metrics: {
      total_proxies: 3,
      available_for_use: 2,
      active_proxies: 2,
      cooling_down_proxies: 0,
      avg_latency_ms: 250,
    },
    queue_metrics: {
      pending_jobs: 0,
      failed_jobs: 0,
    },
  }

  const mockProxy: ProxyServerItem = {
    id: 1,
    protocol: 'http',
    host: '192.168.1.1',
    port: 8080,
    username: null,
    is_active: true,
    cooldown_until: null,
    is_cooling_down: false,
    fails_count: 0,
    success_count: 15,
    last_used_at: '2026-09-13T20:50:00Z',
    last_error: null,
    avg_response_time_ms: 320,
    masked_endpoint: 'http://192.168.1.1:8080',
    created_at: '2026-09-13T10:00:00Z',
  }

  it('fetches system settings successfully', async () => {
    vi.mocked(api.getAdminSettingsApi).mockResolvedValue(mockSettings)

    const store = useAdminStore()
    expect(store.settings).toBeNull()

    await store.fetchSettings()

    expect(store.settings).toEqual(mockSettings)
    expect(store.isLoadingSettings).toBe(false)
    expect(store.error).toBeNull()
  })

  it('fetches proxy pool list successfully', async () => {
    vi.mocked(api.getAdminProxiesApi).mockResolvedValue({ data: [mockProxy] })

    const store = useAdminStore()
    expect(store.proxies).toHaveLength(0)

    await store.fetchProxies()

    expect(store.proxies).toHaveLength(1)
    expect(store.proxies[0]?.host).toBe('192.168.1.1')
    expect(store.isLoadingProxies).toBe(false)
  })

  it('adds new proxies to pool and refreshes list', async () => {
    vi.mocked(api.addProxiesApi).mockResolvedValue({
      message: 'Успешно добавлено',
      count: 1,
      proxies: [mockProxy],
    })
    vi.mocked(api.getAdminProxiesApi).mockResolvedValue({ data: [mockProxy] })
    vi.mocked(api.getAdminSettingsApi).mockResolvedValue(mockSettings)

    const store = useAdminStore()
    const result = await store.addProxies(['192.168.1.1:8080'])

    expect(result).toBe(true)
    expect(store.actionSuccess).toBe('Успешно добавлено')
    expect(api.addProxiesApi).toHaveBeenCalledWith(['192.168.1.1:8080'])
  })

  it('toggles proxy status and updates state in store', async () => {
    const updatedProxy: ProxyServerItem = { ...mockProxy, is_active: false }
    vi.mocked(api.toggleProxyApi).mockResolvedValue({ data: updatedProxy })
    vi.mocked(api.getAdminSettingsApi).mockResolvedValue(mockSettings)

    const store = useAdminStore()
    store.proxies = [mockProxy]

    const result = await store.toggleProxy(1)

    expect(result).toBe(true)
    expect(store.proxies[0]?.is_active).toBe(false)
  })

  it('deletes proxy from pool and removes it from store', async () => {
    vi.mocked(api.deleteProxyApi).mockResolvedValue({ message: 'Удалено' })
    vi.mocked(api.getAdminSettingsApi).mockResolvedValue(mockSettings)

    const store = useAdminStore()
    store.proxies = [mockProxy]

    const result = await store.deleteProxy(1)

    expect(result).toBe(true)
    expect(store.proxies).toHaveLength(0)
  })

  it('pings proxy and updates latency and status in store', async () => {
    const updatedProxy: ProxyServerItem = { ...mockProxy, avg_response_time_ms: 120, is_active: true }
    vi.mocked(api.pingProxyApi).mockResolvedValue({
      success: true,
      is_captcha: false,
      ping_ms: 120,
      error: null,
      proxy: updatedProxy,
    })
    vi.mocked(api.getAdminSettingsApi).mockResolvedValue(mockSettings)

    const store = useAdminStore()
    store.proxies = [mockProxy]

    const result = await store.pingProxy(1)

    expect(result).toBe(true)
    expect(store.proxies[0]?.avg_response_time_ms).toBe(120)
    expect(store.actionSuccess).toContain('Пинг успешен (120 мс)')
    expect(api.pingProxyApi).toHaveBeenCalledWith(1)
  })
})
