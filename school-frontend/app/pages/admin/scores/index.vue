<script setup>
import { ref, onMounted, watch } from 'vue'

definePageMeta({
  layout: 'admin'
})

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie('auth_token')

const scoresList = ref([])
const rombelList = ref([])
const selectedRombel = ref('')
const search = ref('')
const loading = ref(false)

const fetchRombelOptions = async () => {
  try {
    const res = await $fetch(`${apiBase}/rombel?paginate=false`, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    rombelList.value = res.data || res || []
  } catch (err) {
    console.error('Fetch rombel options error:', err)
  }
}

const fetchScoresReport = async () => {
  loading.value = true
  try {
    const res = await $fetch(`${apiBase}/scores`, {
      params: {
        class_id: selectedRombel.value,
        search: search.value
      },
      headers: { Authorization: `Bearer ${token.value}` }
    })
    scoresList.value = res.data || []
  } catch (err) {
    console.error('Fetch scores error:', err)
  } finally {
    loading.value = false
  }
}

watch([selectedRombel, search], () => {
  fetchScoresReport()
})

onMounted(async () => {
  await fetchRombelOptions()
  fetchScoresReport()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Rekapitulasi Nilai Siswa (Scores)</h1>
        <p class="text-sm text-gray-500">Pantau rata-rata nilai kuis, akumulasi tugas, dan status kelulusan siswa</p>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex flex-col md:flex-row gap-4 justify-between items-center">
      <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
        <div class="relative w-full sm:w-64">
          <input 
            v-model="search"
            type="text" 
            placeholder="Cari nama atau NISN siswa..."
            class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
          >
          <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <select 
          v-model="selectedRombel"
          class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
        >
          <option value="">-- Semua Rombel --</option>
          <option v-for="rmb in rombelList" :key="rmb.id" :value="rmb.id">
            {{ rmb.class_name?.name }}
          </option>
        </select>
      </div>

      <div class="text-xs text-gray-500">
        Total Siswa: <span class="font-bold text-gray-700">{{ scoresList.length }}</span> Siswa
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
              <th class="py-4 px-6">NISN</th>
              <th class="py-4 px-6">Nama Siswa</th>
              <th class="py-4 px-6">Tugas Dikirim</th>
              <th class="py-4 px-6">Sudah Dinilai</th>
              <th class="py-4 px-6">Rata-rata Nilai</th>
              <th class="py-4 px-6">Status Rekap</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
            <tr v-if="loading && scoresList.length === 0">
              <td colspan="6" class="py-8 text-center text-gray-400">Memuat rekapitulasi nilai...</td>
            </tr>
            <tr v-else-if="scoresList.length === 0">
              <td colspan="6" class="py-8 text-center text-gray-400">Tidak ada data rekapitulasi nilai ditemukan.</td>
            </tr>
            <tr v-for="item in scoresList" :key="item.student_id" class="hover:bg-gray-50/50 transition">
              <td class="py-4 px-6 font-mono text-gray-500">{{ item.nisn }}</td>
              <td class="py-4 px-6 font-bold text-gray-900">{{ item.name }}</td>
              <td class="py-4 px-6 font-medium text-gray-700">{{ item.total_submissions }} Tugas</td>
              <td class="py-4 px-6 text-gray-600">{{ item.graded_submissions }} Tugas</td>
              <td class="py-4 px-6">
                <span class="text-base font-extrabold" :class="item.average_score >= 75 ? 'text-emerald-600' : 'text-amber-600'">
                  {{ item.average_score }}
                </span>
              </td>
              <td class="py-4 px-6">
                <span 
                  class="px-3 py-1 text-xs font-bold rounded-full"
                  :class="{
                    'bg-emerald-100 text-emerald-700': item.grade_status === 'Lulus',
                    'bg-amber-100 text-amber-700': item.grade_status === 'Perlu Remidi',
                    'bg-gray-100 text-gray-600': item.grade_status === 'Belum Ada Nilai'
                  }"
                >
                  {{ item.grade_status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
