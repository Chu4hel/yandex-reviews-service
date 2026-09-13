<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const email = ref<string>('admin@georeviews.local')
const password = ref<string>('password')

const handleLogin = async (): Promise<void> => {
  if (!email.value || !password.value) return
  const success = await authStore.login(email.value, password.value)
  if (success) {
    void router.push('/organizations')
  }
}

const fillDemo = (): void => {
  email.value = 'admin@georeviews.local'
  password.value = 'password'
}
</script>

<template>
  <div class="max-w-md mx-auto my-12">
    <div class="bg-white dark:bg-slate-800 shadow-xl rounded-2xl p-8 border border-slate-200/80 dark:border-slate-700">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-red-600 text-white font-bold text-2xl shadow-md mb-3">
          Я
        </div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Авторизация</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Вход в сервис работы с отзывами на Яндекс.Картах
        </p>
      </div>

      <!-- Error Alert -->
      <div
        v-if="authStore.error"
        class="mb-6 p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-sm flex items-center gap-2"
      >
        <svg class="w-5 h-5 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>{{ authStore.error }}</span>
      </div>

      <form @submit.prevent="handleLogin" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
            Email
          </label>
          <input
            v-model="email"
            type="email"
            required
            placeholder="admin@georeviews.local"
            class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition text-sm"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
            Пароль
          </label>
          <input
            v-model="password"
            type="password"
            required
            placeholder="••••••••"
            class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition text-sm"
          />
        </div>

        <button
          type="submit"
          :disabled="authStore.isLoading"
          class="w-full mt-2 py-3 px-4 rounded-xl bg-red-600 hover:bg-red-700 text-white font-semibold text-sm shadow-sm transition flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
        >
          <svg
            v-if="authStore.isLoading"
            class="animate-spin w-4 h-4 text-white"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
          </svg>
          <span>{{ authStore.isLoading ? 'Выполняется вход...' : 'Войти в систему' }}</span>
        </button>
      </form>

      <!-- Quick Demo Access Box -->
      <div class="mt-6 pt-6 border-t border-slate-100 dark:border-slate-700 text-center">
        <p class="text-xs text-slate-500 mb-2">Для проверки задания используйте сид-аккаунт:</p>
        <button
          type="button"
          @click="fillDemo"
          class="inline-flex items-center gap-1.5 text-xs text-red-600 dark:text-red-400 hover:underline font-medium cursor-pointer"
        >
          <span>📋 admin@georeviews.local / password</span>
        </button>
      </div>
    </div>
  </div>
</template>
