export interface ReviewPhoto {
  id: string
  preview_url: string
  full_url: string
}

export interface Review {
  id: number
  organization_id: number
  yandex_review_id: string
  author_name: string | null
  author_avatar_url: string | null
  author_level: string | null
  rating: number
  text: string | null
  photos?: ReviewPhoto[] | null
  published_at: string | null
  business_response_text: string | null
  business_response_at: string | null
  created_at: string
  updated_at: string
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PaginatedReviewsResponse {
  data: Review[]
  meta: PaginationMeta
  organization: {
    id: number
    name: string
    rating: number | null
    ratings_count: number
    reviews_count: number
    last_synced_at: string | null
  }
}
