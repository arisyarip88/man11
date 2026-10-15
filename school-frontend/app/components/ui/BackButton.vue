<script setup lang="ts">
import { useRouter } from 'vue-router'

const props = withDefaults(
  defineProps<{
    to?: string
    fallback?: string
    label?: string
    showIcon?: boolean
  }>(),
  {
    to: '',
    fallback: '/',
    label: 'Kembali',
    showIcon: true,
  }
)

const router = useRouter()

const handleBack = () => {
  if (props.to) {
    router.push(props.to)
    return
  }

  // Cek apakah ada history di browser yang valid
  if (typeof window !== 'undefined' && window.history.length > 2) {
    router.back()
  } else {
    router.push(props.fallback)
  }
}
</script>

<template>
  <button
    type="button"
    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200/80 bg-white/80 hover:bg-slate-100/80 text-slate-700 text-xs font-bold transition shadow-2xs backdrop-blur-xs group"
    @click="handleBack"
  >
    <span v-if="showIcon" class="text-slate-400 group-hover:-translate-x-0.5 transition-transform duration-150">←</span>
    <span>{{ label }}</span>
  </button>
</template>
