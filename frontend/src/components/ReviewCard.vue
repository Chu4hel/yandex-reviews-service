<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Review } from '@/types/review'
import RatingStars from './RatingStars.vue'
import PhotoLightboxModal from './PhotoLightboxModal.vue'

interface Props {
  review: Review
  searchTerm?: string
}

const props = withDefaults(defineProps<Props>(), {
  searchTerm: '',
})

const isLightboxOpen = ref<boolean>(false)
const selectedPhotoIndex = ref<number>(0)

const openLightbox = (index: number): void => {
  selectedPhotoIndex.value = index
  isLightboxOpen.value = true
}

interface TextPart {
  text: string
  isMatch: boolean
}

const getHighlightedParts = (text: string | null | undefined): TextPart[] => {
  if (!text) return []
  const term = props.searchTerm?.trim()
  if (!term) {
    return [{ text, isMatch: false }]
  }

  // Извлекаем слова длиной от 1 символа
  const rawWords = term.split(/[\s,.+-]+/).filter((w) => w.length >= 1)
  if (rawWords.length === 0) {
    return [{ text, isMatch: false }]
  }

  // Убираем дубликаты и сортируем по убыванию длины
  const uniqueWords = Array.from(new Set(rawWords)).sort((a, b) => b.length - a.length)
  const escapedWords = uniqueWords.map((w) => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
  const regex = new RegExp(`(${escapedWords.join('|')})`, 'gi')

  const parts: TextPart[] = []
  let lastIndex = 0
  let match: RegExpExecArray | null

  while ((match = regex.exec(text)) !== null) {
    if (match.index > lastIndex) {
      parts.push({
        text: text.slice(lastIndex, match.index),
        isMatch: false,
      })
    }
    parts.push({
      text: match[0],
      isMatch: true,
    })
    lastIndex = regex.lastIndex
  }

  if (lastIndex < text.length) {
    parts.push({
      text: text.slice(lastIndex),
      isMatch: false,
    })
  }

  return parts
}

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

const TEXT_COLLAPSE_THRESHOLD = 320
const isTextExpanded = ref<boolean>(false)
const isResponseExpanded = ref<boolean>(false)

const isTextLong = computed<boolean>(() => {
  return (props.review.text?.length ?? 0) > TEXT_COLLAPSE_THRESHOLD
})

const displayedReviewText = computed<string>(() => {
  const full = props.review.text ?? ''
  if (!isTextLong.value || isTextExpanded.value) {
    return full
  }
  const slice = full.slice(0, TEXT_COLLAPSE_THRESHOLD)
  const lastSpace = slice.lastIndexOf(' ')
  const cleanSlice = lastSpace > 200 ? slice.slice(0, lastSpace) : slice
  return cleanSlice.trimEnd() + '...'
})

const isResponseLong = computed<boolean>(() => {
  return (props.review.business_response_text?.length ?? 0) > TEXT_COLLAPSE_THRESHOLD
})

const displayedResponseText = computed<string>(() => {
  const full = props.review.business_response_text ?? ''
  if (!isResponseLong.value || isResponseExpanded.value) {
    return full
  }
  const slice = full.slice(0, TEXT_COLLAPSE_THRESHOLD)
  const lastSpace = slice.lastIndexOf(' ')
  const cleanSlice = lastSpace > 200 ? slice.slice(0, lastSpace) : slice
  return cleanSlice.trimEnd() + '...'
})

const hasAvatarLoadError = ref<boolean>(false)
</script>

<template>
  <article class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-slate-200/80 dark:border-slate-700 shadow-xs hover:shadow-md transition-shadow">
    <!-- Header: Author, Rating, Date -->
    <div class="flex items-start justify-between gap-4">
      <div class="flex items-center gap-3">
        <!-- Avatar -->
        <div class="w-10 h-10 rounded-full overflow-hidden shrink-0 bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm shadow-xs">
          <img
            v-if="review.author_avatar_url && !hasAvatarLoadError"
            :src="review.author_avatar_url"
            :alt="review.author_name ?? 'Аватар'"
            class="w-full h-full object-cover"
            loading="lazy"
            referrerpolicy="no-referrer"
            @error="hasAvatarLoadError = true"
          />
          <span v-else>{{ authorInitials }}</span>
        </div>

        <div>
          <h4 class="text-sm font-semibold text-slate-900 dark:text-white leading-tight">
            <template v-for="(part, idx) in getHighlightedParts(review.author_name || 'Пользователь Яндекс.Карт')" :key="idx">
              <mark v-if="part.isMatch" class="bg-amber-200/90 dark:bg-amber-800/80 text-amber-950 dark:text-amber-100 px-0.5 rounded-xs font-semibold">{{ part.text }}</mark>
              <template v-else>{{ part.text }}</template>
            </template>
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
      <div v-if="review.text">
        <p>
          <template v-for="(part, idx) in getHighlightedParts(displayedReviewText)" :key="idx">
            <mark v-if="part.isMatch" class="bg-amber-200/90 dark:bg-amber-800/80 text-amber-950 dark:text-amber-100 px-0.5 rounded-xs font-semibold">{{ part.text }}</mark>
            <template v-else>{{ part.text }}</template>
          </template>
        </p>

        <!-- Кнопка «Показать полностью / Свернуть» -->
        <button
          v-if="isTextLong"
          type="button"
          @click="isTextExpanded = !isTextExpanded"
          class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition cursor-pointer"
        >
          <span>{{ isTextExpanded ? 'Свернуть' : 'Показать полностью' }}</span>
          <svg
            class="w-3.5 h-3.5 transition-transform duration-200"
            :class="{ 'rotate-180': isTextExpanded }"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
      </div>
      <p v-else class="text-slate-400 dark:text-slate-500 italic">Пользователь поставил оценку без текстового комментария.</p>
    </div>

    <!-- Review Photos Gallery -->
    <div v-if="review.photos && review.photos.length > 0" class="mt-3.5">
      <div class="flex flex-wrap gap-2">
        <button
          v-for="(photo, pIdx) in review.photos"
          :key="photo.id || pIdx"
          type="button"
          @click="openLightbox(pIdx)"
          class="relative group w-20 h-20 sm:w-24 sm:h-24 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-900 cursor-pointer focus:outline-hidden focus:ring-2 focus:ring-red-500 shadow-2xs"
          :title="`Открыть фото ${pIdx + 1}`"
          :aria-label="`Просмотреть фото ${pIdx + 1}`"
        >
          <img
            :src="photo.preview_url || photo.full_url"
            alt="Фото к отзыву"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
            loading="lazy"
            referrerpolicy="no-referrer"
          />
          <div class="absolute inset-0 bg-black/0 group-hover:bg-black/25 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100">
            <svg class="w-5 h-5 text-white drop-shadow-xs" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7" />
            </svg>
          </div>
        </button>
      </div>
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
      <div>
        <p class="text-slate-700 dark:text-slate-200 mt-1 leading-normal whitespace-pre-line">
          <template v-for="(part, idx) in getHighlightedParts(displayedResponseText)" :key="idx">
            <mark v-if="part.isMatch" class="bg-amber-200/90 dark:bg-amber-800/80 text-amber-950 dark:text-amber-100 px-0.5 rounded-xs font-semibold">{{ part.text }}</mark>
            <template v-else>{{ part.text }}</template>
          </template>
        </p>

        <button
          v-if="isResponseLong"
          type="button"
          @click="isResponseExpanded = !isResponseExpanded"
          class="mt-1.5 inline-flex items-center gap-1 text-[11px] font-semibold text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition cursor-pointer"
        >
          <span>{{ isResponseExpanded ? 'Свернуть' : 'Показать полностью' }}</span>
          <svg
            class="w-3 h-3 transition-transform duration-200"
            :class="{ 'rotate-180': isResponseExpanded }"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Lightbox Modal for Fullscreen Photos -->
    <PhotoLightboxModal
      v-if="review.photos && review.photos.length > 0"
      :is-open="isLightboxOpen"
      :photos="review.photos"
      :initial-index="selectedPhotoIndex"
      @close="isLightboxOpen = false"
    />
  </article>
</template>
