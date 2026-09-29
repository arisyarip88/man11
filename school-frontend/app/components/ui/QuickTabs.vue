<script setup lang="ts">
export interface TabItem {
  id: string
  label: string
  icon?: string
  badge?: string | number
  badgeColor?: string
}

const props = defineProps<{
  items: TabItem[]
  modelValue: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const selectTab = (id: string) => {
  emit('update:modelValue', id)
}
</script>

<template>
  <div class="flex items-center gap-1.5 p-1.5 bg-slate-100/80 rounded-2xl border border-slate-200/60 overflow-x-auto no-scrollbar">
    <button
      v-for="item in items"
      :key="item.id"
      type="button"
      class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
      :class="modelValue === item.id
        ? 'bg-white text-slate-900 shadow-sm border border-slate-200/80'
        : 'text-slate-600 hover:text-slate-900 hover:bg-white/60'"
      @click="selectTab(item.id)"
    >
      <span v-if="item.icon" class="text-sm leading-none">{{ item.icon }}</span>
      <span>{{ item.label }}</span>
      <span
        v-if="item.badge !== undefined && item.badge !== null && item.badge !== ''"
        class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-extrabold"
        :class="item.badgeColor || (modelValue === item.id ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700')"
      >
        {{ item.badge }}
      </span>
    </button>
  </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
