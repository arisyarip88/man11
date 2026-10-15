<script setup lang="ts">
withDefaults(defineProps<{
  title: string
  description?: string
  actionLabel?: string
  actionTo?: string
}>(), {
  description: '',
  actionLabel: '',
  actionTo: ''
})

const emit = defineEmits<{
  (e: 'action'): void
}>()
</script>

<template>
  <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-8 sm:p-12 text-center flex flex-col items-center justify-center">
    <div class="w-12 h-12 rounded-2xl bg-white border border-slate-200 text-slate-500 flex items-center justify-center mb-3 shadow-2xs">
      <slot name="icon">
        <svg class="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
        </svg>
      </slot>
    </div>

    <h3 class="text-sm font-bold text-slate-800 leading-snug">
      {{ title }}
    </h3>

    <p v-if="description" class="text-xs text-slate-500 mt-1 max-w-sm leading-relaxed">
      {{ description }}
    </p>

    <div v-if="actionLabel" class="mt-4">
      <NuxtLink
        v-if="actionTo"
        :to="actionTo"
        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-700 hover:bg-brand-800 text-white text-xs font-bold transition shadow-xs min-h-[40px]"
      >
        <span>{{ actionLabel }}</span>
      </NuxtLink>
      <button
        v-else
        type="button"
        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-700 hover:bg-brand-800 text-white text-xs font-bold transition shadow-xs min-h-[40px]"
        @click="emit('action')"
      >
        <span>{{ actionLabel }}</span>
      </button>
    </div>
  </div>
</template>
