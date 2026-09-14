<script setup lang="ts">
import { computed, ref } from 'vue'
import type { OrganizationSnapshot } from '@/types/snapshot'

interface Props {
  snapshots: OrganizationSnapshot[]
}

const props = defineProps<Props>()

const hoveredPointIndex = ref<number | null>(null)

// Хронологическая сортировка снимков
const sortedSnapshots = computed(() => {
  return [...props.snapshots].sort(
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

// Расчет координат точек SVG-графика
const points = computed(() => {
  const list = sortedSnapshots.value
  if (list.length === 0) return []

  // Если доступен только один снимок — позиционируем по центру
  if (list.length === 1) {
    const s = list[0]
    const rating = s?.rating_after ?? 5
    return [{
      x: width / 2,
      y: height / 2,
      rating,
      date: s?.snapshot_at ?? '',
      newReviews: s?.new_reviews_added ?? 0,
      totalReviews: s?.reviews_count_after ?? 0,
    }]
  }

  // Расчет динамического диапазона шкалы с отступами
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
      rating,
      date: s.snapshot_at,
      newReviews: s.new_reviews_added,
      totalReviews: s.reviews_count_after ?? 0,
    }
  })
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
    <!-- Top Summary Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <!-- Delta Rating -->
      <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-850 flex items-center justify-between">
        <div>
          <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Тренд рейтинга</span>
          <div class="flex items-baseline gap-2 mt-1">
            <span class="text-2xl font-extrabold text-slate-900 dark:text-white">
              {{ currentRating !== null ? currentRating.toFixed(1) : '—' }}★
            </span>
            <span
              v-if="ratingDelta !== 0"
              class="text-xs font-bold px-1.5 py-0.5 rounded"
              :class="ratingDelta > 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/70 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-950/70 dark:text-rose-300'"
            >
              {{ ratingDelta > 0 ? `+${ratingDelta}` : ratingDelta }}
            </span>
            <span v-else class="text-xs text-slate-400 font-medium">без изменений</span>
          </div>
        </div>
        <div class="text-right text-xs text-slate-400">
          было: <span class="font-semibold text-slate-600 dark:text-slate-300">{{ initialRating !== null ? initialRating.toFixed(1) : '—' }}</span>
        </div>
      </div>

      <!-- New Reviews Added -->
      <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-850 flex items-center justify-between">
        <div>
          <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Новых отзывов</span>
          <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">
            +{{ totalNewReviews.toLocaleString('ru-RU') }}
          </div>
        </div>
        <span class="px-2 py-1 text-xs font-bold rounded-lg bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-400">
          Прирост
        </span>
      </div>

      <!-- Total Snapshots -->
      <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-850 flex items-center justify-between">
        <div>
          <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Снимков в истории</span>
          <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">
            {{ sortedSnapshots.length }}
          </div>
        </div>
        <span class="text-xs text-slate-400 font-medium">
          Синхронизаций
        </span>
      </div>
    </div>

    <!-- Interactive SVG Chart -->
    <div v-if="points.length > 0" class="p-5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xs relative">
      <div class="flex items-center justify-between mb-2">
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
          Динамика рейтинга организации во времени
        </h4>
        <span class="text-[11px] text-slate-400">
          Наведите на точку для деталей
        </span>
      </div>

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
            fill="url(#ratingGradient)"
          />

          <!-- Trend Line -->
          <path
            v-if="linePath"
            :d="linePath"
            fill="none"
            stroke="#ef4444"
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
              :class="hoveredPointIndex === index ? 'fill-red-600 stroke-white dark:stroke-slate-900 stroke-2' : 'fill-white dark:fill-slate-800 stroke-red-500 stroke-2'"
              @mouseenter="hoveredPointIndex = index"
              @mouseleave="hoveredPointIndex = null"
            />
          </g>
        </svg>
      </div>

      <!-- Hover Tooltip -->
      <div
        v-if="hoveredPointIndex !== null && points[hoveredPointIndex]"
        class="mt-2 p-2.5 rounded-xl bg-slate-900 text-white text-xs flex items-center justify-between gap-4 shadow-lg animate-fade-in"
      >
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-red-500"></span>
          <span class="font-bold text-amber-400">{{ points[hoveredPointIndex]?.rating.toFixed(1) }}★</span>
          <span class="text-slate-400">({{ formatDate(points[hoveredPointIndex]?.date ?? '') }})</span>
        </div>
        <div class="text-[11px] text-slate-300">
          Прирост отзывов: <span class="font-bold text-white">+{{ points[hoveredPointIndex]?.newReviews }}</span>
          &bull; Всего в базе: <span class="font-bold text-white">{{ points[hoveredPointIndex]?.totalReviews }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
