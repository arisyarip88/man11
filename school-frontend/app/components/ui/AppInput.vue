<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  modelValue?: string | number
  label?: string
  id?: string
  name?: string
  type?: string
  placeholder?: string
  disabled?: boolean
  readonly?: boolean
  required?: boolean
  error?: string
  hint?: string
  size?: 'sm' | 'md' | 'lg'
}>(), {
  modelValue: '',
  type: 'text',
  placeholder: '',
  disabled: false,
  readonly: false,
  required: false,
  error: '',
  hint: '',
  size: 'md'
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number): void
  (e: 'blur', event: FocusEvent): void
  (e: 'focus', event: FocusEvent): void
}>()

const inputId = computed(() => props.id || props.name || `app-input-${Math.random().toString(36).slice(2, 8)}`)

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'py-1.5 px-3 text-xs min-h-[38px]'
    case 'lg':
      return 'py-3 px-4 text-base min-h-[48px]'
    case 'md':
    default:
      return 'py-2.5 px-3.5 text-xs sm:text-sm min-h-[44px]'
  }
})

const onInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  emit('update:modelValue', target.value)
}
</script>

<template>
  <div class="w-full flex flex-col gap-1.5">
    <label
      v-if="label"
      :for="inputId"
      class="text-xs font-bold text-slate-700 tracking-tight flex items-center justify-between"
    >
      <span>
        {{ label }}
        <span v-if="required" class="text-rose-600 font-bold ml-0.5">*</span>
      </span>
      <slot name="label-right" />
    </label>

    <div class="relative w-full flex items-center">
      <div v-if="$slots.prefix" class="absolute left-3 flex items-center pointer-events-none text-slate-400">
        <slot name="prefix" />
      </div>

      <input
        :id="inputId"
        :name="name"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        @input="onInput"
        @blur="emit('blur', $event)"
        @focus="emit('focus', $event)"
        class="w-full rounded-xl border transition-all text-slate-800 placeholder-slate-400 bg-white focus:outline-hidden disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed"
        :class="[
          sizeClasses,
          $slots.prefix ? 'pl-9' : '',
          $slots.suffix ? 'pr-9' : '',
          error
            ? 'border-rose-400 focus:border-rose-600 focus:ring-2 focus:ring-rose-500/20'
            : 'border-slate-300 hover:border-slate-400 focus:border-brand-700 focus:ring-2 focus:ring-brand-700/20'
        ]"
      />

      <div v-if="$slots.suffix" class="absolute right-3 flex items-center text-slate-400">
        <slot name="suffix" />
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
