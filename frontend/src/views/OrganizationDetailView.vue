<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import {
  getOrganizationApi,
  getOrganizationReviewsApi,
  getOrganizationSnapshotsApi,
  syncOrganizationApi,
  getOrganizationStatusApi,
  exportOrganizationReviewsApi,
  deleteOrganizationApi,
  extractRequestId,
  extractRetryAfterSeconds,
} from '@/services/api'
import { useNotificationStore } from '@/stores/notification'
import { useOrganizationsStore } from '@/stores/organizations'
import type { Organization } from '@/types/organization'
import type { Review, PaginationMeta } from '@/types/review'
import type { OrganizationSnapshot } from '@/types/snapshot'
import RatingStars from '@/components/RatingStars.vue'
import ReviewCard from '@/components/ReviewCard.vue'
import ReviewSkeleton from '@/components/ReviewSkeleton.vue'
import Pagination from '@/components/Pagination.vue'
import ReputationTrendChart from '@/components/ReputationTrendChart.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'

const route = useRoute()
const router = useRouter()
const notificationStore = useNotificationStore()
const orgStore = useOrganizationsStore()
const orgId = Number(route.params.id)

const organization = ref<Organization | null>(null)
const isDemoOrg = computed<boolean>(() => {
  if (!organization.value) return false
  return (
    organization.value.name.includes('Демо') ||
    organization.value.yandex_org_id === '67037665858'
  )
})
const isDeleteModalOpen = ref<boolean>(false)
const isDeleting = ref<boolean>(false)
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
const searchQuery = ref<string>('')
let searchDebounceTimer: number | null = null

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
    const res = await getOrganizationReviewsApi(
      orgId,
      page,
      selectedRating.value,
      selectedSort.value,
      searchQuery.value
    )
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
    // Не блокируем основной рендеринг при сбое загрузки снимков
  }
}

const handlePageChange = (page: number): void => {
  void loadReviews(page)
  window.scrollTo({ top: 350, behavior: 'smooth' })
}

const handleFilterChange = (): void => {
  void loadReviews(1)
}

const handleSearchInput = (): void => {
  if (searchDebounceTimer) {
    window.clearTimeout(searchDebounceTimer)
  }
  searchDebounceTimer = window.setTimeout(() => {
    if (searchQuery.value.trim() && selectedSort.value === 'date_desc') {
      selectedSort.value = 'relevance'
    } else if (!searchQuery.value.trim() && selectedSort.value === 'relevance') {
      selectedSort.value = 'date_desc'
    }
    void loadReviews(1)
  }, 300)
}

const clearSearch = (): void => {
  searchQuery.value = ''
  if (selectedSort.value === 'relevance') {
    selectedSort.value = 'date_desc'
  }
  void loadReviews(1)
}

const isExporting = ref<boolean>(false)
const exportFormat = ref<'csv' | 'json'>('csv')

const handleExport = async (format: 'csv' | 'json' = 'csv'): Promise<void> => {
  exportFormat.value = format
  isExporting.value = true
  try {
    const blob = await exportOrganizationReviewsApi(orgId, selectedRating.value, searchQuery.value, format)
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    const suffixParts: string[] = []
    if (selectedRating.value > 0) suffixParts.push(`${selectedRating.value}stars`)
    if (searchQuery.value) suffixParts.push('filtered')
    const suffix = suffixParts.length > 0 ? `_${suffixParts.join('_')}` : ''
    const ext = format === 'json' ? 'json' : 'csv'
    a.download = `reviews_${organization.value?.name || 'organization'}${suffix}.${ext}`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    window.URL.revokeObjectURL(url)
    notificationStore.success(
      'Экспорт завершен',
      `${format.toUpperCase()}-файл с отзывами успешно сохранен`
    )
  } catch (err: unknown) {
    const msg = err instanceof Error ? err.message : `Не удалось экспортировать отзывы в ${format.toUpperCase()}`
    notificationStore.error('Ошибка экспорта', msg, extractRequestId(err))
  } finally {
    isExporting.value = false
  }
}

const triggerSync = async (syncNow = false): Promise<void> => {
  isSyncing.value = true
  try {
    const res = await syncOrganizationApi(orgId, syncNow)
    organization.value = res.data
    notificationStore.success('Синхронизация запущена', 'Сбор отзывов выполняется в фоновом режиме')
    startPolling()
  } catch (err: unknown) {
    if (axios.isAxiosError(err) && err.response?.status === 429) {
      const waitSec = extractRetryAfterSeconds(err, 60)
      orgStore.setSyncCooldown(orgId, waitSec)
      notificationStore.warning(
        'Лимит частоты запросов',
        `Слишком много запросов к сервису. Повторная попытка станет доступна через ${waitSec} сек.`,
        extractRequestId(err)
      )
      return
    }
    const msg = err instanceof Error ? err.message : 'Ошибка запуска синхронизации'
    notificationStore.error('Ошибка синхронизации', msg, extractRequestId(err))
  } finally {
    isSyncing.value = false
  }
}

const handleDeleteConfirm = async (): Promise<void> => {
  if (!organization.value) return
  isDeleting.value = true
  try {
    await deleteOrganizationApi(orgId)
    notificationStore.success('Организация удалена', `Карточка «${organization.value.name}» успешно перемещена в архив`)
    void router.push('/organizations')
  } catch (err: unknown) {
    const msg = err instanceof Error ? err.message : 'Не удалось удалить организацию'
    notificationStore.error('Ошибка удаления', msg, extractRequestId(err))
  } finally {
    isDeleting.value = false
    isDeleteModalOpen.value = false
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
        organization.value.sync_message = res.data.sync_message
        organization.value.db_reviews_count = res.data.db_reviews_count
        organization.value.last_synced_at = res.data.last_synced_at
        organization.value.last_sync_error = res.data.last_sync_error
        organization.value.rating = res.data.rating
        organization.value.ratings_count = res.data.ratings_count
        organization.value.reviews_count = res.data.reviews_count
        if (typeof res.data.db_reviews_count === 'number') {
          meta.value.total = res.data.db_reviews_count
        }
      }

      if (res.data.sync_status === 'completed' || res.data.sync_status === 'failed') {
        stopPolling()
        void loadReviews(meta.value.current_page)
        void loadSnapshots()
      }
    } catch {
      stopPolling()
    }
  }, 1200)
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
          <span>Яндекс.Карты</span>
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
          </svg>
        </a>

        <button
          type="button"
          @click="isDeleteModalOpen = true"
          title="Переместить организацию в архив"
          class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:border-rose-300 dark:hover:border-rose-800 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-medium text-slate-600 hover:text-rose-600 dark:text-slate-300 dark:hover:text-rose-400 transition flex items-center gap-1 cursor-pointer"
        >
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
          <span class="hidden sm:inline">Удалить</span>
        </button>

        <button
          @click="triggerSync(false)"
          :disabled="isSyncing || organization?.sync_status === 'syncing' || (orgStore.syncCooldowns[orgId] ?? 0) > 0"
          :title="(orgStore.syncCooldowns[orgId] ?? 0) > 0 ? `Подождите ${orgStore.syncCooldowns[orgId]} с...` : 'Обновить отзывы'"
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
          <span v-if="(orgStore.syncCooldowns[orgId] ?? 0) > 0">
            Подождите {{ orgStore.syncCooldowns[orgId] }} с
          </span>
          <span v-else>
            {{ organization?.sync_status === 'syncing' ? 'Синхронизация...' : 'Обновить отзывы' }}
          </span>
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
      <!-- Demo Organization Notice Banner -->
      <div
        v-if="isDemoOrg"
        class="p-4 rounded-2xl bg-purple-50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800/80 shadow-xs flex items-start gap-3.5 text-xs text-purple-900 dark:text-purple-200 mb-6"
      >
        <span class="text-2xl shrink-0">🎯</span>
        <div class="space-y-1">
          <div class="flex items-center gap-2 flex-wrap">
            <h3 class="text-sm font-bold text-purple-950 dark:text-purple-100">
              Демонстрационная тестовая организация (сидер базы данных)
            </h3>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-200/80 dark:bg-purple-800 text-purple-900 dark:text-purple-100">
              Тестовый стенд
            </span>
          </div>
          <p class="leading-relaxed text-slate-600 dark:text-slate-300">
            Эта карточка создана автоматически сидером с предзагруженными 55 отзывами, чтобы можно было сразу оценить пагинацию по 50 отзывов на страницу, фильтры по всем оценкам (1–5★), полнотекстовый поиск и ответы бизнеса без ожидания скрейпинга.
          </p>
          <p class="text-purple-700 dark:text-purple-300 font-medium pt-0.5">
            💡 Нажмите кнопку <strong>«Обновить отзывы»</strong> выше, чтобы запросить реальные данные с Яндекс.Карт, либо подключите любую другую организацию по ссылке на главной странице.
          </p>
        </div>
      </div>

      <!-- Organization Header Card -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 sm:p-8 border border-slate-200/80 dark:border-slate-700 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-100 dark:border-slate-700">
          <div>
            <div class="flex items-center gap-2 mb-1 flex-wrap">
              <span class="text-xs px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 font-mono text-slate-600 dark:text-slate-300">
                ID: {{ organization.yandex_org_id }}
              </span>
              <span
                v-if="isDemoOrg"
                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300"
                title="Организация создана сидером базы данных для демонстрации возможностей"
              >
                🎯 Демо-стенд
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
            <span class="flex items-center gap-2">
              <svg class="animate-spin w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>{{ organization.sync_message || 'Выполняется фоновый сбор отзывов с Яндекс.Карт...' }}</span>
            </span>
            <span class="font-mono font-bold">{{ organization.sync_progress }}%</span>
          </div>
          <div class="w-full bg-amber-200 dark:bg-amber-900/60 h-2.5 rounded-full overflow-hidden">
            <div
              class="bg-gradient-to-r from-amber-500 to-red-600 h-2.5 rounded-full transition-all duration-300"
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
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Отзывов на Яндекс.Картах</span>
              <span
                class="text-[11px] px-2 py-0.5 rounded-full bg-slate-200/80 dark:bg-slate-600 font-medium text-slate-700 dark:text-slate-200"
                title="Количество отзывов, сохраненных в базу сервиса"
              >
                В базе: {{ meta.total.toLocaleString('ru-RU') }}
              </span>
            </div>
            <div class="mt-2">
              <span class="text-3xl font-extrabold text-slate-900 dark:text-white tabular-nums">
                {{ organization.reviews_count.toLocaleString('ru-RU') }}
              </span>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Всего отзывов на карточке Яндекс.Карт
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
          💬 Отзывы в базе ({{ meta.total.toLocaleString('ru-RU') }})
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
        <!-- Discrepancy Info Banner -->
        <div
          v-if="organization.reviews_count > meta.total && organization.sync_status !== 'syncing'"
          class="p-3.5 rounded-xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200/80 dark:border-blue-800/60 text-xs text-blue-900 dark:text-blue-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
        >
          <div class="flex items-start sm:items-center gap-2.5">
            <span class="text-base shrink-0">ℹ️</span>
            <span>
              На Яндекс.Картах зафиксировано <strong>{{ organization.reviews_count.toLocaleString('ru-RU') }}</strong> отзывов.
              В базу сервиса сейчас загружено <strong>{{ meta.total.toLocaleString('ru-RU') }}</strong>.
            </span>
          </div>
          <button
            type="button"
            @click="triggerSync(false)"
            :disabled="isSyncing || (orgStore.syncCooldowns[orgId] ?? 0) > 0"
            class="shrink-0 self-start sm:self-auto px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium transition disabled:opacity-50 cursor-pointer flex items-center gap-1"
          >
            <span>🔄 Синхронизировать</span>
          </button>
        </div>

        <!-- Controls: Filters, Search & Sort -->
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

          <!-- Search Input -->
          <div class="relative flex-1 min-w-[200px] max-w-sm">
            <input
              v-model="searchQuery"
              @input="handleSearchInput"
              type="text"
              placeholder="Поиск по отзывам и авторам..."
              class="w-full pl-8 pr-8 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-hidden focus:ring-1 focus:ring-red-500 focus:border-red-500 transition"
            />
            <svg
              class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <button
              v-if="searchQuery"
              @click="clearSearch"
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs cursor-pointer p-0.5"
              title="Очистить поиск"
            >
              ✕
            </button>
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
                <option v-if="searchQuery" value="relevance">По релевантности</option>
                <option value="date_desc">Сначала новые</option>
                <option value="date_asc">Сначала старые</option>
                <option value="rating_desc">Сначала с высокой оценкой</option>
                <option value="rating_asc">Сначала с низкой оценкой</option>
              </select>
            </div>

            <!-- Мультиформатный экспорт (CSV / JSON) -->
            <div class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 p-0.5 shadow-xs">
              <button
                type="button"
                @click="handleExport('csv')"
                :disabled="isExporting || meta.total === 0"
                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-600 transition disabled:opacity-50 cursor-pointer"
                title="Экспорт отзывов в файл Excel / CSV"
              >
                <svg class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>{{ isExporting && exportFormat === 'csv' ? 'Экспорт...' : 'CSV' }}</span>
              </button>
              <span class="text-slate-300 dark:text-slate-600">|</span>
              <button
                type="button"
                @click="handleExport('json')"
                :disabled="isExporting || meta.total === 0"
                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-600 transition disabled:opacity-50 cursor-pointer"
                title="Экспорт отзывов в формате JSON"
              >
                <span>{{ isExporting && exportFormat === 'json' ? 'Экспорт...' : 'JSON' }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Reviews Skeleton / List -->
        <div v-if="isReviewsLoading" class="space-y-4">
          <ReviewSkeleton v-for="i in 5" :key="i" />
        </div>

        <div v-else-if="reviews.length === 0" class="bg-white dark:bg-slate-800 rounded-xl p-12 text-center border border-slate-200 dark:border-slate-700">
          <p class="text-slate-500 text-sm">
            {{ searchQuery ? 'По вашему поисковому запросу отзывы не найдены.' : 'Отзывы не найдены по заданным фильтрам.' }}
          </p>
          <button
            v-if="searchQuery || selectedRating > 0"
            type="button"
            @click="searchQuery = ''; selectedRating = 0; handleFilterChange()"
            class="mt-3 inline-flex items-center gap-1 text-xs text-red-600 dark:text-red-400 hover:underline font-medium cursor-pointer"
          >
            Сбросить фильтры
          </button>
        </div>

        <div v-else class="space-y-4">
          <ReviewCard v-for="rev in reviews" :key="rev.id" :review="rev" :search-term="searchQuery" />

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

    <!-- Модальное окно подтверждения удаления карточки -->
    <ConfirmModal
      :is-open="isDeleteModalOpen"
      :title="`Удалить карточку «${organization?.name || ''}»?`"
      message="Организация будет перемещена в архив. Сбор отзывов прекратится, карточка исчезнет из списка активных."
      confirm-text="Удалить"
      cancel-text="Отмена"
      :danger="true"
      :is-loading="isDeleting"
      @confirm="handleDeleteConfirm"
      @cancel="isDeleteModalOpen = false"
    />
  </div>
</template>
