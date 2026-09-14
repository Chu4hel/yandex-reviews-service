import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { ShowToastOptions, ToastItem } from '@/types/notification'

export const useNotificationStore = defineStore('notification', () => {
  const toasts = ref<ToastItem[]>([])

  const dismiss = (id: string): void => {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  const clearAll = (): void => {
    toasts.value = []
  }

  const show = (options: ShowToastOptions): string => {
    const id = `toast-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
    const duration = options.duration ?? (options.type === 'error' ? 6000 : 4000)

    const toast: ToastItem = {
      id,
      type: options.type ?? 'info',
      title: options.title,
      message: options.message,
      requestId: options.requestId,
      duration,
      timestamp: Date.now(),
    }

    toasts.value.push(toast)

    if (duration > 0) {
      setTimeout(() => {
        dismiss(id)
      }, duration)
    }

    return id
  }

  const success = (title: string, message?: string, duration?: number): string => {
    return show({ type: 'success', title, message, duration })
  }

  const error = (title: string, message?: string, requestId?: string, duration?: number): string => {
    return show({ type: 'error', title, message, requestId, duration })
  }

  const warning = (title: string, message?: string, duration?: number): string => {
    return show({ type: 'warning', title, message, duration })
  }

  const info = (title: string, message?: string, duration?: number): string => {
    return show({ type: 'info', title, message, duration })
  }

  return {
    toasts,
    show,
    dismiss,
    clearAll,
    success,
    error,
    warning,
    info,
  }
})
