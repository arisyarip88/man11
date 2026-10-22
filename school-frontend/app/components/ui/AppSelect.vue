<script setup lang="ts">
import { computed } from 'vue'

export interface SelectOption {
  label: string
  value: string | number | boolean
  disabled?: boolean
}

const props = withDefaults(defineProps<{
  modelValue?: string | number | boolean
  options?: Array<SelectOption | string | number>
  label?: string
  id?: string
  name?: string
  placeholder?: string
  disabled?: boolean
  required?: boolean
  error?: string
  hint?: string
  size?: 'sm' | 'md' | 'lg'
}>(), {
  modelValue: '',
  options: () => [],
  placeholder: '',
  disabled: false,
  required: false,
  error: '',
  hint: '',
  size: 'md'
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: any): void
  (e: 'change', event: Event): void
}>()

const selectId = computed(() => props.id || props.name || `app-select-${Math.random().toString(36).slice(2, 8)}`)

const normalizedOptions = computed(() => {
  return props.options.map(opt => {
    if (typeof opt === 'object' && opt !== null && 'value' in opt) {
      return opt as SelectOption
    }
    return {
      label: String(opt),
      value: opt,
      disabled: false
    }
  })
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'py-1.5 pl-3 pr-8 text-xs min-h-[38px]'
    case 'lg':
      return 'py-3 pl-4 pr-10 text-base min-h-[48px]'
    case 'md':
    default:
      return 'py-2.5 pl-3.5 pr-9 text-xs sm:text-sm min-h-[44px]'
  }
})

const onChange = (event: Event) => {
  const target = event.target as HTMLSelectElement
  emit('update:modelValue', target.value)
  emit('change', event)
}
</script>

<template>
  <div class="w-full flex flex-col gap-1.5">
    <label
      v-if="label"
      :for="selectId"
      class="text-xs font-bold text-slate-700 tracking-tight flex items-center justify-between"
    >
      <span>
        {{ label }}
        <span v-if="required" class="text-rose-600 font-bold ml-0.5">*</span>
      </span>
      <slot name="label-right" />
    </label>

    <div class="relative w-full">
      <select
        :id="selectId"
        :name="name"
        :value="modelValue"
        :disabled="disabled"
        :required="required"
        @change="onChange"
        class="w-full rounded-xl border appearance-none transition-all text-slate-800 bg-white focus:outline-hidden disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed"
        :class="[
          sizeClasses,
          error
            ? 'border-rose-400 focus:border-rose-600 focus:ring-2 focus:ring-rose-500/20'
            : 'border-slate-300 hover:border-slate-400 focus:border-brand-700 focus:ring-2 focus:ring-brand-700/20'
        ]"
      >
        <option v-if="placeholder" value="" disabled :selected="modelValue === ''">
          {{ placeholder }}
        </option>
        <template v-if="normalizedOptions.length > 0">
          <option
            v-for="(opt, idx) in normalizedOptions"
            :key="idx"
            :value="opt.value"
            :disabled="opt.disabled"
          >
            {{ opt.label }}
          </option>
        </template>
        <slot v-else />
      </select>

      <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </div>
    </div>

    <p v-if="error" class="text-[11px] font-semibold text-rose-600 leading-tight">
      {{ error }}
    </p>
    <p v-else-if="hint" class="text-[11px] text-slate-500 leading-tight">
      {{ hint }}
    </p>
  </div>
</template>
