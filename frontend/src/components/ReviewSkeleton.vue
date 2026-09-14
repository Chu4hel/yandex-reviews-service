<script setup lang="ts">
// Компонент скелетона загрузки карточки отзыва для плавного UX
</script>

<template>
  <article
    data-testid="review-skeleton"
    class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-slate-200/80 dark:border-slate-700 shadow-xs animate-pulse"
  >
    <!-- Шапка скелетона: Аватар, имя, звезды, дата -->
    <div class="flex items-start justify-between gap-4">
      <div class="flex items-center gap-3">
        <!-- Круглый аватар -->
        <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 shrink-0"></div>

        <!-- Имя и уровень автора -->
        <div class="space-y-1.5">
          <div class="w-28 h-3.5 bg-slate-200 dark:bg-slate-700 rounded-md"></div>
          <div class="w-20 h-2.5 bg-slate-100 dark:bg-slate-700/60 rounded-md"></div>
        </div>
      </div>

      <!-- Звёзды и дата -->
      <div class="flex flex-col items-end space-y-1.5">
        <div class="flex gap-1">
          <div v-for="i in 5" :key="i" class="w-3.5 h-3.5 bg-slate-200 dark:bg-slate-700 rounded-sm"></div>
        </div>
        <div class="w-16 h-2.5 bg-slate-100 dark:bg-slate-700/60 rounded-md"></div>
      </div>
    </div>

    <!-- Текст отзыва (строки-плейсхолдеры разной ширины) -->
    <div class="mt-4 space-y-2">
      <div class="w-full h-3 bg-slate-200 dark:bg-slate-700 rounded-md"></div>
      <div class="w-11/12 h-3 bg-slate-200 dark:bg-slate-700 rounded-md"></div>
      <div class="w-4/5 h-3 bg-slate-200 dark:bg-slate-700 rounded-md"></div>
    </div>
  </article>
</template>
