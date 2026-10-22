<script setup lang="ts">
import AppSkeleton from './AppSkeleton.vue'
import AppEmptyState from './AppEmptyState.vue'

export interface TableColumn {
  key: string
  label: string
  class?: string
  headerClass?: string
  align?: 'left' | 'center' | 'right'
}

withDefaults(defineProps<{
  columns: TableColumn[]
  rows: any[]
  loading?: boolean
  emptyTitle?: string
  emptyDescription?: string
  rowKey?: string
}>(), {
  loading: false,
  emptyTitle: 'Tidak ada data',
  emptyDescription: 'Belum ada data untuk ditampilkan saat ini.',
  rowKey: 'id'
})
</script>

<template>
  <div class="w-full overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-2xs">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs sm:text-sm">
        <thead class="bg-slate-50 border-b border-slate-200 text-slate-700 font-bold uppercase tracking-wider text-[11px]">
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              scope="col"
              class="py-3 px-4 font-bold"
              :class="[
                col.headerClass || '',
                col.align === 'center' ? 'text-center' : col.align === 'right' ? 'text-right' : 'text-left'
              ]"
            >
              {{ col.label }}
            </th>
          </tr>
        </thead>

        <tbody v-if="loading" class="divide-y divide-slate-100">
          <tr v-for="i in 5" :key="i">
            <td v-for="col in columns" :key="col.key" class="py-3 px-4">
              <AppSkeleton variant="text" width="75%" />
            </td>
          </tr>
        </tbody>

        <tbody v-else-if="rows.length > 0" class="divide-y divide-slate-100 text-slate-800">
          <tr
            v-for="(row, idx) in rows"
            :key="row[rowKey] ?? idx"
            class="hover:bg-slate-50/80 transition-colors"
          >
            <td
              v-for="col in columns"
              :key="col.key"
              class="py-3 px-4"
              :class="[
                col.class || '',
                col.align === 'center' ? 'text-center' : col.align === 'right' ? 'text-right' : 'text-left'
              ]"
            >
              <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]" :index="idx">
                {{ row[col.key] ?? '-' }}
              </slot>
            </td>
          </tr>
        </tbody>

        <tbody v-else>
          <tr>
            <td :colspan="columns.length" class="py-10 px-4">
              <AppEmptyState
                :title="emptyTitle"
                :description="emptyDescription"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="$slots.footer" class="p-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
      <slot name="footer" />
    </div>
  </div>
</template>
