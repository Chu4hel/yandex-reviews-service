<script setup lang="ts">
import { ref } from 'vue'
import { useNotificationStore } from '@/stores/notification'
import type { ToastType } from '@/types/notification'
import { CheckCircle2, AlertCircle, AlertTriangle, Info, X, Copy, Check } from 'lucide-vue-next'

const notificationStore = useNotificationStore()
const copiedId = ref<string | null>(null)

const copyRequestId = async (id: string): Promise<void> => {
  try {
    await navigator.clipboard.writeText(id)
    copiedId.value = id
    setTimeout(() => {
      if (copiedId.value === id) {
        copiedId.value = null
      }
    }, 2000)
  } catch {
    // Безопасный fallback при отсутствии прав к clipboard или в headless-браузере
  }
}

const getStyles = (type: ToastType): { container: string; iconClass: string } => {
  switch (type) {
    case 'success':
      return {
        container: 'bg-emerald-50 border-emerald-200 text-emerald-950 shadow-emerald-500/10',
        iconClass: 'text-emerald-600',
      }
    case 'error':
      return {
        container: 'bg-rose-50 border-rose-200 text-rose-950 shadow-rose-500/10',
        iconClass: 'text-rose-600',
      }
    case 'warning':
      return {
        container: 'bg-amber-50 border-amber-200 text-amber-950 shadow-amber-500/10',
        iconClass: 'text-amber-600',
      }
    case 'info':
    default:
      return {
        container: 'bg-blue-50 border-blue-200 text-blue-950 shadow-blue-500/10',
        iconClass: 'text-blue-600',
      }
  }
}
</script>

<template>
  <div
    aria-live="polite"
    class="fixed top-4 right-4 z-50 flex flex-col gap-2.5 max-w-sm w-full pointer-events-none px-4 sm:px-0"
  >
    <TransitionGroup
      name="toast"
      tag="div"
      class="flex flex-col gap-2.5"
    >
      <div
        v-for="toast in notificationStore.toasts"
        :key="toast.id"
        class="pointer-events-auto flex items-start gap-3 p-4 rounded-xl border shadow-lg transition-all duration-300"
        :class="getStyles(toast.type).container"
        role="alert"
      >
        <div class="shrink-0 mt-0.5">
          <CheckCircle2 v-if="toast.type === 'success'" class="w-5 h-5" :class="getStyles(toast.type).iconClass" />
          <AlertCircle v-else-if="toast.type === 'error'" class="w-5 h-5" :class="getStyles(toast.type).iconClass" />
          <AlertTriangle v-else-if="toast.type === 'warning'" class="w-5 h-5" :class="getStyles(toast.type).iconClass" />
          <Info v-else class="w-5 h-5" :class="getStyles(toast.type).iconClass" />
        </div>

        <div class="flex-1 min-w-0">
          <h4 class="text-sm font-semibold leading-tight">
            {{ toast.title }}
          </h4>
          <p v-if="toast.message" class="text-xs mt-1 leading-normal opacity-90 break-words">
            {{ toast.message }}
          </p>

          <div v-if="toast.requestId" class="mt-2 flex items-center gap-1.5 text-[11px] font-mono opacity-80">
            <span class="truncate">ID: {{ toast.requestId }}</span>
            <button
              type="button"
              class="p-0.5 hover:opacity-100 transition-opacity inline-flex items-center text-current cursor-pointer"
              title="Скопировать Trace ID"
              @click.stop="copyRequestId(toast.requestId)"
            >
              <Check v-if="copiedId === toast.requestId" class="w-3.5 h-3.5 text-emerald-600" />
              <Copy v-else class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <button
          type="button"
          class="shrink-0 -mr-1 -mt-1 p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-black/5 transition-colors cursor-pointer"
          aria-label="Закрыть уведомление"
          @click="notificationStore.dismiss(toast.id)"
        >
          <X class="w-4 h-4" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.25s ease-out;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(30px) scale(0.95);
}

.toast-leave-to {
  opacity: 0;
  transform: translateY(-10px) scale(0.95);
}
</style>
