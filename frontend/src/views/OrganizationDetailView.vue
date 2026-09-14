<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  getOrganizationApi,
  getOrganizationReviewsApi,
  getOrganizationSnapshotsApi,
  syncOrganizationApi,
  getOrganizationStatusApi,
  exportOrganizationReviewsApi,
} from '@/services/api'
import type { Organization } from '@/types/organization'
import type { Review, PaginationMeta } from '@/types/review'
import type { OrganizationSnapshot } from '@/types/snapshot'
import RatingStars from '@/components/RatingStars.vue'
import ReviewCard from '@/components/ReviewCard.vue'
import Pagination from '@/components/Pagination.vue'
import ReputationTrendChart from '@/components/ReputationTrendChart.vue'

const route = useRoute()
const router = useRouter()
const orgId = Number(route.params.id)

const organization = ref<Organization | null>(null)
const reviews = ref<Review[]>([])
const meta = ref<PaginationMeta>({
  current_page: 1,
  last_page: 1,
  per_page: 50,
  total: 0,
})
const snapshots = ref<OrganizationSnapshot[]>([])

const activeTab = ref<'reviews' | 'snapshots'>('reviews')
const selectedRating = ref<number>(0)
const selectedSort = ref<string>('date_desc')

const isLoading = ref<boolean>(true)
const isReviewsLoading = ref<boolean>(false)
const isSyncing = ref<boolean>(false)
const errorMessage = ref<string | null>(null)

let statusTimer: number | null = null

const loadOrganization = async (): Promise<void> => {
  isLoading.value = true
  errorMessage.value = null
  try {
    const orgRes = await getOrganizationApi(orgId)
    organization.value = orgRes.data
    await Promise.all([loadReviews(1), loadSnapshots()])

    if (organization.value.sync_status === 'syncing' || organization.value.sync_status === 'pending') {
      startPolling()
    }
  } catch (err: unknown) {
    errorMessage.value = err instanceof Error ? err.message : 'Не удалось загрузить данные организации'
  } finally {
    isLoading.value = false
  }
}

const loadReviews = async (page = 1): Promise<void> => {
  isReviewsLoading.value = true
  try {
    const res = await getOrganizationReviewsApi(orgId, page, selectedRating.value, selectedSort.value)
    reviews.value = res.data
    meta.value = res.meta
  } catch (err: unknown) {
    errorMessage.value = err instanceof Error ? err.message : 'Ошибка загрузки отзывов'
  } finally {
    isReviewsLoading.value = false
  }
}

const loadSnapshots = async (): Promise<void> => {
  try {
    const res = await getOrganizationSnapshotsApi(orgId)
    snapshots.value = res.data
  } catch {
    // Non-blocking
  }
}

const handlePageChange = (page: number): void => {
  void loadReviews(page)
  window.scrollTo({ top: 350, behavior: 'smooth' })
}

const handleFilterChange = (): void => {
  void loadReviews(1)
}

const isExporting = ref<boolean>(false)

const handleExport = async (): Promise<void> => {
  isExporting.value = true
  try {
    const blob = await exportOrganizationReviewsApi(orgId, selectedRating.value)
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    const suffix = selectedRating.value > 0 ? `_${selectedRating.value}stars` : ''
    a.download = `reviews_${organization.value?.name || 'organization'}${suffix}.csv`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    window.URL.revokeObjectURL(url)
  } catch (err: unknown) {
    alert(err instanceof Error ? err.message : 'Не удалось экспортировать отзывы в CSV')
  } finally {
    isExporting.value = false
  }
}

const triggerSync = async (syncNow = false): Promise<void> => {
  isSyncing.value = true
  try {
    const res = await syncOrganizationApi(orgId, syncNow)
    organization.value = res.data
    startPolling()
  } catch (err: unknown) {
    alert(err instanceof Error ? err.message : 'Ошибка при запуске синхронизации')
  } finally {
    isSyncing.value = false
  }
}

const startPolling = (): void => {
  if (statusTimer) return
  statusTimer = window.setInterval(async () => {
    try {
      const res = await getOrganizationStatusApi(orgId)
      if (organization.value) {
        organization.value.sync_status = res.data.sync_status
        organization.value.sync_progress = res.data.sync_progress
        organization.value.last_synced_at = res.data.last_synced_at
        organization.value.last_sync_error = res.data.last_sync_error
        organization.value.rating = res.data.rating
        organization.value.ratings_count = res.data.ratings_count
        organization.value.reviews_count = res.data.reviews_count
      }

      if (res.data.sync_status === 'completed' || res.data.sync_status === 'failed') {
        stopPolling()
        void loadReviews(meta.value.current_page)
        void loadSnapshots()
      }
    } catch {
      stopPolling()
    }
  }, 2500)
}

const stopPolling = (): void => {
  if (statusTimer) {
    clearInterval(statusTimer)
    statusTimer = null
  }
}

const formatDate = (iso: string | null): string => {
  if (!iso) return '—'
  try {
    return new Intl.DateTimeFormat('ru-RU', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(iso))
  } catch {
    return iso
  }
}

onMounted(() => {
  void loadOrganization()
})

onUnmounted(() => {
  stopPolling()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Breadcrumbs / Back Navigation -->
    <div class="flex items-center justify-between">
      <button
        @click="router.push('/organizations')"
        class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition cursor-pointer"
      >
        <span>&larr; Вернуться к списку карточек</span>
      </button>

      <div class="flex items-center gap-2">
        <a
          v-if="organization?.url"
          :href="organization.url"
          target="_blank"
          rel="noopener noreferrer"
          class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition flex items-center gap-1"
        >
          <span>Открыть на Яндекс.Картах</span>
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
          </svg>
        </a>
        <button
          @click="triggerSync(false)"
          :disabled="isSyncing || organization?.sync_status === 'syncing'"
          class="px-4 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
        >
          <svg
            v-if="organization?.sync_status === 'syncing' || isSyncing"
            class="animate-spin w-3.5 h-3.5"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
          </svg>
          <span>{{ organization?.sync_status === 'syncing' ? 'Синхронизация...' : 'Обновить отзывы' }}</span>
        </button>
      </div>
    </div>

    <!-- Error Banner -->
    <div
      v-if="errorMessage"
      class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-sm flex items-center gap-2"
    >
      <svg class="w-5 h-5 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span>{{ errorMessage }}</span>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="isLoading" class="p-12 text-center text-slate-400 text-sm">
      Загрузка карточки организации и отзывов...
    </div>

    <template v-else-if="organization">
      <!-- Organization Header Card -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 sm:p-8 border border-slate-200/80 dark:border-slate-700 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-100 dark:border-slate-700">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="text-xs px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 font-mono text-slate-600 dark:text-slate-300">
                ID: {{ organization.yandex_org_id }}
              </span>
              <span
                v-if="organization.sync_status === 'syncing'"
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
              >
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                Парсинг отзывов ({{ organization.sync_progress }}%)
              </span>
              <span
                v-else-if="organization.sync_status === 'completed'"
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300"
              >
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                Синхронизировано
              </span>
              <span
                v-else-if="organization.sync_status === 'failed'"
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300"
              >
                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                Ошибка: {{ organization.last_sync_error || 'сбой' }}
              </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white">
              {{ organization.name }}
            </h1>
            <p v-if="organization.address" class="text-sm text-slate-500 dark:text-slate-400 mt-1">
              📍 {{ organization.address }}
            </p>
          </div>

          <div class="text-xs text-slate-400 dark:text-slate-500 md:text-right">
            <span>Последнее обновление:</span>
            <div class="font-medium text-slate-700 dark:text-slate-300 mt-0.5">
              {{ formatDate(organization.last_synced_at) }}
            </div>
          </div>
        </div>

        <!-- Sync Progress Bar -->
        <div v-if="organization.sync_status === 'syncing'" class="mt-4 p-4 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800">
          <div class="flex items-center justify-between text-xs font-semibold text-amber-900 dark:text-amber-200 mb-1.5">
            <span>Выполняется фоновый парсинг карточки (до ~600 отзывов)...</span>
            <span>{{ organization.sync_progress }}%</span>
          </div>
          <div class="w-full bg-amber-200 dark:bg-amber-900/60 h-2 rounded-full overflow-hidden">
            <div
              class="bg-amber-500 h-2 rounded-full transition-all duration-300"
              :style="{ width: `${organization.sync_progress}%` }"
            ></div>
          </div>
        </div>

        <!-- Metric Cards (Rating, Ratings Count, Reviews Count) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
          <!-- Card 1: Average Rating -->
          <div class="bg-slate-50 dark:bg-slate-700/40 rounded-xl p-5 border border-slate-200/80 dark:border-slate-700 flex flex-col justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Средний рейтинг</span>
            <div class="mt-2 flex items-baseline gap-3">
              <span class="text-4xl font-extrabold text-slate-900 dark:text-white tabular-nums">
                {{ organization.rating ? organization.rating.toFixed(1) : '—' }}
              </span>
              <div class="flex flex-col">
                <RatingStars :rating="organization.rating" size="md" />
                <span class="text-[11px] text-slate-400 mt-0.5">по шкале Яндекс.Карт</span>
              </div>
            </div>
          </div>

          <!-- Card 2: Ratings Count -->
          <div class="bg-slate-50 dark:bg-slate-700/40 rounded-xl p-5 border border-slate-200/80 dark:border-slate-700 flex flex-col justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Количество оценок</span>
            <div class="mt-2">
              <span class="text-3xl font-extrabold text-slate-900 dark:text-white tabular-nums">
                {{ organization.ratings_count.toLocaleString('ru-RU') }}
              </span>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Всего поставлено оценок пользователями</p>
            </div>
          </div>

          <!-- Card 3: Reviews Count -->
          <div class="bg-slate-50 dark:bg-slate-700/40 rounded-xl p-5 border border-slate-200/80 dark:border-slate-700 flex flex-col justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Количество отзывов</span>
            <div class="mt-2">
              <span class="text-3xl font-extrabold text-slate-900 dark:text-white tabular-nums">
                {{ organization.reviews_count.toLocaleString('ru-RU') }}
              </span>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Отзывов с текстом (загружено в базу: {{ meta.total }})
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex items-center gap-4 border-b border-slate-200 dark:border-slate-700">
        <button
          type="button"
          @click="activeTab = 'reviews'"
          :class="[
            'pb-3 text-sm font-bold border-b-2 transition cursor-pointer',
            activeTab === 'reviews'
              ? 'border-red-600 text-red-600 dark:text-red-400'
              : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
          ]"
        >
          💬 Все отзывы ({{ meta.total.toLocaleString('ru-RU') }})
        </button>

        <button
          type="button"
          @click="activeTab = 'snapshots'"
          :class="[
            'pb-3 text-sm font-bold border-b-2 transition cursor-pointer',
            activeTab === 'snapshots'
              ? 'border-red-600 text-red-600 dark:text-red-400'
              : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
          ]"
        >
          📈 История изменений (Снимки: {{ snapshots.length }})
        </button>
      </div>

      <!-- TAB 1: REVIEWS -->
      <div v-if="activeTab === 'reviews'" class="space-y-4">
        <!-- Controls: Filters & Sort -->
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
          <!-- Rating Filter -->
          <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-slate-500">Оценка:</span>
            <div class="flex items-center gap-1">
              <button
                type="button"
                @click="selectedRating = 0; handleFilterChange()"
                :class="[
                  'px-2.5 py-1 rounded-lg text-xs font-medium transition cursor-pointer',
                  selectedRating === 0
                    ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                    : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200'
                ]"
              >
                Все
              </button>
              <button
                v-for="star in [5, 4, 3, 2, 1]"
                :key="star"
                type="button"
                @click="selectedRating = star; handleFilterChange()"
                :class="[
                  'px-2.5 py-1 rounded-lg text-xs font-medium transition cursor-pointer flex items-center gap-1',
                  selectedRating === star
                    ? 'bg-amber-500 text-white'
                    : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200'
                ]"
              >
                <span>{{ star }}</span>
                <span>★</span>
              </button>
            </div>
          </div>

          <!-- Right Side: Sort & Export -->
          <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
              <span class="text-xs font-semibold text-slate-500">Сортировка:</span>
              <select
                v-model="selectedSort"
                @change="handleFilterChange"
                class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-700 dark:text-slate-200 focus:outline-hidden"
              >
                <option value="date_desc">Сначала новые</option>
                <option value="date_asc">Сначала старые</option>
                <option value="rating_desc">Сначала с высокой оценкой</option>
                <option value="rating_asc">Сначала с низкой оценкой</option>
              </select>
            </div>

            <button
              type="button"
              @click="handleExport"
              :disabled="isExporting || meta.total === 0"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs font-medium text-slate-700 dark:text-slate-200 transition shadow-xs disabled:opacity-50 cursor-pointer"
              title="Экспорт отзывов в файл Excel / CSV"
            >
              <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span>{{ isExporting ? 'Экспорт...' : 'Экспорт в CSV' }}</span>
            </button>
          </div>
        </div>

        <!-- Reviews List -->
        <div v-if="isReviewsLoading" class="text-center py-12 text-slate-400 text-sm">
          Загрузка страницы с отзывами...
        </div>

        <div v-else-if="reviews.length === 0" class="bg-white dark:bg-slate-800 rounded-xl p-12 text-center border border-slate-200 dark:border-slate-700">
          <p class="text-slate-500 text-sm">Отзывы не найдены по заданным фильтрам.</p>
        </div>

        <div v-else class="space-y-4">
          <ReviewCard v-for="rev in reviews" :key="rev.id" :review="rev" />

          <!-- Pagination (50 reviews per page as required) -->
          <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
            <Pagination
              :current-page="meta.current_page"
              :last-page="meta.last_page"
              :total="meta.total"
              :per-page="meta.per_page"
              :is-loading="isReviewsLoading"
              @page-change="handlePageChange"
            />
          </div>
        </div>
      </div>

      <!-- TAB 2: SNAPSHOTS HISTORY -->
      <div v-else-if="activeTab === 'snapshots'" class="space-y-6">
        <!-- Visual Reputation Trend Chart -->
        <ReputationTrendChart v-if="snapshots.length > 0" :snapshots="snapshots" />

        <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200/80 dark:border-slate-700 shadow-sm">
          <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2">История снимков синхронизации (было → стало)</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mb-6">
            Фиксация изменений между циклами парсинга: динамика рейтинга, прирост оценок и добавление новых отзывов.
          </p>

          <div v-if="snapshots.length === 0" class="text-center py-8 text-slate-400 text-sm">
            История снимков пока пуста. При повторной синхронизации здесь отобразится динамика изменений.
          </div>

          <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="border-b border-slate-200 dark:border-slate-700 text-slate-400 uppercase font-semibold">
                  <th class="py-3 px-3">Дата снимка</th>
                  <th class="py-3 px-3">Рейтинг (было → стало)</th>
                  <th class="py-3 px-3">Оценок (было → стало)</th>
                  <th class="py-3 px-3">Отзывов (было → стало)</th>
                  <th class="py-3 px-3">Новых добавлено</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                <tr v-for="snap in snapshots" :key="snap.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                  <td class="py-3 px-3 font-medium text-slate-700 dark:text-slate-300">
                    {{ formatDate(snap.snapshot_at) }}
                  </td>
                  <td class="py-3 px-3 font-semibold">
                    <span class="text-slate-400">{{ snap.rating_before ?? '—' }}</span>
                    <span class="mx-1.5 text-slate-300">&rarr;</span>
                    <span class="text-amber-500 font-bold">{{ snap.rating_after ?? '—' }} ★</span>
                  </td>
                  <td class="py-3 px-3 font-semibold">
                    <span class="text-slate-400">{{ snap.ratings_count_before?.toLocaleString('ru-RU') ?? '—' }}</span>
                    <span class="mx-1.5 text-slate-300">&rarr;</span>
                    <span class="text-slate-900 dark:text-white font-bold">{{ snap.ratings_count_after?.toLocaleString('ru-RU') ?? '—' }}</span>
                  </td>
                  <td class="py-3 px-3 font-semibold">
                    <span class="text-slate-400">{{ snap.reviews_count_before?.toLocaleString('ru-RU') ?? '—' }}</span>
                    <span class="mx-1.5 text-slate-300">&rarr;</span>
                    <span class="text-slate-900 dark:text-white font-bold">{{ snap.reviews_count_after?.toLocaleString('ru-RU') ?? '—' }}</span>
                  </td>
                  <td class="py-3 px-3">
                    <span class="inline-flex px-2 py-0.5 rounded-full font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                      +{{ snap.new_reviews_added }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
