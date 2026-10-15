<script setup lang="ts">
withDefaults(defineProps<{
  type?: 'card' | 'header' | 'table' | 'text' | 'custom'
  lines?: number
  height?: string
  width?: string
}>(), {
  type: 'card',
  lines: 3,
  height: 'h-4',
  width: 'w-full'
})
</script>

<template>
  <!-- Card Skeleton -->
  <div v-if="type === 'card'" class="rounded-2xl border border-slate-200/80 bg-white p-5 space-y-3 animate-pulse">
    <div class="h-4 bg-slate-200 rounded-md w-1/3" />
    <div class="h-6 bg-slate-200 rounded-md w-2/3" />
    <div class="h-3 bg-slate-200 rounded-md w-full" />
    <div class="pt-3 border-t border-slate-100 flex gap-2">
      <div class="h-8 bg-slate-200 rounded-xl w-1/2" />
      <div class="h-8 bg-slate-200 rounded-xl w-1/2" />
    </div>
  </div>

  <!-- Header / Banner Skeleton -->
  <div v-else-if="type === 'header'" class="rounded-3xl border border-slate-200/80 bg-white p-6 sm:p-8 space-y-4 animate-pulse">
    <div class="h-5 bg-slate-200 rounded-md w-1/4" />
    <div class="h-8 bg-slate-200 rounded-md w-1/2" />
    <div class="h-4 bg-slate-200 rounded-md w-3/4" />
  </div>

  <!-- Table Rows Skeleton -->
  <div v-else-if="type === 'table'" class="divide-y divide-slate-100 border border-slate-200/80 rounded-2xl bg-white animate-pulse overflow-hidden">
    <div v-for="i in lines" :key="i" class="p-4 flex items-center justify-between gap-4">
      <div class="space-y-1.5 flex-1">
        <div class="h-4 bg-slate-200 rounded w-1/3" />
        <div class="h-3 bg-slate-200 rounded w-1/4" />
      </div>
      <div class="h-7 bg-slate-200 rounded-lg w-20" />
    </div>
  </div>

  <!-- Multi-line Text Skeleton -->
  <div v-else-if="type === 'text'" class="space-y-2 animate-pulse">
    <div v-for="i in lines" :key="i" class="h-3.5 bg-slate-200 rounded" :class="i === lines ? 'w-2/3' : 'w-full'" />
  </div>

  <!-- Custom Box Skeleton -->
  <div
    v-else
    class="bg-slate-200 rounded-xl animate-pulse"
    :class="[height, width]"
  />
</template>
