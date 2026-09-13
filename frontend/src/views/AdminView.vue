<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useAdminStore } from '@/stores/admin'

const adminStore = useAdminStore()

const activeTab = ref<'all' | 'active' | 'cooldown' | 'disabled'>('all')
const searchQuery = ref<string>('')
const showAddModal = ref<boolean>(false)
const rawProxiesInput = ref<string>('')
const localError = ref<string | null>(null)

onMounted(async () => {
  await Promise.all([adminStore.fetchSettings(), adminStore.fetchProxies()])
})

const handleRefresh = async (): Promise<void> => {
  await Promise.all([adminStore.fetchSettings(), adminStore.fetchProxies()])
}

const filteredProxies = computed(() => {
  let list = adminStore.proxies

  if (activeTab.value === 'active') {
    list = list.filter((p) => p.is_active && !p.is_cooling_down)
  } else if (activeTab.value === 'cooldown') {
    list = list.filter((p) => p.is_cooling_down)
  } else if (activeTab.value === 'disabled') {
    list = list.filter((p) => !p.is_active)
  }

  const query = searchQuery.value.trim().toLowerCase()
  if (query) {
    list = list.filter(
      (p) =>
        p.host.toLowerCase().includes(query) ||
        p.masked_endpoint.toLowerCase().includes(query) ||
        p.port.toString().includes(query)
    )
  }

  return list
})

const handleAddProxies = async (): Promise<void> => {
  localError.value = null
  const lines = rawProxiesInput.value
    .split('\n')
    .map((l) => l.trim())
    .filter((l) => l.length > 0)

  if (lines.length === 0) {
    localError.value = 'Пожалуйста, введите хотя бы один адрес прокси-сервера'
    return
  }

  const success = await adminStore.addProxies(lines)
  if (success) {
    rawProxiesInput.value = ''
    showAddModal.value = false
  }
}

const handleToggle = async (id: number): Promise<void> => {
  await adminStore.toggleProxy(id)
}

const handleDelete = async (id: number, host: string, port: number): Promise<void> => {
  if (confirm(`Вы действительно хотите удалить прокси ${host}:${port} из пула?`)) {
    await adminStore.deleteProxy(id)
  }
}

const formatDateTime = (dateStr: string | null): string => {
  if (!dateStr) return 'Не использовался'
  try {
    const d = new Date(dateStr)
    return d.toLocaleString('ru-RU', {
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    })
  } catch {
    return dateStr
  }
}
</script>

<template>
  <div class="space-y-8">
    <!-- Breadcrumb and Top Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
          <RouterLink to="/organizations" class="hover:text-red-600 transition">Организации</RouterLink>
          <span>/</span>
          <span class="text-slate-700 dark:text-slate-300 font-medium">Админ-панель</span>
        </div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
          <span>Административная панель</span>
          <span class="px-2 py-0.5 text-xs font-semibold rounded-md bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-400">
            System &amp; Proxies
          </span>
        </h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          Управление пулом ротации прокси-серверов, мониторинг системных метрик и очередей
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          type="button"
          @click="handleRefresh"
          :disabled="adminStore.isLoadingSettings || adminStore.isLoadingProxies"
          class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-750 transition shadow-xs disabled:opacity-50 cursor-pointer"
        >
          <svg
            class="w-4 h-4 text-slate-500"
            :class="{ 'animate-spin': adminStore.isLoadingSettings || adminStore.isLoadingProxies }"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
            />
          </svg>
          Обновить данные
        </button>

        <button
          type="button"
          @click="showAddModal = !showAddModal"
          class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-semibold shadow-xs transition cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Добавить прокси
        </button>
      </div>
    </div>

    <!-- Alert Notifications -->
    <div
      v-if="adminStore.actionSuccess"
      class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs sm:text-sm flex items-center justify-between"
    >
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span>{{ adminStore.actionSuccess }}</span>
      </div>
      <button type="button" @click="adminStore.clearMessages" class="text-xs underline text-emerald-600 dark:text-emerald-400">
        Закрыть
      </button>
    </div>

    <div
      v-if="adminStore.error"
      class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-xs sm:text-sm flex items-center justify-between"
    >
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5 text-rose-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>{{ adminStore.error }}</span>
      </div>
      <button type="button" @click="adminStore.clearMessages" class="text-xs underline text-rose-600 dark:text-rose-400">
        Закрыть
      </button>
    </div>

    <!-- System Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Card 1: Proxy Pool Health -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-700 shadow-xs flex flex-col justify-between">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Пул прокси</span>
          <span
            v-if="adminStore.settings"
            class="px-2 py-0.5 rounded text-[11px] font-bold"
            :class="
              adminStore.settings.proxy_pool_metrics.available_for_use > 0
                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-400'
                : 'bg-amber-100 text-amber-700 dark:bg-amber-950/80 dark:text-amber-400'
            "
          >
            {{ adminStore.settings.proxy_pool_metrics.available_for_use > 0 ? 'Готов к работе' : 'Без прокси' }}
          </span>
        </div>
        <div>
          <div class="text-2xl font-extrabold text-slate-900 dark:text-white">
            {{ adminStore.settings?.proxy_pool_metrics.available_for_use ?? '0' }}
            <span class="text-sm font-normal text-slate-400">/ {{ adminStore.settings?.proxy_pool_metrics.total_proxies ?? '0' }} активных</span>
          </div>
          <p class="text-xs text-slate-500 mt-1">
            На охлаждении: <span class="font-semibold text-amber-600 dark:text-amber-400">{{ adminStore.settings?.proxy_pool_metrics.cooling_down_proxies ?? 0 }}</span>
            &bull; Задержка: <span class="font-semibold">{{ adminStore.settings?.proxy_pool_metrics.avg_latency_ms ?? 0 }} мс</span>
          </p>
        </div>
      </div>

      <!-- Card 2: Database Volume -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-700 shadow-xs flex flex-col justify-between">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-400">База данных</span>
          <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-950/80 dark:text-blue-400">
            PostgreSQL
          </span>
        </div>
        <div>
          <div class="text-2xl font-extrabold text-slate-900 dark:text-white">
            {{ adminStore.settings?.database_metrics.reviews_count ?? '0' }}
            <span class="text-sm font-normal text-slate-400">отзывов</span>
          </div>
          <p class="text-xs text-slate-500 mt-1">
            Организаций: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ adminStore.settings?.database_metrics.organizations_count ?? 0 }}</span>
            &bull; Снимков: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ adminStore.settings?.database_metrics.snapshots_count ?? 0 }}</span>
          </p>
        </div>
      </div>

      <!-- Card 3: Queue & Background Workers -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-700 shadow-xs flex flex-col justify-between">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Очередь задач</span>
          <span
            class="px-2 py-0.5 rounded text-[11px] font-bold"
            :class="
              (adminStore.settings?.queue_metrics.failed_jobs ?? 0) > 0
                ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-400'
                : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-400'
            "
          >
            {{ (adminStore.settings?.queue_metrics.failed_jobs ?? 0) > 0 ? 'Ошибки в очереди' : 'Очередь в норме' }}
          </span>
        </div>
        <div>
          <div class="text-2xl font-extrabold text-slate-900 dark:text-white">
            {{ adminStore.settings?.queue_metrics.pending_jobs ?? '0' }}
            <span class="text-sm font-normal text-slate-400">в очереди</span>
          </div>
          <p class="text-xs text-slate-500 mt-1">
            Ошибочных задач:
            <span :class="(adminStore.settings?.queue_metrics.failed_jobs ?? 0) > 0 ? 'text-rose-600 font-bold' : 'text-slate-400'">
              {{ adminStore.settings?.queue_metrics.failed_jobs ?? 0 }}
            </span>
            &bull; Драйвер: <span class="font-semibold">{{ adminStore.settings?.environment.queue_driver ?? 'sync' }}</span>
          </p>
        </div>
      </div>

      <!-- Card 4: Platform & Environment -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-700 shadow-xs flex flex-col justify-between">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Платформа</span>
          <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
            Laravel 12
          </span>
        </div>
        <div>
          <div class="text-base font-bold text-slate-900 dark:text-white">
            PHP {{ adminStore.settings?.environment.php_version ?? '-' }}
          </div>
          <p class="text-xs text-slate-500 mt-1">
            Время сервера:
            <span class="font-medium text-slate-700 dark:text-slate-300">
              {{ formatDateTime(adminStore.settings?.environment.server_time ?? null) }}
            </span>
          </p>
        </div>
      </div>
    </div>

    <!-- Add Proxies Card/Drawer (Toggleable) -->
    <div
      v-if="showAddModal"
      class="bg-white dark:bg-slate-800 rounded-2xl p-6 border-2 border-red-200 dark:border-red-900/50 shadow-md transition-all"
    >
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-base font-bold text-slate-900 dark:text-white">Добавить прокси-серверы в пул</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Введите адреса прокси по одному на строку. Поддерживаются любые форматы с авторизацией: <code class="text-red-600 font-mono">ip:port:login:password</code>, <code class="text-red-600 font-mono">ip:port@login:password</code>, <code class="text-red-600 font-mono">http://user:pass@host:port</code>, <code class="text-red-600 font-mono">socks5://...</code> или <code class="text-red-600 font-mono">ip:port</code>.
          </p>
        </div>
        <button
          type="button"
          @click="showAddModal = false"
          class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
        >
          ✕ Закрыть
        </button>
      </div>

      <div v-if="localError" class="mb-4 text-xs text-rose-600 font-medium">
        {{ localError }}
      </div>

      <textarea
        v-model="rawProxiesInput"
        rows="4"
        placeholder="192.168.1.100:8080:myuser:secret123&#10;192.168.1.100:8080@myuser:secret123&#10;socks5://user:secret@185.123.45.67:1080&#10;192.168.1.100:3128"
        class="w-full font-mono text-xs p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 focus:outline-hidden focus:ring-2 focus:ring-red-500 text-slate-800 dark:text-slate-100"
      ></textarea>

      <div class="mt-4 flex items-center justify-end gap-3">
        <button
          type="button"
          @click="showAddModal = false"
          class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
        >
          Отмена
        </button>
        <button
          type="button"
          @click="handleAddProxies"
          :disabled="adminStore.isSubmitting"
          class="px-5 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-semibold shadow-xs transition disabled:opacity-50 flex items-center gap-2 cursor-pointer"
        >
          <span v-if="adminStore.isSubmitting">Сохранение...</span>
          <span v-else>Добавить в ротацию</span>
        </button>
      </div>
    </div>

    <!-- Proxy Servers Table & Controls -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700 shadow-xs overflow-hidden">
      <!-- Toolbar -->
      <div class="p-5 border-b border-slate-200 dark:border-slate-700 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Tabs -->
        <div class="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-900 rounded-xl">
          <button
            type="button"
            @click="activeTab = 'all'"
            class="px-3 py-1.5 rounded-lg text-xs font-medium transition cursor-pointer"
            :class="
              activeTab === 'all'
                ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs'
                : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
            "
          >
            Все ({{ adminStore.proxies.length }})
          </button>
          <button
            type="button"
            @click="activeTab = 'active'"
            class="px-3 py-1.5 rounded-lg text-xs font-medium transition cursor-pointer"
            :class="
              activeTab === 'active'
                ? 'bg-white dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 shadow-xs'
                : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
            "
          >
            Активные ({{ adminStore.proxies.filter((p) => p.is_active && !p.is_cooling_down).length }})
          </button>
          <button
            type="button"
            @click="activeTab = 'cooldown'"
            class="px-3 py-1.5 rounded-lg text-xs font-medium transition cursor-pointer"
            :class="
              activeTab === 'cooldown'
                ? 'bg-white dark:bg-slate-800 text-amber-600 dark:text-amber-400 shadow-xs'
                : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
            "
          >
            Охлаждение ({{ adminStore.proxies.filter((p) => p.is_cooling_down).length }})
          </button>
          <button
            type="button"
            @click="activeTab = 'disabled'"
            class="px-3 py-1.5 rounded-lg text-xs font-medium transition cursor-pointer"
            :class="
              activeTab === 'disabled'
                ? 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 shadow-xs'
                : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
            "
          >
            Отключенные ({{ adminStore.proxies.filter((p) => !p.is_active).length }})
          </button>
        </div>

        <!-- Search Input -->
        <div class="w-full md:w-72">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Поиск по IP / хосту..."
            class="w-full px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100 focus:outline-hidden focus:ring-2 focus:ring-red-500"
          />
        </div>
      </div>

      <!-- Table Content -->
      <div v-if="adminStore.isLoadingProxies && adminStore.proxies.length === 0" class="p-12 text-center text-xs text-slate-400">
        Загрузка списка прокси-серверов...
      </div>

      <div v-else-if="filteredProxies.length === 0" class="p-12 text-center">
        <svg class="w-10 h-10 text-slate-300 dark:text-slate-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Прокси-серверы не найдены</p>
        <p class="text-xs text-slate-400 mt-1">
          {{ adminStore.proxies.length === 0 ? 'Пул пуст. Нажмите «Добавить прокси» для добавления первых адресов.' : 'По заданному фильтру прокси отсутствуют.' }}
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 font-semibold">
            <tr>
              <th class="px-5 py-3.5">Адрес / Эндпоинт</th>
              <th class="px-4 py-3.5">Протокол</th>
              <th class="px-4 py-3.5">Статус</th>
              <th class="px-4 py-3.5">Задержка</th>
              <th class="px-4 py-3.5">Успешно / Ошибок</th>
              <th class="px-4 py-3.5">Последний вызов</th>
              <th class="px-5 py-3.5 text-right">Действия</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr
              v-for="proxy in filteredProxies"
              :key="proxy.id"
              class="hover:bg-slate-50/70 dark:hover:bg-slate-750/50 transition-colors"
            >
              <!-- Endpoint -->
              <td class="px-5 py-3.5 font-mono text-slate-800 dark:text-slate-200">
                <span class="font-semibold">{{ proxy.host }}</span>:{{ proxy.port }}
                <div v-if="proxy.username" class="text-[10px] text-slate-400 font-sans mt-0.5">
                  auth: {{ proxy.username }}
                </div>
              </td>

              <!-- Protocol -->
              <td class="px-4 py-3.5">
                <span class="px-2 py-0.5 rounded uppercase font-bold text-[10px] bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                  {{ proxy.protocol }}
                </span>
              </td>

              <!-- Status -->
              <td class="px-4 py-3.5">
                <div class="flex items-center gap-1.5">
                  <span
                    v-if="proxy.is_cooling_down"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                    Охлаждение
                  </span>
                  <span
                    v-else-if="proxy.is_active"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Активен
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                    Отключен
                  </span>
                </div>
                <div v-if="proxy.last_error" class="text-[10px] text-rose-500 mt-1 truncate max-w-xs" :title="proxy.last_error">
                  {{ proxy.last_error }}
                </div>
              </td>

              <!-- Latency -->
              <td class="px-4 py-3.5 font-medium">
                <span v-if="proxy.avg_response_time_ms !== null" :class="proxy.avg_response_time_ms < 800 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'">
                  {{ proxy.avg_response_time_ms }} мс
                </span>
                <span v-else class="text-slate-400">—</span>
              </td>

              <!-- Success / Fails -->
              <td class="px-4 py-3.5">
                <div class="flex items-center gap-2">
                  <span class="text-emerald-600 font-semibold" title="Успешные запросы">{{ proxy.success_count }}</span>
                  <span class="text-slate-300">/</span>
                  <span :class="proxy.fails_count > 0 ? 'text-rose-600 font-bold' : 'text-slate-400'" title="Неудачные попытки">
                    {{ proxy.fails_count }}
                  </span>
                </div>
              </td>

              <!-- Last Used -->
              <td class="px-4 py-3.5 text-slate-500">
                {{ formatDateTime(proxy.last_used_at) }}
              </td>

              <!-- Actions -->
              <td class="px-5 py-3.5 text-right whitespace-nowrap">
                <div class="inline-flex items-center gap-2">
                  <!-- Toggle Button -->
                  <button
                    type="button"
                    @click="handleToggle(proxy.id)"
                    class="px-2.5 py-1 rounded-lg text-xs font-medium border transition cursor-pointer"
                    :class="
                      proxy.is_active
                        ? 'border-amber-200 dark:border-amber-900/60 text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40'
                        : 'border-emerald-200 dark:border-emerald-900/60 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40'
                    "
                  >
                    {{ proxy.is_active ? 'Приостановить' : 'Активировать' }}
                  </button>

                  <!-- Delete Button -->
                  <button
                    type="button"
                    @click="handleDelete(proxy.id, proxy.host, proxy.port)"
                    class="p-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer"
                    title="Удалить из пула"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                      />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
