<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'
</script>

<template>
  <div class="min-h-screen bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-slate-100 flex flex-col font-sans antialiased">
    <header class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 sticky top-0 z-50 shadow-xs">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-red-600 flex items-center justify-center text-white font-bold text-lg shadow-sm">
            Я
          </div>
          <div>
            <h1 class="text-base font-bold leading-tight text-slate-900 dark:text-white">GeoReviews</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Яндекс.Карты / Отзывы и Рейтинг</p>
          </div>
        </div>

        <nav class="flex items-center gap-4 text-sm font-medium">
          <RouterLink
            to="/"
            class="px-3 py-2 rounded-lg text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/60 transition"
            active-class="bg-slate-100 dark:bg-slate-700 text-red-600 dark:text-red-400"
          >
            Главная
          </RouterLink>
        </nav>
      </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <RouterView />
    </main>

    <footer class="border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-800/60 py-4 text-center text-xs text-slate-500">
      Яндекс.Карты Интеграция &copy; 2026. Laravel 12 API + Vue 3 SPA.
    </footer>
  </div>
</template>

