export type ToastType = 'success' | 'error' | 'warning' | 'info'

export interface ToastItem {
  id: string
  type: ToastType
  title: string
  message?: string
  requestId?: string
  duration?: number
  timestamp: number
}

export interface ShowToastOptions {
  type?: ToastType
  title: string
  message?: string
  requestId?: string
  duration?: number
}
