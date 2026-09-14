export type SyncStatus = 'idle' | 'pending' | 'syncing' | 'completed' | 'failed'

export interface Organization {
  id: number
  yandex_org_id: string
  name: string
  url: string
  address: string | null
  rating: number | null
  ratings_count: number
  reviews_count: number
  sync_status: SyncStatus
  sync_progress: number
  sync_message?: string | null
  db_reviews_count?: number
  last_synced_at: string | null
  last_sync_error: string | null
  created_at: string
  updated_at: string
}

export interface OrganizationStatus {
  id: number
  sync_status: SyncStatus
  sync_progress: number
  sync_message?: string | null
  db_reviews_count?: number
  last_synced_at: string | null
  last_sync_error: string | null
  rating: number | null
  ratings_count: number
  reviews_count: number
}

