<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'outline' | 'danger' | 'ghost'
  size?: 'sm' | 'md' | 'lg'
  to?: string
  href?: string
  type?: 'button' | 'submit' | 'reset'
  loading?: boolean
  disabled?: boolean
  block?: boolean
}>(), {
  variant: 'primary',
  size: 'md',
  type: 'button',
  loading: false,
  disabled: false,
  block: false
})

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'primary':
      return 'bg-brand-700 hover:bg-brand-800 active:bg-brand-900 text-white shadow-xs border border-brand-800'
    case 'secondary':
      return 'bg-slate-100 hover:bg-slate-200 active:bg-slate-300 text-slate-800 border border-slate-200'
    case 'outline':
      return 'bg-white hover:bg-slate-50 active:bg-slate-100 text-slate-800 border border-slate-300 shadow-2xs'
    case 'danger':
      return 'bg-rose-700 hover:bg-rose-800 active:bg-rose-900 text-white shadow-xs border border-rose-800'
    case 'ghost':
      return 'bg-transparent hover:bg-slate-100 active:bg-slate-200 text-slate-700 hover:text-slate-900'
    default:
      return 'bg-brand-700 hover:bg-brand-800 text-white'
  }
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'px-3 py-1.5 text-xs min-h-[36px] sm:min-h-[40px]'
    case 'lg':
      return 'px-5 py-3 text-sm min-h-[48px]'
    case 'md':
    default:
      return 'px-4 py-2.5 text-xs sm:text-sm min-h-[44px]'
  }
})

const isButtonDisabled = computed(() => props.disabled || props.loading)
</script>

<template>
  <NuxtLink
    v-if="to"
    :to="to"
    class="inline-flex items-center justify-center gap-2 rounded-xl font-bold transition-all select-none text-center cursor-pointer disabled:pointer-events-none disabled:opacity-50"
    :class="[
      variantClasses,
      sizeClasses,
      block ? 'w-full' : '',
      isButtonDisabled ? 'opacity-50 pointer-events-none' : ''
    ]"
  >
    <span v-if="loading" class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin shrink-0" />
    <slot name="prefix" />
    <slot />
    <slot name="suffix" />
  </NuxtLink>

  <a
    v-else-if="href"
    :href="href"
    target="_blank"
    rel="noopener noreferrer"
    class="inline-flex items-center justify-center gap-2 rounded-xl font-bold transition-all select-none text-center cursor-pointer"
    :class="[
      variantClasses,
      sizeClasses,
      block ? 'w-full' : '',
      isButtonDisabled ? 'opacity-50 pointer-events-none' : ''
    ]"
  >
    <span v-if="loading" class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin shrink-0" />
    <slot name="prefix" />
    <slot />
    <slot name="suffix" />
  </a>

  <button
    v-else
    :type="type"
    :disabled="isButtonDisabled"
    class="inline-flex items-center justify-center gap-2 rounded-xl font-bold transition-all select-none text-center cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
    :class="[
      variantClasses,
      sizeClasses,
      block ? 'w-full' : ''
    ]"
  >
    <span v-if="loading" class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin shrink-0" />
    <slot name="prefix" />
    <slot />
    <slot name="suffix" />
  </button>
</template>
