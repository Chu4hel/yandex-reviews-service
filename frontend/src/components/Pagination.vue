<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  currentPage: number
  lastPage: number
  total: number
  perPage: number
  isLoading?: boolean
}>()

const emit = defineEmits<{
  (e: 'page-change', page: number): void
}>()

const visiblePages = computed<number[]>(() => {
  const current = props.currentPage
  const last = props.lastPage
  const delta = 2

  const range: number[] = []
  for (let i = Math.max(1, current - delta); i <= Math.min(last, current + delta); i++) {
    range.push(i)
  }
  return range
})

const startItem = computed<number>(() => {
  if (props.total === 0) return 0
  return (props.currentPage - 1) * props.perPage + 1
})

const endItem = computed<number>(() => {
  return Math.min(props.currentPage * props.perPage, props.total)
})

const changePage = (page: number): void => {
  if (page < 1 || page > props.lastPage || page === props.currentPage || props.isLoading) {
    return
  }
  emit('page-change', page)
}
</script>

<template>
  <div class="flex flex-col sm:flex-row items-center justify-between gap-4 py-4 px-2">
    <!-- Info -->
    <div class="text-xs text-slate-500 dark:text-slate-400">
      Показаны отзывы <span class="font-semibold text-slate-800 dark:text-slate-200">{{ startItem }}–{{ endItem }}</span> из
      <span class="font-semibold text-slate-800 dark:text-slate-200">{{ total.toLocaleString('ru-RU') }}</span>
    </div>

    <!-- Controls -->
    <div class="flex items-center gap-1">
      <!-- Prev -->
      <button
        type="button"
        @click="changePage(currentPage - 1)"
        :disabled="currentPage <= 1 || isLoading"
        class="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition"
      >
        &larr; Назад
      </button>

      <!-- First Page if far -->
      <template v-if="visiblePages[0] > 1">
        <button
          type="button"
          @click="changePage(1)"
          :disabled="isLoading"
          class="w-8 h-8 text-xs font-medium rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition"
        >
          1
        </button>
        <span v-if="visiblePages[0] > 2" class="px-1 text-slate-400">...</span>
      </template>

      <!-- Page Numbers -->
      <button
        v-for="page in visiblePages"
        :key="page"
        type="button"
        @click="changePage(page)"
        :disabled="isLoading"
        :class="[
          'w-8 h-8 text-xs font-medium rounded-lg transition',
          page === currentPage
            ? 'bg-red-600 text-white font-bold shadow-xs'
            : 'border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700'
        ]"
      >
        {{ page }}
      </button>

      <!-- Last Page if far -->
      <template v-if="visiblePages[visiblePages.length - 1] < lastPage">
        <span v-if="visiblePages[visiblePages.length - 1] < lastPage - 1" class="px-1 text-slate-400">...</span>
        <button
          type="button"
          @click="changePage(lastPage)"
          :disabled="isLoading"
          class="w-8 h-8 text-xs font-medium rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition"
        >
          {{ lastPage }}
        </button>
      </template>

      <!-- Next -->
      <button
        type="button"
        @click="changePage(currentPage + 1)"
        :disabled="currentPage >= lastPage || isLoading"
        class="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition"
      >
        Вперед &rarr;
      </button>
    </div>
  </div>
</template>
