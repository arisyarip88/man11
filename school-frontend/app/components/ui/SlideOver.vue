<script setup lang="ts">
import { onMounted, onUnmounted, watch } from 'vue'

const props = withDefaults(defineProps<{
  modelValue: boolean
  title?: string
  subtitle?: string
  width?: string
}>(), {
  modelValue: false,
  title: '',
  subtitle: '',
  width: 'max-w-xl'
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'close'): void
}>()

const close = () => {
  emit('update:modelValue', false)
  emit('close')
}

const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && props.modelValue) {
    close()
  }
}

watch(() => props.modelValue, (isOpen) => {
  if (typeof document !== 'undefined') {
    if (isOpen) {
      document.body.style.overflow = 'hidden'
    } else {
      document.body.style.overflow = ''
    }
  }
})

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleKeydown)
  }
})

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', handleKeydown)
    document.body.style.overflow = ''
  }
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="fixed inset-0 z-50 overflow-hidden"
      role="dialog"
      aria-modal="true"
    >
      <!-- Backdrop -->
      <div
        class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity duration-300"
        @click="close"
      />

      <!-- Slide Panel Container -->
      <div class="fixed inset-y-0 right-0 flex max-w-full pl-6 sm:pl-10">
        <div
          class="w-screen flex flex-col bg-white shadow-2xl border-l border-slate-200 transition-transform duration-300 ease-in-out"
          :class="width"
        >
          <!-- Header -->
          <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
            <div>
              <h3 class="text-base font-bold text-slate-900">
                {{ title }}
              </h3>
              <p v-if="subtitle" class="mt-0.5 text-xs text-slate-500">
                {{ subtitle }}
              </p>
            </div>
            <button
              type="button"
              class="rounded-xl p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 transition"
              title="Tutup (ESC)"
              @click="close"
            >
              <span class="sr-only">Tutup</span>
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto px-6 py-6">
            <slot />
          </div>

          <!-- Footer (Optional) -->
          <div v-if="$slots.footer" class="border-t border-slate-100 bg-slate-50/90 px-6 py-4 flex items-center justify-end gap-3">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
