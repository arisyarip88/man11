<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'

definePageMeta({
  layout: 'teacher',
  middleware: 'auth'
})

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie<string | null>('auth_token')

const classes = ref<any[]>([])
const selectedRombelId = ref<string>('')
const selectedMonth = ref<string>('')
const searchQuery = ref<string>('')
const loading = ref(true)
const dataLoading = ref(false)

const rombelData = ref<any>(null)
const summaryData = ref<any>(null)
const meetsData = ref<any[]>([])
const studentsData = ref<any[]>([])

const selectedStudent = ref<any>(null)
const showDetailModal = ref(false)

const months = [
  { value: '', label: 'Semua Bulan (Semester Ini)' },
  { value: '1', label: 'Januari' },
  { value: '2', label: 'Februari' },
  { value: '3', label: 'Maret' },
  { value: '4', label: 'April' },
  { value: '5', label: 'Mei' },
  { value: '6', label: 'Juni' },
  { value: '7', label: 'Juli' },
  { value: '8', label: 'Agustus' },
  { value: '9', label: 'September' },
  { value: '10', label: 'Oktober' },
  { value: '11', label: 'November' },
  { value: '12', label: 'Desember' },
]

// Fetch Classes
const fetchClasses = async () => {
  loading.value = true
  try {
    const res: any = await $fetch(`${apiBase}/supervision/classes`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    classes.value = res.data || res || []
    if (classes.value.length > 0) {
      selectedRombelId.value = String(classes.value[0].id)
      await fetchAttendanceData()
    }
  } catch (err) {
    console.error('Fetch supervision classes error:', err)
  } finally {
    loading.value = false
  }
}

// Fetch Attendance Data for selected rombel
const fetchAttendanceData = async () => {
  if (!selectedRombelId.value) return
  dataLoading.value = true
  try {
    let url = `${apiBase}/supervision/attendance/${selectedRombelId.value}`
    if (selectedMonth.value) {
      url += `?month=${selectedMonth.value}`
    }

    const res: any = await $fetch(url, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })

    if (res?.data) {
      rombelData.value = res.data.rombel
      summaryData.value = res.data.summary
      meetsData.value = res.data.meets || []
      studentsData.value = res.data.students || []
    }
  } catch (err) {
    console.error('Fetch attendance data error:', err)
  } finally {
    dataLoading.value = false
  }
}

watch(selectedRombelId, () => {
  fetchAttendanceData()
})

watch(selectedMonth, () => {
  fetchAttendanceData()
})

// Filtered Students
const filteredStudents = computed(() => {
  if (!searchQuery.value.trim()) return studentsData.value
  const q = searchQuery.value.toLowerCase()
  return studentsData.value.filter(s =>
    (s.name && s.name.toLowerCase().includes(q)) ||
    (s.nisn && s.nisn.toLowerCase().includes(q))
  )
})

// Export CSV
const exportCsv = () => {
  if (studentsData.value.length === 0) return

  const headers = ['No', 'NISN', 'Nama Siswa', 'L/P', 'Total Sesi', 'Hadir (H)', 'Sakit (S)', 'Izin (I)', 'Alpa (A)', 'Persentase Kehadiran (%)', 'Status']
  const rows = studentsData.value.map((s, idx) => [
    idx + 1,
    `="${s.nisn || ''}"`,
    `"${s.name}"`,
    s.gender || '-',
    s.total_meets,
    s.H,
    s.S,
    s.I,
    s.A,
    s.percentage,
    s.is_critical ? 'Perlu Perhatian' : 'Aman'
  ])

  const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n')
  const encodedUri = encodeURI(csvContent)
  const link = document.createElement('a')
  link.setAttribute('href', encodedUri)
  link.setAttribute('download', `Rekap_Presensi_${rombelData.value?.class_name || 'Rombel'}_${new Date().toISOString().substring(0, 10)}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

const openStudentDetail = (s: any) => {
  selectedStudent.value = s
  showDetailModal.value = true
}

onMounted(() => {
  fetchClasses()
})
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
      <!-- Breadcrumb -->
      <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <NuxtLink to="/teacher/dashboard" class="hover:text-emerald-700 transition">Dashboard</NuxtLink>
        <span>/</span>
        <span class="text-slate-400">Supervisi Wali Kelas</span>
        <span>/</span>
        <span class="text-slate-800">Presensi Kehadiran</span>
      </nav>

      <!-- Header Section -->
      <div class="mt-3 flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
          <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800 border border-emerald-200/60">
            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
            Supervisi Rombel Binaan
          </div>
          <h1 class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
            Rekap Presensi Rombel
          </h1>
          <p class="mt-1 text-xs sm:text-sm text-slate-500">
            Monitoring rekapitulasi kehadiran seluruh siswa sekelas untuk deteksi dini ketidakhadiran siswa.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="exportCsv"
            :disabled="studentsData.length === 0"
            class="inline-flex items-center justify-center gap-1.5 rounded-2xl bg-white border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-xs transition disabled:opacity-50"
          >
            <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Ekspor CSV
          </button>

          <NuxtLink
            to="/teacher/supervision/ledger"
            class="inline-flex items-center justify-center gap-1.5 rounded-2xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition"
          >
            Buka Ledger Nilai →
          </NuxtLink>
        </div>
      </div>

      <!-- Controls & Filter Bar -->
      <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-4 shadow-xs">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <!-- Rombel Selector -->
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Pilih Rombel / Kelas Binaan</label>
            <select
              v-model="selectedRombelId"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            >
              <option v-for="c in classes" :key="c.id" :value="c.id">
                {{ c.class_name?.name || c.className?.name || c.name || 'Kelas' }} ({{ c.students_count || 0 }} Siswa)
              </option>
            </select>
          </div>

          <!-- Month Filter -->
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Periode Bulan</label>
            <select
              v-model="selectedMonth"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            >
              <option v-for="m in months" :key="m.value" :value="m.value">
                {{ m.label }}
              </option>
            </select>
          </div>

          <!-- Search input -->
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Cari Nama / NISN Siswa</label>
            <div class="relative">
              <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Ketik nama atau NISN..."
                class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- KPI Summary Cards -->
      <div v-if="summaryData" class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-5">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs">
          <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Siswa</p>
          <p class="mt-2 text-3xl font-black text-slate-900">{{ summaryData.total_students }}</p>
          <p class="mt-1 text-[11px] text-slate-500">{{ rombelData?.class_name }}</p>
        </div>

        <div class="rounded-3xl border border-emerald-200/80 bg-emerald-50/60 p-5 shadow-xs">
          <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Rerata Kehadiran</p>
          <p class="mt-2 text-3xl font-black text-emerald-800">{{ summaryData.average_attendance_rate }}%</p>
          <p class="mt-1 text-[11px] text-emerald-600">Standar minimal 85%</p>
        </div>

        <div class="rounded-3xl border border-blue-200/80 bg-blue-50/60 p-5 shadow-xs">
          <p class="text-[11px] font-bold uppercase tracking-wider text-blue-700">Akumulasi Hadir (H)</p>
          <p class="mt-2 text-3xl font-black text-blue-800">{{ summaryData.total_H }}</p>
          <p class="mt-1 text-[11px] text-blue-600">Presensi terisi</p>
        </div>

        <div class="rounded-3xl border border-amber-200/80 bg-amber-50/60 p-5 shadow-xs">
          <p class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Sakit / Izin (S/I)</p>
          <p class="mt-2 text-3xl font-black text-amber-800">{{ summaryData.total_S + summaryData.total_I }}</p>
          <p class="mt-1 text-[11px] text-amber-600">S: {{ summaryData.total_S }} • I: {{ summaryData.total_I }}</p>
        </div>

        <div class="rounded-3xl border border-rose-200/80 bg-rose-50/60 p-5 shadow-xs col-span-2 sm:col-span-1">
          <p class="text-[11px] font-bold uppercase tracking-wider text-rose-700">Alpa / Tanpa Keterangan</p>
          <p class="mt-2 text-3xl font-black text-rose-800">{{ summaryData.total_A }}</p>
          <p class="mt-1 text-[11px] text-rose-600">{{ summaryData.critical_students_count }} siswa perlu perhatian</p>
        </div>
      </div>

      <!-- Main Attendance Table -->
      <div class="mt-6 rounded-3xl border border-slate-200 bg-white shadow-xs overflow-hidden">
        <div v-if="dataLoading" class="p-12 text-center text-xs text-slate-400">
          Memuat rekapitulasi presensi rombel...
        </div>

        <div v-else-if="filteredStudents.length === 0" class="p-12 text-center text-xs text-slate-400">
          Tidak ada data siswa ditemukan pada rombel ini.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-100 text-slate-500 uppercase tracking-wider text-[10px]">
                <th class="py-3.5 px-4 font-bold text-center w-12 sticky left-0 bg-slate-50 z-10">No</th>
                <th class="py-3.5 px-4 font-bold w-32 sticky left-12 bg-slate-50 z-10">NISN</th>
                <th class="py-3.5 px-4 font-bold min-w-[200px] sticky left-44 bg-slate-50 z-10">Nama Lengkap Siswa</th>
                <th class="py-3.5 px-3 font-bold text-center w-24">Sesi Diikuti</th>
                <th class="py-3.5 px-3 font-bold text-center w-20 text-emerald-700">Hadir (H)</th>
                <th class="py-3.5 px-3 font-bold text-center w-20 text-amber-700">Sakit (S)</th>
                <th class="py-3.5 px-3 font-bold text-center w-20 text-sky-700">Izin (I)</th>
                <th class="py-3.5 px-3 font-bold text-center w-20 text-rose-700">Alpa (A)</th>
                <th class="py-3.5 px-4 font-bold text-center w-32">Persentase</th>
                <th class="py-3.5 px-4 font-bold text-center w-32">Status Kehadiran</th>
                <th class="py-3.5 px-4 font-bold text-center w-20">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="(s, idx) in filteredStudents"
                :key="s.student_id"
                class="hover:bg-slate-50/70 transition"
                :class="s.is_critical ? 'bg-rose-50/20' : ''"
              >
                <td class="py-3.5 px-4 text-center font-bold text-slate-400 sticky left-0 bg-white group-hover:bg-slate-50">{{ idx + 1 }}</td>
                <td class="py-3.5 px-4 font-mono text-slate-500 sticky left-12 bg-white group-hover:bg-slate-50">{{ s.nisn || '-' }}</td>
                <td class="py-3.5 px-4 font-bold text-slate-900 sticky left-44 bg-white group-hover:bg-slate-50">
                  <div class="flex items-center gap-2">
                    <span>{{ s.name }}</span>
                    <span v-if="s.gender" class="text-[9px] px-1.5 py-0.2 bg-slate-100 text-slate-500 rounded font-semibold">{{ s.gender }}</span>
                  </div>
                </td>
                <td class="py-3.5 px-3 text-center text-slate-600 font-semibold">{{ s.total_meets }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-emerald-700 bg-emerald-50/40">{{ s.H }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-amber-700 bg-amber-50/40">{{ s.S }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-sky-700 bg-sky-50/40">{{ s.I }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-rose-700 bg-rose-50/40">{{ s.A }}</td>
                <td class="py-3.5 px-4 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <div class="w-16 h-2 bg-slate-100 rounded-full overflow-hidden">
                      <div
                        class="h-full rounded-full transition-all"
                        :class="s.percentage >= 85 ? 'bg-emerald-500' : s.percentage >= 75 ? 'bg-amber-500' : 'bg-rose-500'"
                        :style="{ width: `${s.percentage}%` }"
                      ></div>
                    </div>
                    <span class="font-extrabold text-[11px]" :class="s.percentage >= 85 ? 'text-emerald-700' : 'text-rose-700'">
                      {{ s.percentage }}%
                    </span>
                  </div>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span
                    class="inline-block px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"
                    :class="s.is_critical ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200'"
                  >
                    {{ s.is_critical ? 'Perlu Perhatian' : 'Aman' }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <button
                    @click="openStudentDetail(s)"
                    class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition"
                    title="Detail Presensi Siswa"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL DETAIL SISWA -->
    <div v-if="showDetailModal && selectedStudent" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs overflow-y-auto">
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 my-8 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Rincian Kehadiran Siswa</span>
            <h3 class="text-lg font-extrabold text-slate-900 mt-0.5">{{ selectedStudent.name }}</h3>
            <p class="text-xs text-slate-400">NISN: {{ selectedStudent.nisn || '-' }}</p>
          </div>
          <button @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-4 gap-2 text-center text-xs">
          <div class="p-2.5 rounded-xl bg-emerald-50 text-emerald-800 font-bold border border-emerald-100">
            <span class="block text-[10px] uppercase">Hadir</span>
            <span class="text-lg">{{ selectedStudent.H }}</span>
          </div>
          <div class="p-2.5 rounded-xl bg-amber-50 text-amber-800 font-bold border border-amber-100">
            <span class="block text-[10px] uppercase">Sakit</span>
            <span class="text-lg">{{ selectedStudent.S }}</span>
          </div>
          <div class="p-2.5 rounded-xl bg-sky-50 text-sky-800 font-bold border border-sky-100">
            <span class="block text-[10px] uppercase">Izin</span>
            <span class="text-lg">{{ selectedStudent.I }}</span>
          </div>
          <div class="p-2.5 rounded-xl bg-rose-50 text-rose-800 font-bold border border-rose-100">
            <span class="block text-[10px] uppercase">Alpa</span>
            <span class="text-lg">{{ selectedStudent.A }}</span>
          </div>
        </div>

        <!-- Meet List -->
        <div>
          <h4 class="text-xs font-bold text-slate-800 mb-2">Riwayat Pertemuan:</h4>
          <div class="max-h-60 overflow-y-auto space-y-2 pr-1">
            <div
              v-for="m in selectedStudent.meet_details"
              :key="m.meet_id"
              class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-between text-xs"
            >
              <div>
                <p class="font-bold text-slate-800">Pertemuan {{ m.meeting_number }}: {{ m.subject }}</p>
                <p v-if="m.notes" class="text-[11px] text-slate-500 mt-0.5">Catatan: {{ m.notes }}</p>
              </div>
              <span
                class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase"
                :class="{
                  'bg-emerald-100 text-emerald-800': m.status === 'H',
                  'bg-amber-100 text-amber-800': m.status === 'S',
                  'bg-sky-100 text-sky-800': m.status === 'I',
                  'bg-rose-100 text-rose-800': m.status === 'A'
                }"
              >
                {{ m.status === 'H' ? 'Hadir' : m.status === 'S' ? 'Sakit' : m.status === 'I' ? 'Izin' : 'Alpa' }}
              </span>
            </div>
          </div>
        </div>

        <div class="flex justify-end pt-3 border-t border-slate-100">
          <button
            @click="showDetailModal = false"
            class="px-5 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs hover:bg-slate-200 transition"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  </main>
</template>
