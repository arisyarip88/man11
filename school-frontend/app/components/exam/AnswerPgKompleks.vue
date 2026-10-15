<script setup lang="ts">
const props = defineProps<{ question: any; modelValue: any }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: string[]): void }>()

const selected = (key: string) => Array.isArray(props.modelValue) && props.modelValue.includes(key)

const toggle = (key: string) => {
  const current: string[] = Array.isArray(props.modelValue) ? [...props.modelValue] : []
  const idx = current.indexOf(key)
  if (idx >= 0) current.splice(idx, 1)
  else current.push(key)
  emit('update:modelValue', current.sort())
}
</script>

<template>
  <div class="mt-6 space-y-3">
    <p class="text-xs font-semibold text-slate-500">Pilih semua jawaban yang benar.</p>
    <button
      v-for="opt in question.options"
      :key="opt.key"
      type="button"
      :class="[
        'w-full text-left p-4 rounded-2xl border transition flex items-center gap-3.5',
        selected(opt.key)
          ? 'border-emerald-600 bg-emerald-50 text-emerald-950 font-bold ring-2 ring-emerald-500/20'
          : 'border-slate-200 bg-white hover:bg-slate-50 text-slate-700'
      ]"
      @click="toggle(opt.key)"
    >
      <span
        :class="[
          'w-7 h-7 rounded-lg border-2 text-xs font-extrabold flex items-center justify-center shrink-0',
          selected(opt.key) ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-slate-300 text-transparent'
        ]"
      >✓</span>
      <span class="text-xs font-mono font-extrabold text-slate-500">{{ opt.key }}</span>
      <span class="text-sm leading-relaxed">{{ opt.text }}</span>
    </button>
  </div>
</template>
