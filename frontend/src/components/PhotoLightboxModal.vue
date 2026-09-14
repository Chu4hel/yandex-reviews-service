<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import type { ReviewPhoto } from '@/types/review'

interface Props {
  isOpen: boolean
  photos: ReviewPhoto[]
  initialIndex?: number
}

const props = withDefaults(defineProps<Props>(), {
  initialIndex: 0,
})

const emit = defineEmits<{
  (e: 'close'): void
}>()

const currentIndex = ref<number>(props.initialIndex)

watch(
  () => props.initialIndex,
  (newVal) => {
    currentIndex.value = newVal
  }
)

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      currentIndex.value = Math.max(0, Math.min(props.initialIndex, props.photos.length - 1))
    }
  }
)

const currentPhoto = computed<ReviewPhoto | null>(() => {
  if (!props.photos || props.photos.length === 0) return null
  return props.photos[currentIndex.value] ?? props.photos[0] ?? null
})

const prevPhoto = (): void => {
  if (currentIndex.value > 0) {
    currentIndex.value -= 1
  } else {
    currentIndex.value = props.photos.length - 1
  }
}

const nextPhoto = (): void => {
  if (currentIndex.value < props.photos.length - 1) {
    currentIndex.value += 1
  } else {
    currentIndex.value = 0
  }
}

const handleKeydown = (e: KeyboardEvent): void => {
  if (!props.isOpen) return
  if (e.key === 'Escape') {
    emit('close')
  } else if (e.key === 'ArrowLeft') {
    prevPhoto()
  } else if (e.key === 'ArrowRight') {
    nextPhoto()
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen && currentPhoto"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 backdrop-blur-sm transition-opacity p-4 select-none"
      @click.self="emit('close')"
      role="dialog"
      aria-modal="true"
    >
      <!-- Close Button -->
      <button
        type="button"
        @click="emit('close')"
        class="absolute top-4 right-4 z-10 p-2.5 rounded-full bg-white/10 hover:bg-white/20 text-white/90 hover:text-white transition cursor-pointer"
        title="Закрыть (Esc)"
        aria-label="Закрыть просмотр фото"
      >
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      <!-- Counter -->
      <div
        v-if="photos.length > 1"
        class="absolute top-4 left-4 z-10 px-3 py-1 rounded-full bg-black/50 border border-white/10 text-white text-xs font-semibold backdrop-blur-xs"
      >
        {{ currentIndex + 1 }} из {{ photos.length }}
      </div>

      <!-- Left Navigation Arrow -->
      <button
        v-if="photos.length > 1"
        type="button"
        @click.stop="prevPhoto"
        class="absolute left-4 top-1/2 -translate-y-1/2 p-3 rounded-full bg-black/40 hover:bg-black/70 border border-white/10 text-white transition cursor-pointer z-10 hover:scale-105"
        title="Предыдущее фото (←)"
        aria-label="Предыдущее фото"
      >
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
        </svg>
      </button>

      <!-- Photo Display Container -->
      <div class="relative max-w-4xl max-h-[85vh] w-full flex items-center justify-center p-2">
        <img
          :src="currentPhoto.full_url || currentPhoto.preview_url"
          alt="Фото отзыва"
          class="max-h-[80vh] max-w-full object-contain rounded-lg shadow-2xl transition-all"
          referrerpolicy="no-referrer"
        />
      </div>

      <!-- Right Navigation Arrow -->
      <button
        v-if="photos.length > 1"
        type="button"
        @click.stop="nextPhoto"
        class="absolute right-4 top-1/2 -translate-y-1/2 p-3 rounded-full bg-black/40 hover:bg-black/70 border border-white/10 text-white transition cursor-pointer z-10 hover:scale-105"
        title="Следующее фото (→)"
        aria-label="Следующее фото"
      >
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </div>
  </Teleport>
</template>
