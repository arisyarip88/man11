<script setup lang="ts">
const props = defineProps<{ question: any; modelValue: any }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: string): void }>()
</script>

<template>
  <div class="mt-6 space-y-3">
    <button
      v-for="opt in question.options"
      :key="opt.key"
      type="button"
      :class="[
        'w-full text-left p-4 rounded-2xl border transition flex items-center gap-3.5',
        modelValue === opt.key
          ? 'border-emerald-600 bg-emerald-50 text-emerald-950 font-bold ring-2 ring-emerald-500/20'
          : 'border-slate-200 bg-white hover:bg-slate-50 text-slate-700'
      ]"
      @click="emit('update:modelValue', opt.key)"
    >
      <span
        :class="[
          'w-7 h-7 rounded-xl font-mono text-xs font-extrabold flex items-center justify-center shrink-0',
          modelValue === opt.key ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'
        ]"
      >{{ opt.key }}</span>
      <span class="text-sm leading-relaxed">{{ opt.text }}</span>
    </button>
  </div>
</template>
