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
      return 'bg-success-100 text-success-800 border-success-200'
    case 'warning':
      return 'bg-warning-100 text-warning-800 border-warning-200'
    case 'danger':
      return 'bg-danger-100 text-danger-800 border-danger-200'
    case 'info':
      return 'bg-info-100 text-info-800 border-info-200'
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
      return 'bg-success-700'
    case 'warning':
      return 'bg-warning-700'
    case 'danger':
      return 'bg-danger-700'
    case 'info':
      return 'bg-info-700'
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
