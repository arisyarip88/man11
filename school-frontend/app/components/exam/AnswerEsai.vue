<script setup lang="ts">
const props = defineProps<{ question: any; modelValue: any }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: string): void }>()
</script>

<template>
  <div class="mt-6">
    <label class="block font-bold text-xs text-slate-700 mb-1">Tulis Jawaban Uraian Anda di sini:</label>
    <textarea
      :value="modelValue || ''"
      rows="6"
      placeholder="Ketik uraian jawaban secara jelas dan terperinci..."
      class="w-full p-4 rounded-2xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm"
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    ></textarea>
  </div>
</template>
