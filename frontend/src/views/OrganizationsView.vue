<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useOrganizationsStore } from '@/stores/organizations'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import { extractRequestId, extractRetryAfterSeconds, addProxiesApi } from '@/services/api'
import type { Organization } from '@/types/organization'
import RatingStars from '@/components/RatingStars.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'

const router = useRouter()
const store = useOrganizationsStore()
const authStore = useAuthStore()
const notificationStore = useNotificationStore()

const showAddProxyModal = ref<boolean>(false)
const rawProxiesInput = ref<string>('')
const isSubmittingProxies = ref<boolean>(false)
const proxyModalError = ref<string | null>(null)

const handleGoToAdmin = async (): Promise<void> => {
  if (!authStore.isAdmin) {
    // Если в локальном сторе еще нет флага админа, проверим профиль
    await authStore.fetchProfile()
  }

  if (authStore.isAdmin) {
    void router.push('/admin')
  } else {
    showAddProxyModal.value = true
    notificationStore.info(
      'Пополнение пула прокси',
      'Просмотр и редактирование активных прокси доступны только администраторам. Вы можете пополнить пул новыми адресами через форму.'
    )
  }
}

const handleAddProxiesSubmit = async (): Promise<void> => {
  proxyModalError.value = null
  const lines = rawProxiesInput.value
    .split('\n')
    .map((l) => l.trim())
    .filter((l) => l.length > 0)

  if (lines.length === 0) {
    proxyModalError.value = 'Пожалуйста, введите хотя бы один адрес прокси-сервера'
    return
  }

  isSubmittingProxies.value = true
  try {
    const res = await addProxiesApi(lines)
    rawProxiesInput.value = ''
    showAddProxyModal.value = false
    notificationStore.success('Прокси добавлены в пул', res.message ?? `Добавлено адресов: ${res.count}`)
  } catch (err: unknown) {
    if (typeof err === 'object' && err !== null && 'response' in err) {
      const axiosErr = err as { response?: { data?: { message?: string } } }
      proxyModalError.value = axiosErr.response?.data?.message ?? 'Не удалось добавить прокси'
    } else if (err instanceof Error) {
      proxyModalError.value = err.message
    } else {
      proxyModalError.value = 'Ошибка добавления прокси'
    }
  } finally {
    isSubmittingProxies.value = false
  }
}

const inputUrl = ref<string>('')
const localError = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const isSubmitting = ref<boolean>(false)
const orgToDelete = ref<Organization | null>(null)
const isDeleting = ref<boolean>(false)
let pollingTimer: number | null = null

const demoExamples = [
  {
    name: 'Додо Пицца (Полянка)',
    url: 'https://yandex.ru/maps/org/dodo_pizza/67037665858/reviews/',
  },
  {
    name: 'Шоколадница (Манежная)',
    url: 'https://yandex.ru/maps/org/shokoladnitsa/171251592216/reviews/',
  },
  {
    name: 'Вкусно — и точка',
    url: 'https://yandex.ru/maps/org/vkusno_i_tochka/128463520794/reviews/',
  },
  {
    name: 'Музей Яндекса',
    url: 'https://yandex.ru/maps/org/219658402738/reviews/',
  },
  {
    name: 'Эрмитаж',
    url: 'https://yandex.ru/maps/org/1057721048/reviews/',
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
    await store.triggerSync(id, false)
    notificationStore.success('Синхронизация запущена', 'Сбор отзывов выполняется в фоновом режиме')
    startPolling()
  } catch (err: unknown) {
    if (axios.isAxiosError(err) && err.response?.status === 429) {
      const waitSec = extractRetryAfterSeconds(err, 60)
      store.setSyncCooldown(id, waitSec)
      notificationStore.warning(
        'Лимит частоты запросов',
        `Слишком много запросов к сервису. Повторная попытка станет доступна через ${waitSec} сек.`,
        extractRequestId(err)
      )
      return
    }
    const msg = err instanceof Error ? err.message : 'Не удалось запустить синхронизацию'
    notificationStore.error('Ошибка синхронизации', msg, extractRequestId(err))
  }
}

const confirmDelete = (org: Organization): void => {
  orgToDelete.value = org
}

const handleDeleteConfirm = async (): Promise<void> => {
  if (!orgToDelete.value) return
  const id = orgToDelete.value.id
  const name = orgToDelete.value.name
  isDeleting.value = true
  try {
    await store.deleteOrganization(id)
    notificationStore.success('Организация удалена', `Карточка «${name}» успешно перемещена в архив`)
    orgToDelete.value = null
  } catch (err: unknown) {
    const msg = err instanceof Error ? err.message : 'Не удалось удалить организацию'
    notificationStore.error('Ошибка удаления', msg, extractRequestId(err))
  } finally {
    isDeleting.value = false
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
  }, 1500)
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
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Настройки и подключение организаций</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Укажите ссылку на карточку Яндекс.Карт для запуска парсинга отзывов, рейтинга и счетчиков
        </p>
      </div>

      <div class="flex items-center gap-2.5 flex-wrap self-start sm:self-auto">
        <button
          type="button"
          @click="showAddProxyModal = true"
          class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-semibold transition shadow-xs cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          <span>Пополнить пул прокси</span>
        </button>

        <button
          type="button"
          @click="handleGoToAdmin"
          class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-800 dark:text-white text-xs font-semibold transition border border-slate-300 dark:border-slate-600 shadow-xs cursor-pointer"
          :title="authStore.isAdmin ? 'Перейти в панель администратора' : 'Просмотр активного пула доступен администраторам'"
        >
          <svg class="w-4 h-4 text-red-600 dark:text-red-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
          </svg>
          <span class="text-slate-800 dark:text-white font-semibold">{{ authStore.isAdmin ? 'Управление пулом прокси' : 'Пул прокси (инфо)' }}</span>
          <span class="text-slate-400 dark:text-slate-300 font-bold">&rarr;</span>
        </button>
      </div>
    </div>

    <!-- Modal Add Proxies -->
    <div
      v-if="showAddProxyModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
    >
      <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 dark:border-slate-700 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span>🌐 Пополнение пула прокси</span>
          </h3>
          <button
            type="button"
            @click="showAddProxyModal = false"
            class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-sm cursor-pointer"
          >
            ✕
          </button>
        </div>

        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
          Любой авторизованный пользователь может пополнять пул серверов для бесперебойной ротации запросов.
          Поддерживаются форматы: <code class="text-red-600 font-mono">host:port</code>, <code class="text-red-600 font-mono">host:port:login:pass</code>, <code class="text-red-600 font-mono">http://login:pass@host:port</code>, <code class="text-red-600 font-mono">socks5://...</code>.
        </p>

        <div v-if="proxyModalError" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-xs">
          {{ proxyModalError }}
        </div>

        <div>
          <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">
            Список адресов (по одному на строку)
          </label>
          <textarea
            v-model="rawProxiesInput"
            rows="4"
            placeholder="192.168.1.100:8080:myuser:secret123&#10;http://user:pass@10.0.0.1:3128&#10;socks5://user:pass@172.16.0.2:1080"
            class="w-full font-mono text-xs p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 focus:outline-hidden focus:ring-2 focus:ring-red-500 text-slate-800 dark:text-slate-100"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
          <button
            type="button"
            @click="showAddProxyModal = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 cursor-pointer"
          >
            Отмена
          </button>
          <button
            type="button"
            @click="handleAddProxiesSubmit"
            :disabled="isSubmittingProxies"
            class="px-5 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-semibold shadow-xs transition disabled:opacity-50 cursor-pointer flex items-center gap-2"
          >
            <span v-if="isSubmittingProxies">Добавление...</span>
            <span v-else>Отправить в пул</span>
          </button>
        </div>
      </div>
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
              <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2 flex-wrap">
                  <h4 class="font-bold text-base text-slate-900 dark:text-white leading-tight">
                    {{ org.name }}
                  </h4>
                  <span
                    v-if="org.name.includes('Демо') || org.yandex_org_id === '67037665858'"
                    class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300 shrink-0"
                    title="Демонстрационная тестовая организация"
                  >
                    🎯 Демо-стенд
                  </span>
                </div>
              </div>
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
              <span class="text-[11px] text-amber-600 dark:text-amber-400 block mt-1 font-medium truncate" :title="org.sync_message || ''">
                {{ org.sync_message || `Сбор отзывов... ${org.sync_progress}%` }}
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
              :disabled="org.sync_status === 'syncing' || (store.syncCooldowns[org.id] ?? 0) > 0"
              :title="(store.syncCooldowns[org.id] ?? 0) > 0 ? `Подождите ${store.syncCooldowns[org.id]} с...` : 'Запустить повторную синхронизацию'"
              class="p-2 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs transition disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer flex items-center gap-1 shrink-0"
            >
              <span>🔄</span>
              <span v-if="(store.syncCooldowns[org.id] ?? 0) > 0" class="text-[11px] font-mono font-bold text-amber-600 dark:text-amber-400">
                {{ store.syncCooldowns[org.id] }}s
              </span>
            </button>
            <button
              type="button"
              @click="confirmDelete(org)"
              title="Переместить организацию в архив"
              class="p-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-rose-300 dark:hover:border-rose-800 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 text-xs transition cursor-pointer shrink-0"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Модальное окно подтверждения удаления карточки -->
    <ConfirmModal
      :is-open="orgToDelete !== null"
      :title="`Удалить карточку «${orgToDelete?.name || ''}»?`"
      message="Организация будет перемещена в архив. Фоновый сбор отзывов прекратится, карточка исчезнет из активного списка."
      confirm-text="Удалить"
      cancel-text="Отмена"
      :danger="true"
      :is-loading="isDeleting"
      @confirm="handleDeleteConfirm"
      @cancel="orgToDelete = null"
    />
  </div>
</template>
