<script setup lang="ts">
import { computed, ref } from 'vue'
import type { OrganizationSnapshot } from '@/types/snapshot'

interface Props {
  snapshots: OrganizationSnapshot[]
}

const props = defineProps<Props>()

type MetricMode = 'rating' | 'reviews'
const selectedMetric = ref<MetricMode>('rating')
const isDemoActive = ref<boolean>(false)
const hoveredPointIndex = ref<number | null>(null)

// Готовый демонстрационный срез истории за 60 дней для наглядной презентации графика
const DEMO_SNAPSHOTS: OrganizationSnapshot[] = [
  {
    id: 991,
    organization_id: 0,
    rating_before: 4.68,
    rating_after: 4.72,
    ratings_count_before: 2120,
    ratings_count_after: 2200,
    reviews_count_before: 1590,
    reviews_count_after: 1650,
    new_reviews_added: 60,
    updated_reviews_count: 5,
    snapshot_at: '2026-07-15T10:00:00Z',
    created_at: '2026-07-15T10:00:00Z',
  },
  {
    id: 992,
    organization_id: 0,
    rating_before: 4.72,
    rating_after: 4.75,
    ratings_count_before: 2200,
    ratings_count_after: 2310,
    reviews_count_before: 1650,
    reviews_count_after: 1715,
    new_reviews_added: 65,
    updated_reviews_count: 8,
    snapshot_at: '2026-07-25T10:00:00Z',
    created_at: '2026-07-25T10:00:00Z',
  },
  {
    id: 993,
    organization_id: 0,
    rating_before: 4.75,
    rating_after: 4.78,
    ratings_count_before: 2310,
    ratings_count_after: 2420,
    reviews_count_before: 1715,
    reviews_count_after: 1780,
    new_reviews_added: 65,
    updated_reviews_count: 10,
    snapshot_at: '2026-08-05T10:00:00Z',
    created_at: '2026-08-05T10:00:00Z',
  },
  {
    id: 994,
    organization_id: 0,
    rating_before: 4.78,
    rating_after: 4.82,
    ratings_count_before: 2420,
    ratings_count_after: 2510,
    reviews_count_before: 1780,
    reviews_count_after: 1835,
    new_reviews_added: 55,
    updated_reviews_count: 7,
    snapshot_at: '2026-08-15T10:00:00Z',
    created_at: '2026-08-15T10:00:00Z',
  },
  {
    id: 995,
    organization_id: 0,
    rating_before: 4.82,
    rating_after: 4.85,
    ratings_count_before: 2510,
    ratings_count_after: 2600,
    reviews_count_before: 1835,
    reviews_count_after: 1890,
    new_reviews_added: 55,
    updated_reviews_count: 9,
    snapshot_at: '2026-08-25T10:00:00Z',
    created_at: '2026-08-25T10:00:00Z',
  },
  {
    id: 996,
    organization_id: 0,
    rating_before: 4.85,
    rating_after: 4.88,
    ratings_count_before: 2600,
    ratings_count_after: 2690,
    reviews_count_before: 1890,
    reviews_count_after: 1945,
    new_reviews_added: 55,
    updated_reviews_count: 12,
    snapshot_at: '2026-09-05T10:00:00Z',
    created_at: '2026-09-05T10:00:00Z',
  },
  {
    id: 997,
    organization_id: 0,
    rating_before: 4.88,
    rating_after: 4.90,
    ratings_count_before: 2690,
    ratings_count_after: 2774,
    reviews_count_before: 1945,
    reviews_count_after: 1990,
    new_reviews_added: 45,
    updated_reviews_count: 15,
    snapshot_at: '2026-09-14T10:00:00Z',
    created_at: '2026-09-14T10:00:00Z',
  },
]

// Выбор активного набора данных: реальные снимки или демо-пример
const effectiveSnapshots = computed<OrganizationSnapshot[]>(() => {
  if (isDemoActive.value) {
    return DEMO_SNAPSHOTS
  }
  return props.snapshots
})

// Хронологическая сортировка снимков
const sortedSnapshots = computed(() => {
  return [...effectiveSnapshots.value].sort(
    (a, b) => new Date(a.snapshot_at).getTime() - new Date(b.snapshot_at).getTime()
  )
})

const initialRating = computed<number | null>(() => {
  if (sortedSnapshots.value.length === 0) return null
  const first = sortedSnapshots.value[0]
  return first?.rating_before ?? first?.rating_after ?? null
})

const currentRating = computed<number | null>(() => {
  if (sortedSnapshots.value.length === 0) return null
  const last = sortedSnapshots.value[sortedSnapshots.value.length - 1]
  return last?.rating_after ?? null
})

const ratingDelta = computed<number>(() => {
  if (initialRating.value === null || currentRating.value === null) return 0
  return Number((currentRating.value - initialRating.value).toFixed(2))
})

const totalNewReviews = computed<number>(() => {
  return sortedSnapshots.value.reduce((acc, s) => acc + (s.new_reviews_added || 0), 0)
})

const width = 600
const height = 180
const padding = 35

// Расчет координат точек SVG-графика в зависимости от выбранной метрики
const points = computed(() => {
  const list = sortedSnapshots.value
  if (list.length === 0) return []

  // Если доступен только один снимок — позиционируем по центру
  if (list.length === 1) {
    const s = list[0]
    const rating = s?.rating_after ?? 5
    const reviews = s?.reviews_count_after ?? 0
    return [{
      x: width / 2,
      y: height / 2,
      value: selectedMetric.value === 'rating' ? rating : reviews,
      rating,
      date: s?.snapshot_at ?? '',
      newReviews: s?.new_reviews_added ?? 0,
      totalReviews: reviews,
    }]
  }

  if (selectedMetric.value === 'rating') {
    const ratings = list.map((s) => s.rating_after ?? 5)
    const rawMin = Math.min(...ratings)
    const rawMax = Math.max(...ratings)
    const minRating = Math.max(1, Math.floor(rawMin * 10) / 10 - 0.2)
    const maxRating = Math.min(5, Math.ceil(rawMax * 10) / 10 + 0.2)
    const range = maxRating - minRating || 1

    return list.map((s, idx) => {
      const rating = s.rating_after ?? 5
      const x = padding + (idx / (list.length - 1)) * (width - padding * 2)
      const y = height - padding - ((rating - minRating) / range) * (height - padding * 2)
      return {
        x,
        y,
        value: rating,
        rating,
        date: s.snapshot_at,
        newReviews: s.new_reviews_added,
        totalReviews: s.reviews_count_after ?? 0,
      }
    })
  } else {
    const reviewsList = list.map((s) => s.reviews_count_after ?? 0)
    const rawMin = Math.min(...reviewsList)
    const rawMax = Math.max(...reviewsList)
    const minReviews = Math.max(0, Math.floor(rawMin * 0.95))
    const maxReviews = Math.ceil(rawMax * 1.05) || 10
    const range = maxReviews - minReviews || 1

    return list.map((s, idx) => {
      const reviews = s.reviews_count_after ?? 0
      const x = padding + (idx / (list.length - 1)) * (width - padding * 2)
      const y = height - padding - ((reviews - minReviews) / range) * (height - padding * 2)
      return {
        x,
        y,
        value: reviews,
        rating: s.rating_after ?? 5,
        date: s.snapshot_at,
        newReviews: s.new_reviews_added,
        totalReviews: reviews,
      }
    })
  }
})

const linePath = computed(() => {
  if (points.value.length < 2) return ''
  return points.value.reduce((acc, p, idx) => {
    return idx === 0 ? `M ${p.x} ${p.y}` : `${acc} L ${p.x} ${p.y}`
  }, '')
})

// Замкнутый контур области под графиком для градиентной заливки
const areaPath = computed(() => {
  if (points.value.length < 2) return ''
  const first = points.value[0]
  const last = points.value[points.value.length - 1]
  const baseline = height - padding + 10
  if (!first || !last) return ''
  return `${linePath.value} L ${last.x} ${baseline} L ${first.x} ${baseline} Z`
})

const formatDate = (iso: string): string => {
  try {
    const d = new Date(iso)
    return d.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
  } catch {
    return iso
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Информационный баннер при малом числе снимков или активном демо -->
    <div
      v-if="props.snapshots.length < 2 && !isDemoActive"
      class="p-4 rounded-xl bg-indigo-50/90 dark:bg-indigo-950/30 border border-indigo-200/80 dark:border-indigo-800/60 text-xs text-indigo-950 dark:text-indigo-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
    >
      <div class="flex items-start gap-2.5">
        <span class="text-base shrink-0">📈</span>
        <div>
          <p class="font-semibold text-indigo-900 dark:text-indigo-100">
            В истории карточки пока {{ props.snapshots.length === 0 ? 'нет сохраненных снимков' : 'только 1 начальный снимок' }}.
          </p>
          <p class="text-indigo-700 dark:text-indigo-300 mt-0.5">
            Для построения динамики изменений требуется минимум 2 цикла синхронизации. Вы можете включить готовый интерактивный пример графика.
          </p>
        </div>
      </div>
      <button
        type="button"
        @click="isDemoActive = true"
        class="shrink-0 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold transition cursor-pointer flex items-center gap-1.5 shadow-xs"
      >
        <span>✨ Показать пример графика (Демо)</span>
      </button>
    </div>

    <!-- Top Summary Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <!-- Delta Rating -->
      <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm flex items-center justify-between">
        <div>
          <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Тренд рейтинга</span>
          <div class="flex items-baseline gap-2.5 mt-2">
            <span class="text-3xl font-extrabold text-slate-900 dark:text-white tabular-nums">
              {{ currentRating !== null ? currentRating.toFixed(1) : '—' }}★
            </span>
            <span
              v-if="ratingDelta !== 0"
              class="text-xs font-bold px-2 py-0.5 rounded-md"
              :class="ratingDelta > 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-400' : 'bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-400'"
            >
              {{ ratingDelta > 0 ? `+${ratingDelta}` : ratingDelta }}
            </span>
            <span v-else class="text-xs text-slate-400 font-medium">без изменений</span>
          </div>
        </div>
        <div class="text-right text-xs text-slate-500 dark:text-slate-400">
          было: <span class="font-bold text-slate-700 dark:text-slate-200">{{ initialRating !== null ? initialRating.toFixed(1) : '—' }}</span>
        </div>
      </div>

      <!-- New Reviews Added -->
      <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm flex items-center justify-between">
        <div>
          <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Прирост отзывов</span>
          <div class="text-3xl font-extrabold text-slate-900 dark:text-white tabular-nums mt-2">
            +{{ totalNewReviews.toLocaleString('ru-RU') }}
          </div>
        </div>
        <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-400">
          Прирост
        </span>
      </div>

      <!-- Total Snapshots -->
      <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm flex items-center justify-between">
        <div>
          <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Снимков в истории</span>
          <div class="text-3xl font-extrabold text-slate-900 dark:text-white tabular-nums mt-2">
            {{ sortedSnapshots.length }}
          </div>
        </div>
        <div class="text-right">
          <span v-if="isDemoActive" class="px-2.5 py-1 text-xs font-bold rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
            Демо-срез
          </span>
          <span v-else class="text-xs text-slate-500 dark:text-slate-400 font-medium">
            Синхронизаций
          </span>
        </div>
      </div>
    </div>

    <!-- Interactive SVG Chart Container -->
    <div class="p-5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xs relative">
      <!-- Toolbar: Title, Metric Switcher & Demo Toggle -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <div>
          <div class="flex items-center gap-2">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
              {{ selectedMetric === 'rating' ? 'Динамика рейтинга организации во времени' : 'Рост общего количества отзывов во времени' }}
            </h4>
            <span
              v-if="isDemoActive"
              class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
            >
              Демо-пример (60 дней)
            </span>
          </div>
          <span class="text-[11px] text-slate-400 block mt-0.5">
            Наведите курсор на точку графика для просмотра детальных показателей
          </span>
        </div>

        <div class="flex items-center gap-2">
          <!-- Metric Mode Switcher -->
          <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 p-0.5 bg-slate-100 dark:bg-slate-800">
            <button
              type="button"
              @click="selectedMetric = 'rating'"
              :class="[
                'px-2.5 py-1 text-xs font-semibold rounded-md transition cursor-pointer',
                selectedMetric === 'rating'
                  ? 'bg-white dark:bg-slate-700 text-red-600 dark:text-red-400 shadow-xs'
                  : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
              ]"
            >
              ★ Рейтинг
            </button>
            <button
              type="button"
              @click="selectedMetric = 'reviews'"
              :class="[
                'px-2.5 py-1 text-xs font-semibold rounded-md transition cursor-pointer',
                selectedMetric === 'reviews'
                  ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-xs'
                  : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
              ]"
            >
              💬 Отзывы
            </button>
          </div>

          <!-- Demo Toggle Button -->
          <button
            type="button"
            @click="isDemoActive = !isDemoActive"
            :class="[
              'px-2.5 py-1 text-xs font-semibold rounded-lg border transition cursor-pointer flex items-center gap-1',
              isDemoActive
                ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300'
                : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'
            ]"
            :title="isDemoActive ? 'Вернуться к реальным снимкам организации' : 'Показать демонстрационный пример за 60 дней'"
          >
            <span>{{ isDemoActive ? '✕ Закрыть демо' : '✨ Пример графика' }}</span>
          </button>
        </div>
      </div>

      <!-- SVG Canvas -->
      <div class="w-full overflow-hidden">
        <svg
          :viewBox="`0 0 ${width} ${height}`"
          class="w-full h-44 select-none"
        >
          <defs>
            <linearGradient id="ratingGradient" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#ef4444" stop-opacity="0.35" />
              <stop offset="100%" stop-color="#ef4444" stop-opacity="0.0" />
            </linearGradient>
            <linearGradient id="reviewsGradient" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#6366f1" stop-opacity="0.35" />
              <stop offset="100%" stop-color="#6366f1" stop-opacity="0.0" />
            </linearGradient>
          </defs>

          <!-- Grid Lines -->
          <line
            :x1="padding"
            :y1="padding"
            :x2="width - padding"
            :y2="padding"
            stroke="currentColor"
            class="text-slate-100 dark:text-slate-800"
            stroke-dasharray="3 3"
          />
          <line
            :x1="padding"
            :y1="height / 2"
            :x2="width - padding"
            :y2="height / 2"
            stroke="currentColor"
            class="text-slate-100 dark:text-slate-800"
            stroke-dasharray="3 3"
          />
          <line
            :x1="padding"
            :y1="height - padding + 10"
            :x2="width - padding"
            :y2="height - padding + 10"
            stroke="currentColor"
            class="text-slate-200 dark:text-slate-700"
          />

          <!-- Area Fill -->
          <path
            v-if="areaPath"
            :d="areaPath"
            :fill="selectedMetric === 'rating' ? 'url(#ratingGradient)' : 'url(#reviewsGradient)'"
          />

          <!-- Trend Line -->
          <path
            v-if="linePath"
            :d="linePath"
            fill="none"
            :stroke="selectedMetric === 'rating' ? '#ef4444' : '#6366f1'"
            stroke-width="3"
            stroke-linecap="round"
            stroke-linejoin="round"
          />

          <!-- Data Points -->
          <g v-for="(p, index) in points" :key="index">
            <circle
              :cx="p.x"
              :cy="p.y"
              :r="hoveredPointIndex === index ? 6 : 4"
              class="cursor-pointer transition-all duration-150"
              :class="hoveredPointIndex === index
                ? (selectedMetric === 'rating' ? 'fill-red-600 stroke-white dark:stroke-slate-900 stroke-2' : 'fill-indigo-600 stroke-white dark:stroke-slate-900 stroke-2')
                : (selectedMetric === 'rating' ? 'fill-white dark:fill-slate-800 stroke-red-500 stroke-2' : 'fill-white dark:fill-slate-800 stroke-indigo-500 stroke-2')"
              @mouseenter="hoveredPointIndex = index"
              @mouseleave="hoveredPointIndex = null"
            />
          </g>
        </svg>
      </div>

      <!-- Hover Tooltip -->
      <div
        v-if="hoveredPointIndex !== null && points[hoveredPointIndex]"
        class="mt-2 p-2.5 rounded-xl bg-slate-900 text-white text-xs flex flex-wrap items-center justify-between gap-4 shadow-lg animate-fade-in"
      >
        <div class="flex items-center gap-2">
          <span
            class="w-2.5 h-2.5 rounded-full"
            :class="selectedMetric === 'rating' ? 'bg-red-500' : 'bg-indigo-400'"
          ></span>
          <span v-if="selectedMetric === 'rating'" class="font-bold text-amber-400">
            {{ points[hoveredPointIndex]?.rating.toFixed(2) }}★
          </span>
          <span v-else class="font-bold text-indigo-300">
            {{ points[hoveredPointIndex]?.totalReviews.toLocaleString('ru-RU') }} отзывов
          </span>
          <span class="text-slate-400">({{ formatDate(points[hoveredPointIndex]?.date ?? '') }})</span>
        </div>
        <div class="text-[11px] text-slate-300">
          Прирост за снимок: <span class="font-bold text-emerald-400">+{{ points[hoveredPointIndex]?.newReviews }}</span>
          &bull; Рейтинг: <span class="font-bold text-amber-400">{{ points[hoveredPointIndex]?.rating.toFixed(1) }}★</span>
          &bull; Всего в базе: <span class="font-bold text-white">{{ points[hoveredPointIndex]?.totalReviews.toLocaleString('ru-RU') }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
