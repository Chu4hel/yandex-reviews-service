export interface ProxyServerItem {
  id: number
  protocol: string
  host: string
  port: number
  username: string | null
  is_active: boolean
  cooldown_until: string | null
  is_cooling_down: boolean
  fails_count: number
  success_count: number
  last_used_at: string | null
  last_error: string | null
  avg_response_time_ms: number | null
  masked_endpoint: string
  created_at: string | null
}

export interface SystemEnvironment {
  php_version: string
  laravel_version: string
  server_time: string
  queue_driver: string
}

export interface DatabaseMetrics {
  organizations_count: number
  reviews_count: number
  snapshots_count: number
}

export interface ProxyPoolMetrics {
  total_proxies: number
  available_for_use: number
  active_proxies: number
  cooling_down_proxies: number
  avg_latency_ms: number
}

export interface QueueMetrics {
  pending_jobs: number
  failed_jobs: number
}

export interface SystemSettingsData {
  environment: SystemEnvironment
  database_metrics: DatabaseMetrics
  proxy_pool_metrics: ProxyPoolMetrics
  queue_metrics: QueueMetrics
}
