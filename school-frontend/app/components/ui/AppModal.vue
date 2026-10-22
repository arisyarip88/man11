<script setup lang="ts">
import { computed, watch } from 'vue'

const props = withDefaults(defineProps<{
  modelValue: boolean
  title?: string
  description?: string
  maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '4xl'
  persistent?: boolean
}>(), {
  title: '',
  description: '',
  maxWidth: 'md',
  persistent: false
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'close'): void
}>()

const maxWidthClass = computed(() => {
  switch (props.maxWidth) {
    case 'sm':
      return 'max-w-sm'
    case 'lg':
      return 'max-w-lg'
    case 'xl':
      return 'max-w-xl'
    case '2xl':
      return 'max-w-2xl'
    case '4xl':
      return 'max-w-4xl'
    case 'md':
    default:
      return 'max-w-md'
  }
})

const close = () => {
  emit('update:modelValue', false)
  emit('close')
}

const onBackdropClick = () => {
  if (!props.persistent) {
    close()
  }
}

watch(() => props.modelValue, (isOpen) => {
  if (typeof document === 'undefined') return
  if (isOpen) {
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = ''
  }
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/50 backdrop-blur-xs flex items-center justify-center p-4 sm:p-6"
        @click="onBackdropClick"
      >
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-1"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-95 translate-y-1"
        >
          <div
            v-if="modelValue"
            class="w-full bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden flex flex-col"
            :class="maxWidthClass"
            @click.stop
            role="dialog"
            aria-modal="true"
          >
            <!-- Header -->
            <div class="px-5 py-4 border-b border-slate-100 flex items-start justify-between gap-3">
              <div>
                <slot name="title">
                  <h3 class="text-base font-bold text-slate-900 leading-snug">
                    {{ title }}
                  </h3>
                </slot>
                <p v-if="description" class="text-xs text-slate-500 mt-0.5">
                  {{ description }}
                </p>
              </div>

              <button
                type="button"
                @click="close"
                class="text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg p-1.5 transition -mr-1"
                aria-label="Tutup dialog"
              >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Body -->
            <div class="px-5 py-4 overflow-y-auto max-h-[calc(85vh-130px)]">
              <slot />
            </div>

            <!-- Footer -->
            <div v-if="$slots.footer" class="px-5 py-3.5 bg-slate-50/70 border-t border-slate-100 flex items-center justify-end gap-2.5">
              <slot name="footer" />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
