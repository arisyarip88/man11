<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

definePageMeta({ layout: 'student', middleware: 'auth' })

const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'

interface AttendanceRecord {
  id: number
  meet_id: number
  status: 'H' | 'S' | 'I' | 'A'
  notes: string | null
  recorded_at: string | null
  meeting_title: string
  meeting_number: number
  subject_name: string
  class_name: string
}

interface AttendanceSummaryData {
  student: {
    id: number
    name: string
    nis: string
    nisn: string
  }
  stats: {
    total: number
    H: number
    S: number
    I: number
    A: number
  }
  attendance_rate: number
  records: AttendanceRecord[]
}

const loading = ref(true)
const summaryData = ref<AttendanceSummaryData | null>(null)
const search = ref('')
const filterStatus = ref('all') // 'all', 'H', 'S', 'I', 'A'

const api = async <T>(path: string) => {
  return await $fetch<T>(`${apiBase}${path}`, {
    headers: {
      Authorization: `Bearer ${token.value}`,
      Accept: 'application/json',
    },
  })
}

const fetchAttendanceSummary = async () => {
  loading.value = true
  try {
    const res: any = await api('/student/attendance-summary')
    if (res?.data) {
      summaryData.value = res.data
    }
  } catch (err) {
    console.error('Gagal mengambil rekap presensi siswa:', err)
  } finally {
    loading.value = false
  }
}

const records = computed(() => summaryData.value?.records || [])

const filteredRecords = computed(() => {
  return records.value.filter(r => {
    // Status filter
    if (filterStatus.value !== 'all' && r.status !== filterStatus.value) return false

    // Search query
    if (!search.value.trim()) return true
    const q = search.value.toLowerCase()
    const mapelMatch = (r.subject_name || '').toLowerCase().includes(q)
    const titleMatch = (r.meeting_title || '').toLowerCase().includes(q)
    return mapelMatch || titleMatch
  })
})

const stats = computed(() => summaryData.value?.stats || { total: 0, H: 0, S: 0, I: 0, A: 0 })
const attendanceRate = computed(() => summaryData.value?.attendance_rate || 100)

onMounted(() => {
  fetchAttendanceSummary()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Top Back Link -->
    <NuxtLink to="/student/dashboard" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-emerald-700 transition">
      <span>←</span>
      <span>Kembali ke Dashboard Belajar Siswa</span>
    </NuxtLink>

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
            Semester Berjalan
          </span>
          <span class="text-xs text-slate-400">Rekap Presensi Tatap Muka & Daring</span>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">Rekap Kehadiran Siswa</h1>
        <p class="text-xs text-slate-500 mt-1">
          Pantau persentase dan riwayat presensi mandiri Anda di seluruh mata pelajaran.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchAttendanceSummary"
          :disabled="loading"
          class="px-4 py-2 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-semibold shadow-xs transition flex items-center gap-1.5"
        >
          <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': loading }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Segarkan Data
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-5 gap-4 animate-pulse">
      <div v-for="i in 5" :key="i" class="h-28 bg-white rounded-2xl border border-slate-100 p-5"></div>
    </div>

    <template v-else>
      <!-- Stats Cards -->
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <!-- Attendance Rate Card (Spans on mobile or first card) -->
        <div class="col-span-2 md:col-span-1 bg-gradient-to-br from-emerald-600 to-teal-700 text-white p-5 rounded-2xl shadow-sm space-y-1">
          <p class="text-[11px] font-bold uppercase opacity-80">Persentase Hadir</p>
          <p class="text-3xl font-black">{{ attendanceRate }}%</p>
          <p class="text-[11px] opacity-90">{{ stats.H }} dari {{ stats.total }} pertemuan</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-emerald-100 shadow-xs space-y-1">
          <p class="text-[11px] font-bold uppercase text-emerald-600">Hadir (H)</p>
          <p class="text-3xl font-black text-slate-800">{{ stats.H }}</p>
          <p class="text-[11px] text-slate-400">Pertemuan</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-amber-100 shadow-xs space-y-1">
          <p class="text-[11px] font-bold uppercase text-amber-600">Sakit (S)</p>
          <p class="text-3xl font-black text-slate-800">{{ stats.S }}</p>
          <p class="text-[11px] text-slate-400">Pertemuan</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-sky-100 shadow-xs space-y-1">
          <p class="text-[11px] font-bold uppercase text-sky-600">Izin (I)</p>
          <p class="text-3xl font-black text-slate-800">{{ stats.I }}</p>
          <p class="text-[11px] text-slate-400">Pertemuan</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-rose-100 shadow-xs space-y-1">
          <p class="text-[11px] font-bold uppercase text-rose-600">Alpa (A)</p>
          <p class="text-3xl font-black text-slate-800">{{ stats.A }}</p>
          <p class="text-[11px] text-slate-400">Tanpa keterangan</p>
        </div>
      </div>

      <!-- Filter & Search Controls -->
      <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-slate-100 shadow-xs">
        <div class="flex items-center gap-1.5 bg-slate-100 p-1 rounded-xl text-xs font-bold w-full sm:w-auto overflow-x-auto">
          <button
            type="button"
            @click="filterStatus = 'all'"
            class="px-3 py-1.5 rounded-lg transition"
            :class="filterStatus === 'all' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
          >
            Semua ({{ records.length }})
          </button>
          <button
            type="button"
            @click="filterStatus = 'H'"
            class="px-3 py-1.5 rounded-lg transition"
            :class="filterStatus === 'H' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-500 hover:text-emerald-700'"
          >
            Hadir ({{ stats.H }})
          </button>
          <button
            type="button"
            @click="filterStatus = 'S'"
            class="px-3 py-1.5 rounded-lg transition"
            :class="filterStatus === 'S' ? 'bg-amber-500 text-white shadow-xs' : 'text-slate-500 hover:text-amber-700'"
          >
            Sakit ({{ stats.S }})
          </button>
          <button
            type="button"
            @click="filterStatus = 'I'"
            class="px-3 py-1.5 rounded-lg transition"
            :class="filterStatus === 'I' ? 'bg-sky-500 text-white shadow-xs' : 'text-slate-500 hover:text-sky-700'"
          >
            Izin ({{ stats.I }})
          </button>
          <button
            type="button"
            @click="filterStatus = 'A'"
            class="px-3 py-1.5 rounded-lg transition"
            :class="filterStatus === 'A' ? 'bg-rose-500 text-white shadow-xs' : 'text-slate-500 hover:text-rose-700'"
          >
            Alpa ({{ stats.A }})
          </button>
        </div>

        <div class="relative w-full sm:w-72">
          <input
            v-model="search"
            type="text"
            placeholder="Cari mata pelajaran atau materi..."
            class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none transition"
          />
          <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
      </div>

      <!-- Attendance Records Table -->
      <div v-if="filteredRecords.length === 0" class="bg-white rounded-3xl border border-slate-100 p-12 text-center space-y-3">
        <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
        <h3 class="text-sm font-bold text-slate-800">Belum ada catatan presensi</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto">
          Tidak ada riwayat presensi yang sesuai dengan filter pencarian ini.
        </p>
      </div>

      <div v-else class="bg-white rounded-3xl border border-slate-100 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-100 text-slate-500 uppercase tracking-wider text-[11px]">
                <th class="py-3.5 px-4 font-bold">Mata Pelajaran & Kelas</th>
                <th class="py-3.5 px-4 font-bold">Pertemuan</th>
                <th class="py-3.5 px-4 font-bold text-center">Status</th>
                <th class="py-3.5 px-4 font-bold">Keterangan / Catatan</th>
                <th class="py-3.5 px-4 font-bold">Waktu Dicatat</th>
                <th class="py-3.5 px-4 font-bold text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="r in filteredRecords" :key="r.id" class="hover:bg-slate-50/70 transition">
                <td class="py-3.5 px-4">
                  <div class="font-bold text-slate-900">{{ r.subject_name }}</div>
                  <div class="text-[11px] text-slate-400 font-medium">{{ r.class_name }}</div>
                </td>
                <td class="py-3.5 px-4 font-medium text-slate-700">
                  <div>Pertemuan {{ r.meeting_number || '-' }}</div>
                  <div class="text-[11px] text-slate-500 font-normal line-clamp-1">{{ r.meeting_title }}</div>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span
                    class="px-3 py-1 rounded-full text-[10px] font-black"
                    :class="{
                      'bg-emerald-100 text-emerald-800': r.status === 'H',
                      'bg-amber-100 text-amber-800': r.status === 'S',
                      'bg-sky-100 text-sky-800': r.status === 'I',
                      'bg-rose-100 text-rose-800': r.status === 'A'
                    }"
                  >
                    {{ r.status === 'H' ? 'HADIR' : r.status === 'S' ? 'SAKIT' : r.status === 'I' ? 'IZIN' : 'ALPA' }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-slate-600">
                  {{ r.notes || '-' }}
                </td>
                <td class="py-3.5 px-4 font-mono text-[11px] text-slate-400">
                  {{ r.recorded_at || '-' }}
                </td>
                <td class="py-3.5 px-4 text-right">
                  <NuxtLink
                    :to="`/elearning/meets/${r.meet_id}?tab=attendance`"
                    class="px-3 py-1.5 bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 text-slate-600 rounded-lg text-[11px] font-semibold transition inline-block"
                  >
                    Buka Pertemuan ➔
                  </NuxtLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
