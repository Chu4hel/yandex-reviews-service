<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    rating: number | null
    max?: number
    size?: 'sm' | 'md' | 'lg'
    showNumber?: boolean
  }>(),
  {
    rating: 0,
    max: 5,
    size: 'md',
    showNumber: false,
  }
)

const safeRating = computed(() => props.rating ?? 0)

const starClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'w-3.5 h-3.5'
    case 'lg':
      return 'w-6 h-6'
    default:
      return 'w-4 h-4'
  }
})

const textClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'text-xs'
    case 'lg':
      return 'text-2xl font-bold'
    default:
      return 'text-sm font-semibold'
  }
})
</script>

<template>
  <div class="inline-flex items-center gap-1.5">
    <div class="flex items-center text-amber-400">
      <template v-for="star in max" :key="star">
        <svg
          :class="[
            starClasses,
            star <= Math.round(safeRating) ? 'text-amber-400 fill-amber-400' : 'text-slate-300 dark:text-slate-600 fill-transparent'
          ]"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
        </svg>
      </template>
    </div>
    <span v-if="showNumber" :class="[textClasses, 'text-slate-800 dark:text-slate-100 tabular-nums']">
      {{ safeRating > 0 ? safeRating.toFixed(1) : '—' }}
    </span>
  </div>
</template>
