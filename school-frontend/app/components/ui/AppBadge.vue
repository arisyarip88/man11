<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  variant?: 'brand' | 'success' | 'warning' | 'danger' | 'info' | 'neutral'
  size?: 'sm' | 'md'
  dot?: boolean
}>(), {
  variant: 'neutral',
  size: 'md',
  dot: false
})

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'brand':
      return 'bg-brand-100 text-brand-950 border-brand-200'
    case 'success':
      return 'bg-emerald-100 text-emerald-950 border-emerald-200'
    case 'warning':
      return 'bg-amber-100 text-amber-950 border-amber-200'
    case 'danger':
      return 'bg-rose-100 text-rose-950 border-rose-200'
    case 'info':
      return 'bg-sky-100 text-sky-950 border-sky-200'
    case 'neutral':
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200'
  }
})

const dotClasses = computed(() => {
  switch (props.variant) {
    case 'brand':
      return 'bg-brand-700'
    case 'success':
      return 'bg-emerald-700'
    case 'warning':
      return 'bg-amber-700'
    case 'danger':
      return 'bg-rose-700'
    case 'info':
      return 'bg-sky-700'
    case 'neutral':
    default:
      return 'bg-slate-600'
  }
})

const sizeClasses = computed(() => {
  return props.size === 'sm'
    ? 'px-2 py-0.5 text-xs font-semibold'
    : 'px-2.5 py-1 text-xs font-bold'
})
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-lg border transition-colors leading-tight"
    :class="[variantClasses, sizeClasses]"
  >
    <span
      v-if="dot"
      class="w-1.5 h-1.5 rounded-full shrink-0"
      :class="dotClasses"
      aria-hidden="true"
    />
    <slot />
  </span>
</template>
