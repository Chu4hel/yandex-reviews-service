<script setup lang="ts">
import { computed } from 'vue'
import type { Review } from '@/types/review'
import RatingStars from './RatingStars.vue'

const props = defineProps<{
  review: Review
}>()

const authorInitials = computed<string>(() => {
  const name = props.review.author_name?.trim() ?? ''
  if (!name) return '?'
  const parts = name.split(/\s+/)
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase()
  }
  return name.slice(0, 2).toUpperCase()
})

const formattedDate = computed<string>(() => {
  if (!props.review.published_at) return ''
  try {
    const d = new Date(props.review.published_at)
    return new Intl.DateTimeFormat('ru-RU', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(d)
  } catch {
    return props.review.published_at
  }
})

const formattedResponseDate = computed<string>(() => {
  if (!props.review.business_response_at) return ''
  try {
    const d = new Date(props.review.business_response_at)
    return new Intl.DateTimeFormat('ru-RU', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(d)
  } catch {
    return props.review.business_response_at
  }
})
</script>

<template>
  <article class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-slate-200/80 dark:border-slate-700 shadow-xs hover:shadow-md transition-shadow">
    <!-- Header: Author, Rating, Date -->
    <div class="flex items-start justify-between gap-4">
      <div class="flex items-center gap-3">
        <!-- Avatar -->
        <div class="w-10 h-10 rounded-full overflow-hidden shrink-0 bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm shadow-xs">
          <img
            v-if="review.author_avatar_url"
            :src="review.author_avatar_url"
            :alt="review.author_name ?? 'Аватар'"
            class="w-full h-full object-cover"
            loading="lazy"
            @error="(e) => ((e.target as HTMLElement).style.display = 'none')"
          />
          <span v-else>{{ authorInitials }}</span>
        </div>

        <div>
          <h4 class="text-sm font-semibold text-slate-900 dark:text-white leading-tight">
            {{ review.author_name || 'Пользователь Яндекс.Карт' }}
          </h4>
          <p v-if="review.author_level" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            {{ review.author_level }}
          </p>
        </div>
      </div>

      <!-- Rating and Date -->
      <div class="text-right shrink-0">
        <RatingStars :rating="review.rating" size="sm" />
        <time class="block text-xs text-slate-400 dark:text-slate-500 mt-1">
          {{ formattedDate }}
        </time>
      </div>
    </div>

    <!-- Review Text -->
    <div class="mt-4 text-sm leading-relaxed text-slate-700 dark:text-slate-300 break-words whitespace-pre-line">
      <p v-if="review.text">{{ review.text }}</p>
      <p v-else class="text-slate-400 dark:text-slate-500 italic">Пользователь поставил оценку без текстового комментария.</p>
    </div>

    <!-- Official Organization Response -->
    <div
      v-if="review.business_response_text"
      class="mt-4 p-3.5 rounded-lg bg-slate-50 dark:bg-slate-700/50 border-l-4 border-red-500 dark:border-red-400 text-xs"
    >
      <div class="flex items-center justify-between text-slate-600 dark:text-slate-300 font-semibold mb-1">
        <span class="flex items-center gap-1.5 text-red-600 dark:text-red-400">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
          </svg>
          Ответ организации
        </span>
        <time v-if="formattedResponseDate" class="text-slate-400 font-normal">
          {{ formattedResponseDate }}
        </time>
      </div>
      <p class="text-slate-700 dark:text-slate-200 mt-1 leading-normal whitespace-pre-line">
        {{ review.business_response_text }}
      </p>
    </div>
  </article>
</template>
