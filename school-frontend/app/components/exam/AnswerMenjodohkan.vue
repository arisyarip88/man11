<script setup lang="ts">
const props = defineProps<{ question: any; modelValue: any }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: Record<string, string>): void }>()

const value = (id: string) => (props.modelValue && props.modelValue[id]) || ''

const setPair = (id: string, choice: string) => {
  const next: Record<string, string> = { ...(props.modelValue || {}) }
  if (choice) next[id] = choice
  else delete next[id]
  emit('update:modelValue', next)
}
</script>

<template>
  <div class="mt-6 space-y-3">
    <p class="text-xs font-semibold text-slate-500">Pasangkan setiap pernyataan di kiri dengan pilihan yang tepat.</p>
    <div
      v-for="pair in question.pairs"
      :key="pair.id"
      class="grid grid-cols-1 sm:grid-cols-2 gap-2 items-center p-3 rounded-2xl border border-slate-200 bg-white"
    >
      <span class="text-sm font-semibold text-slate-800">{{ pair.left }}</span>
      <select
        :value="value(pair.id)"
        class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
        @change="setPair(pair.id, ($event.target as HTMLSelectElement).value)"
      >
        <option value="">Pilih pasangan...</option>
        <option v-for="choice in question.choices" :key="choice" :value="choice">{{ choice }}</option>
      </select>
    </div>
  </div>
</template>
