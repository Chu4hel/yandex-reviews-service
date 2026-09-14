<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useOrganizationsStore } from '@/stores/organizations'
import { useNotificationStore } from '@/stores/notification'
import { extractRequestId } from '@/services/api'
import RatingStars from '@/components/RatingStars.vue'

const router = useRouter()
const store = useOrganizationsStore()
const notificationStore = useNotificationStore()

const inputUrl = ref<string>('')
const localError = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const isSubmitting = ref<boolean>(false)
let pollingTimer: number | null = null

const demoExamples = [
  {
    name: 'Додо Пицца (Полянка)',
    url: 'https://yandex.ru/maps/org/dodo_pizza/67037665858/reviews/',
  },
  {
    name: 'Шоколадница (Тверская)',
    url: 'https://yandex.ru/maps/org/shokoladnitsa/1098616954/reviews/',
  },
  {
    name: 'Вкусно — и точка',
    url: 'https://yandex.ru/maps/org/vkusno_i_tochka/1151608688/reviews/',
  },
]

const setExample = (url: string): void => {
  inputUrl.value = url
  localError.value = null
}

const handleSubmit = async (): Promise<void> => {
  localError.value = null
  successMessage.value = null

  const trimmed = inputUrl.value.trim()
  if (!trimmed) {
    localError.value = 'Пожалуйста, вставьте ссылку на карточку организации в Яндекс.Картах'
    return
  }

  isSubmitting.value = true
  try {
    const org = await store.connectOrganization(trimmed)
    successMessage.value = `Организация "${org.name}" успешно подключена! Запущен сбор отзывов.`
    notificationStore.success('Организация подключена', `Карточка "${org.name}" успешно добавлена в мониторинг`)
    inputUrl.value = ''
    startPolling()
  } catch (err: unknown) {
    const msg = err instanceof Error ? err.message : 'Не удалось подключить организацию'
    localError.value = msg
    notificationStore.error('Ошибка подключения', msg, extractRequestId(err))
  } finally {
    isSubmitting.value = false
  }
}

const triggerSync = async (id: number): Promise<void> => {
  try {
    await store.syncOrganization(id, true)
    notificationStore.success('Синхронизация запущена', 'Сбор отзывов выполняется в фоновом режиме')
    startPolling()
  } catch (err: unknown) {
    const msg = err instanceof Error ? err.message : 'Не удалось запустить синхронизацию'
    notificationStore.error('Ошибка синхронизации', msg, extractRequestId(err))
  }
}

const startPolling = (): void => {
  if (pollingTimer) return
  pollingTimer = window.setInterval(async () => {
    const hasSyncing = store.organizations.some(
      (o) => o.sync_status === 'syncing' || o.sync_status === 'pending'
    )
    if (!hasSyncing) {
      stopPolling()
      return
    }

    for (const org of store.organizations) {
      if (org.sync_status === 'syncing' || org.sync_status === 'pending') {
        await store.updateStatus(org.id)
      }
    }
  }, 3000)
}

const stopPolling = (): void => {
  if (pollingTimer) {
    clearInterval(pollingTimer)
    pollingTimer = null
  }
}

onMounted(() => {
  void store.fetchOrganizations().then(() => {
    const hasSyncing = store.organizations.some(
      (o) => o.sync_status === 'syncing' || o.sync_status === 'pending'
    )
    if (hasSyncing) {
      startPolling()
    }
  })
})

onUnmounted(() => {
  stopPolling()
})
</script>

<template>
  <div class="space-y-8">
    <!-- Header -->
    <div>
      <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Настройки и подключение организаций</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
        Укажите ссылку на карточку Яндекс.Карт для запуска парсинга отзывов, рейтинга и счетчиков
      </p>
    </div>

    <!-- Connection Card -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 sm:p-8 border border-slate-200/80 dark:border-slate-700 shadow-sm">
      <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Подключить организацию</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 mb-6">
        Поддерживаются ссылки на Яндекс.Карты любого вида: стандартные с названием, короткие ссылки или прямой ID организации.
      </p>

      <!-- Success Alert -->
      <div
        v-if="successMessage"
        class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm flex items-center justify-between"
      >
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span>{{ successMessage }}</span>
        </div>
        <button @click="successMessage = null" class="text-xs underline text-emerald-600">Закрыть</button>
      </div>

      <!-- Error Alert -->
      <div
        v-if="localError || store.error"
        class="mb-6 p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-sm flex items-center gap-2"
      >
        <svg class="w-5 h-5 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>{{ localError || store.error }}</span>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
            Ссылка на карточку Яндекс.Карт
          </label>
          <div class="flex flex-col sm:flex-row gap-3">
            <input
              v-model="inputUrl"
              type="text"
              required
              placeholder="https://yandex.ru/maps/org/dodo_pizza/67037665858/reviews/"
              class="flex-1 px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition text-sm font-mono"
            />
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-6 py-3 rounded-xl bg-red-600 hover:bg-red-700 text-white font-semibold text-sm shadow-sm transition flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer shrink-0"
            >
              <svg v-if="isSubmitting" class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>{{ isSubmitting ? 'Подключение...' : 'Подключить карточку' }}</span>
            </button>
          </div>
        </div>

        <!-- Examples -->
        <div class="pt-2">
          <span class="text-xs text-slate-500 mr-2">Быстрый выбор для проверки:</span>
          <div class="inline-flex flex-wrap gap-2 mt-2">
            <button
              v-for="example in demoExamples"
              :key="example.name"
              type="button"
              @click="setExample(example.url)"
              class="px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600 transition cursor-pointer"
            >
              {{ example.name }}
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Organizations List -->
    <div>
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Подключенные карточки</h3>
        <button
          @click="store.fetchOrganizations"
          class="text-xs text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition flex items-center gap-1 cursor-pointer"
        >
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Обновить список
        </button>
      </div>

      <div v-if="store.isLoading && store.organizations.length === 0" class="text-center py-12 text-slate-500 text-sm">
        Загрузка подключенных организаций...
      </div>

      <div v-else-if="store.organizations.length === 0" class="bg-white dark:bg-slate-800 rounded-xl p-8 text-center border border-slate-200 dark:border-slate-700">
        <p class="text-slate-500 text-sm">Пока нет подключенных организаций. Вставьте ссылку выше, чтобы начать сбор отзывов.</p>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="org in store.organizations"
          :key="org.id"
          class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200/80 dark:border-slate-700 shadow-xs flex flex-col justify-between hover:shadow-md transition-shadow"
        >
          <div>
            <!-- Header & Badge -->
            <div class="flex items-start justify-between gap-2 mb-3">
              <h4 class="font-bold text-base text-slate-900 dark:text-white leading-tight">
                {{ org.name }}
              </h4>
              <!-- Status Badge -->
              <span
                v-if="org.sync_status === 'syncing'"
                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 shrink-0"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                Сбор ({{ org.sync_progress }}%)
              </span>
              <span
                v-else-if="org.sync_status === 'completed'"
                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 shrink-0"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                Синхронизировано
              </span>
              <span
                v-else-if="org.sync_status === 'failed'"
                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 shrink-0"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                Ошибка
              </span>
              <span
                v-else
                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 shrink-0"
              >
                Ожидание
              </span>
            </div>

            <!-- Address -->
            <p v-if="org.address" class="text-xs text-slate-500 dark:text-slate-400 mb-4 line-clamp-2">
              📍 {{ org.address }}
            </p>

            <!-- Metrics -->
            <div class="grid grid-cols-2 gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50 mb-4 text-center">
              <div>
                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Рейтинг</span>
                <div class="flex items-center justify-center gap-1 mt-0.5">
                  <RatingStars :rating="org.rating" size="sm" showNumber />
                </div>
              </div>
              <div>
                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Отзывов / Оценок</span>
                <div class="text-xs font-bold text-slate-800 dark:text-slate-100 mt-0.5">
                  {{ org.reviews_count.toLocaleString('ru-RU') }} / {{ org.ratings_count.toLocaleString('ru-RU') }}
                </div>
              </div>
            </div>

            <!-- Sync Progress Bar if syncing -->
            <div v-if="org.sync_status === 'syncing'" class="mb-4">
              <div class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full overflow-hidden">
                <div
                  class="bg-red-600 h-1.5 rounded-full transition-all duration-300"
                  :style="{ width: `${org.sync_progress}%` }"
                ></div>
              </div>
              <span class="text-[10px] text-slate-400 block mt-1">
                Выполняется постраничный парсинг отзывов... {{ org.sync_progress }}%
              </span>
            </div>
          </div>

          <!-- Actions -->
          <div class="pt-4 border-t border-slate-100 dark:border-slate-700/80 flex items-center justify-between gap-2">
            <button
              type="button"
              @click="router.push(`/organizations/${org.id}`)"
              class="flex-1 py-2 px-3 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold text-center transition cursor-pointer"
            >
              Открыть отзывы &rarr;
            </button>
            <button
              type="button"
              @click="triggerSync(org.id)"
              :disabled="org.sync_status === 'syncing'"
              title="Запустить повторную синхронизацию"
              class="p-2 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs transition disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
            >
              🔄
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
