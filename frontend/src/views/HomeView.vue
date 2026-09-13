<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { checkHealth, type HealthCheckResponse } from '@/services/api'

const backendStatus = ref<HealthCheckResponse | null>(null)
const isLoading = ref<boolean>(true)
const errorMessage = ref<string | null>(null)

const fetchBackendHealth = async (): Promise<void> => {
  isLoading.value = true
  errorMessage.value = null
  try {
    const data = await checkHealth()
    backendStatus.value = data
  } catch (err: unknown) {
    errorMessage.value = err instanceof Error ? err.message : 'Не удалось связаться с API бэкенда'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void fetchBackendHealth()
})
</script>

<template>
  <div class="space-y-6">
    <section class="bg-white dark:bg-slate-800 shadow rounded-xl p-6 border border-slate-200 dark:border-slate-700">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700 mb-6">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">Интеграция с Яндекс.Картами</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400">
            Сервис сбора данных, рейтинга и отзывов организаций с площадки Яндекс.Карты
          </p>
        </div>
        <div class="flex items-center gap-3">
          <div
            v-if="isLoading"
            class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
          >
            <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
            Проверка API...
          </div>
          <div
            v-else-if="backendStatus?.status === 'ok'"
            class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300"
          >
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            API подключено (Laravel 12)
          </div>
          <div
            v-else
            class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold rounded-full bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300"
          >
            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
            API недоступно
          </div>
          <button
            @click="fetchBackendHealth"
            class="text-xs px-2.5 py-1 rounded-md border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition"
          >
            Обновить
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-700/50 border border-slate-200/60 dark:border-slate-700">
          <span class="text-xs font-medium uppercase tracking-wider text-slate-400">Стек бэкенда</span>
          <p class="mt-1 text-base font-semibold text-slate-800 dark:text-slate-100">Laravel 12 (API / REST)</p>
          <p class="text-xs text-slate-500 mt-1">Dependency Inversion, Repositories, Services</p>
        </div>
        <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-700/50 border border-slate-200/60 dark:border-slate-700">
          <span class="text-xs font-medium uppercase tracking-wider text-slate-400">Стек фронтенда</span>
          <p class="mt-1 text-base font-semibold text-slate-800 dark:text-slate-100">Vue 3 SPA + Vite + Pinia</p>
          <p class="text-xs text-slate-500 mt-1">TypeScript, Tailwind CSS v4, Vue Router</p>
        </div>
        <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-700/50 border border-slate-200/60 dark:border-slate-700">
          <span class="text-xs font-medium uppercase tracking-wider text-slate-400">База данных</span>
          <p class="mt-1 text-base font-semibold text-slate-800 dark:text-slate-100">SQLite (dev) / MySQL</p>
          <p class="text-xs text-slate-500 mt-1">Миграции и связи моделей готовы к расширению</p>
        </div>
      </div>
    </section>

    <section class="bg-white dark:bg-slate-800 shadow rounded-xl p-6 border border-slate-200 dark:border-slate-700">
      <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">Следующий шаг: Реализация логики ТЗ</h3>
      <p class="text-sm text-slate-600 dark:text-slate-300 mb-4">
        Проект успешно инициализирован, окружение настроено и готово к загрузке полного технического задания.
      </p>
      <div class="p-4 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 text-indigo-900 dark:text-indigo-200 text-sm">
        💡 Ожидаем ввод детального ТЗ для реализации парсера Яндекс.Карт, моделей организаций/отзывов и UI-интерфейса карточек.
      </div>
    </section>
  </div>
</template>
