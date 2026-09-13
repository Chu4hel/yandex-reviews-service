<script setup lang="ts">
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const handleLogout = async (): Promise<void> => {
  await authStore.logout()
  void router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 flex flex-col font-sans antialiased">
    <!-- Header -->
    <header class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 sticky top-0 z-50 shadow-xs">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <RouterLink to="/" class="flex items-center gap-3 group">
          <div class="w-10 h-10 rounded-xl bg-red-600 flex items-center justify-center text-white font-bold text-lg shadow-sm group-hover:scale-105 transition-transform">
            Я
          </div>
          <div>
            <h1 class="text-base font-extrabold leading-tight text-slate-900 dark:text-white">
              GeoReviews
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Интеграция с Яндекс.Картами</p>
          </div>
        </RouterLink>

        <!-- Right Side: User Profile & Logout -->
        <div class="flex items-center gap-4">
          <template v-if="authStore.isAuthenticated">
            <div class="hidden sm:flex flex-col text-right">
              <span class="text-xs font-semibold text-slate-900 dark:text-white">
                {{ authStore.user?.name || 'Администратор' }}
              </span>
              <span class="text-[11px] text-slate-400">
                {{ authStore.user?.email }}
              </span>
            </div>

            <button
              type="button"
              @click="handleLogout"
              class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs font-medium text-slate-700 dark:text-slate-300 transition cursor-pointer"
            >
              Выйти
            </button>
          </template>

          <template v-else>
            <RouterLink
              to="/login"
              class="px-4 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold shadow-xs transition"
            >
              Войти
            </RouterLink>
          </template>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <RouterView />
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-800/60 py-4 text-center text-xs text-slate-500">
      Яндекс.Карты Парсер и Мониторинг отзывов &copy; 2026. Laravel 12 API + Vue 3 SPA.
    </footer>
  </div>
</template>
