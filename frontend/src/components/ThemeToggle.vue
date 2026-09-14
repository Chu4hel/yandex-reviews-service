<script setup lang="ts">
import { useTheme } from '@/composables/useTheme'

const { theme, setTheme } = useTheme()
</script>

<template>
  <div class="inline-flex items-center p-0.5 rounded-xl bg-slate-100 dark:bg-slate-700/80 border border-slate-200/80 dark:border-slate-600">
    <button
      type="button"
      @click="setTheme('light')"
      :class="[
        'p-1.5 rounded-lg transition text-xs flex items-center justify-center cursor-pointer',
        theme === 'light'
          ? 'bg-white text-amber-500 shadow-xs'
          : 'text-slate-400 hover:text-slate-700 dark:hover:text-slate-200',
      ]"
      title="Светлая тема"
      aria-label="Светлая тема"
    >
      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
      </svg>
    </button>
    <button
      type="button"
      @click="setTheme('dark')"
      :class="[
        'p-1.5 rounded-lg transition text-xs flex items-center justify-center cursor-pointer',
        theme === 'dark'
          ? 'bg-slate-800 text-indigo-400 shadow-xs'
          : 'text-slate-400 hover:text-slate-700 dark:hover:text-slate-200',
      ]"
      title="Тёмная тема"
      aria-label="Тёмная тема"
    >
      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
      </svg>
    </button>
    <button
      type="button"
      @click="setTheme('system')"
      :class="[
        'p-1.5 rounded-lg transition text-xs flex items-center justify-center cursor-pointer',
        theme === 'system'
          ? 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 shadow-xs'
          : 'text-slate-400 hover:text-slate-700 dark:hover:text-slate-200',
      ]"
      title="Системная тема"
      aria-label="Системная тема"
    >
      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
      </svg>
    </button>
  </div>
</template>
