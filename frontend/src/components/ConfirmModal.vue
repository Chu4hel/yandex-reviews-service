<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'

const props = withDefaults(
  defineProps<{
    isOpen: boolean
    title: string
    message: string
    confirmText?: string
    cancelText?: string
    danger?: boolean
    isLoading?: boolean
  }>(),
  {
    confirmText: 'Подтвердить',
    cancelText: 'Отмена',
    danger: false,
    isLoading: false,
  }
)

const emit = defineEmits<{
  (e: 'confirm'): void
  (e: 'cancel'): void
}>()

const handleKeydown = (e: KeyboardEvent): void => {
  if (e.key === 'Escape' && props.isOpen && !props.isLoading) {
    emit('cancel')
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
      v-if="isOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 dark:bg-black/70 backdrop-blur-xs transition-opacity"
      @click.self="!isLoading && emit('cancel')"
    >
      <div
        class="bg-white dark:bg-slate-800 rounded-2xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-700 shadow-2xl transform transition-all"
        role="dialog"
        aria-modal="true"
      >
        <div class="flex items-start gap-4">
          <div
            class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
            :class="danger ? 'bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400' : 'bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400'"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>

          <div class="flex-1">
            <h3 class="text-base font-bold text-slate-900 dark:text-white leading-tight">
              {{ title }}
            </h3>
            <p class="text-xs text-slate-600 dark:text-slate-300 mt-2 leading-relaxed">
              {{ message }}
            </p>
          </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
          <button
            type="button"
            @click="emit('cancel')"
            :disabled="isLoading"
            class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition disabled:opacity-50 cursor-pointer"
          >
            {{ cancelText }}
          </button>

          <button
            type="button"
            @click="emit('confirm')"
            :disabled="isLoading"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-white transition shadow-xs flex items-center gap-1.5 disabled:opacity-50 cursor-pointer"
            :class="danger ? 'bg-rose-600 hover:bg-rose-700' : 'bg-red-600 hover:bg-red-700'"
          >
            <svg v-if="isLoading" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <span>{{ confirmText }}</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
