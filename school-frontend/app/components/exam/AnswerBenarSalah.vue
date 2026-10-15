<script setup lang="ts">
const props = defineProps<{ question: any; modelValue: any }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: Record<string, boolean>): void }>()

const current = (id: string): boolean | null => {
  const v = props.modelValue ? props.modelValue[id] : undefined
  return v === undefined ? null : v
}

const setValue = (id: string, value: boolean) => {
  emit('update:modelValue', { ...(props.modelValue || {}), [id]: value })
}
</script>

<template>
  <div class="mt-6 space-y-3">
    <p class="text-xs font-semibold text-slate-500">Tentukan setiap pernyataan Benar atau Salah.</p>
    <div
      v-for="st in question.statements"
      :key="st.id"
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-2xl border border-slate-200 bg-white"
    >
      <span class="text-sm text-slate-800 leading-relaxed">{{ st.text }}</span>
      <div class="flex gap-2 shrink-0">
        <button
          type="button"
          :class="[
            'px-4 py-2 rounded-xl text-xs font-bold border transition',
            current(st.id) === true
              ? 'bg-emerald-600 border-emerald-600 text-white'
              : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
          ]"
          @click="setValue(st.id, true)"
        >Benar</button>
        <button
          type="button"
          :class="[
            'px-4 py-2 rounded-xl text-xs font-bold border transition',
            current(st.id) === false
              ? 'bg-rose-600 border-rose-600 text-white'
              : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
          ]"
          @click="setValue(st.id, false)"
        >Salah</button>
      </div>
    </div>
  </div>
</template>
