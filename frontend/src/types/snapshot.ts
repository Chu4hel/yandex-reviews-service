export interface OrganizationSnapshot {
  id: number
  organization_id: number
  rating_before: number | null
  rating_after: number | null
  ratings_count_before: number | null
  ratings_count_after: number | null
  reviews_count_before: number | null
  reviews_count_after: number | null
  new_reviews_added: number
  updated_reviews_count: number
  snapshot_at: string
  created_at: string
}
