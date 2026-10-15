<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  variant?: 'default' | 'flat' | 'brand' | 'interactive'
  padding?: 'none' | 'sm' | 'md' | 'lg'
  as?: string
}>(), {
  variant: 'default',
  padding: 'md',
  as: 'div'
})

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'flat':
      return 'bg-slate-50 border border-slate-200/80'
    case 'brand':
      return 'bg-brand-50/70 border-2 border-brand-300'
    case 'interactive':
      return 'bg-white border border-slate-200 hover:border-brand-400 hover:shadow-xs transition-all cursor-pointer'
    case 'default':
    default:
      return 'bg-white border border-slate-200/90 shadow-2xs'
  }
})

const paddingClasses = computed(() => {
  switch (props.padding) {
    case 'none':
      return 'p-0'
    case 'sm':
      return 'p-3 sm:p-4'
    case 'lg':
      return 'p-6 sm:p-8'
    case 'md':
    default:
      return 'p-5 sm:p-6'
  }
})
</script>

<template>
  <component
    :is="as"
    class="rounded-2xl transition-colors"
    :class="[variantClasses, paddingClasses]"
  >
    <div v-if="$slots.header" class="pb-3 mb-4 border-b border-slate-100 flex items-center justify-between gap-3">
      <slot name="header" />
    </div>

    <slot />

    <div v-if="$slots.footer" class="pt-4 mt-4 border-t border-slate-100">
      <slot name="footer" />
    </div>
  </component>
</template>
