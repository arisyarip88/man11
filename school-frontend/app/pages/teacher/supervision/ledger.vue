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
const searchQuery = ref<string>('')
const loading = ref(true)
const dataLoading = ref(false)

const rombelData = ref<any>(null)
const subjectsData = ref<any[]>([])
const subjectStats = ref<any>({})
const studentsData = ref<any[]>([])
const classSummary = ref<any>(null)

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
      await fetchLedgerData()
    }
  } catch (err) {
    console.error('Fetch classes error:', err)
  } finally {
    loading.value = false
  }
}

// Fetch Ledger Data
const fetchLedgerData = async () => {
  if (!selectedRombelId.value) return
  dataLoading.value = true
  try {
    const res: any = await $fetch(`${apiBase}/supervision/ledger/${selectedRombelId.value}`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })

    if (res?.data) {
      rombelData.value = res.data.rombel
      subjectsData.value = res.data.subjects || []
      subjectStats.value = res.data.subject_stats || {}
      studentsData.value = res.data.students || []
      classSummary.value = res.data.class_summary || null
    }
  } catch (err) {
    console.error('Fetch ledger data error:', err)
  } finally {
    dataLoading.value = false
  }
}

watch(selectedRombelId, () => {
  fetchLedgerData()
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

  const subjectHeaders = subjectsData.value.map(s => `"${s.subject_name}"`)
  const headers = ['Peringkat', 'NISN', 'Nama Siswa', ...subjectHeaders, 'Total Nilai', 'Rata-rata', 'Status KKTP']

  const rows = studentsData.value.map(s => {
    const scores = subjectsData.value.map(subj => s.subject_scores[subj.course_id] ?? 0)
    return [
      s.rank,
      `="${s.nisn || ''}"`,
      `"${s.name}"`,
      ...scores,
      s.total_score,
      s.average_score,
      `"${s.status_kktp}"`
    ]
  })

  const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n')
  const encodedUri = encodeURI(csvContent)
  const link = document.createElement('a')
  link.setAttribute('href', encodedUri)
  link.setAttribute('download', `Ledger_Nilai_${rombelData.value?.class_name || 'Rombel'}_${new Date().toISOString().substring(0, 10)}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

const printLedger = () => {
  window.print()
}

onMounted(() => {
  fetchClasses()
})
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8 print:p-0 print:bg-white">
    <div class="mx-auto max-w-[1600px]">
      <!-- Breadcrumb (Hide in Print) -->
      <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500 print:hidden">
        <NuxtLink to="/teacher/dashboard" class="hover:text-emerald-700 transition">Dashboard</NuxtLink>
        <span>/</span>
        <span class="text-slate-400">Supervisi Wali Kelas</span>
        <span>/</span>
        <span class="text-slate-800">Ledger Nilai</span>
      </nav>

      <!-- Header Section -->
      <div class="mt-3 flex flex-col justify-between gap-4 md:flex-row md:items-end print:hidden">
        <div>
          <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800 border border-emerald-200/60">
            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
            Supervisi Akademik Rombel
          </div>
          <h1 class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
            Ledger Nilai Rapor Madrasah
          </h1>
          <p class="mt-1 text-xs sm:text-sm text-slate-500">
            Kompilasi nilai akhir seluruh mata pelajaran, peringkat kelas, rata-rata, dan ketercapaian KKTP.
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
            Ekspor Ledger (Excel)
          </button>

          <button
            @click="printLedger"
            class="inline-flex items-center justify-center gap-1.5 rounded-2xl bg-slate-800 hover:bg-slate-700 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Cetak Ledger
          </button>
        </div>
      </div>

      <!-- Controls & Rombel Filter (Print: Hidden) -->
      <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-4 shadow-xs print:hidden">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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

      <!-- KPI Summary Cards (Print: Hidden) -->
      <div v-if="classSummary" class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4 print:hidden">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs">
          <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Siswa</p>
          <p class="mt-2 text-3xl font-black text-slate-900">{{ classSummary.total_students }}</p>
          <p class="mt-1 text-[11px] text-slate-500">{{ rombelData?.class_name }}</p>
        </div>

        <div class="rounded-3xl border border-emerald-200/80 bg-emerald-50/60 p-5 shadow-xs">
          <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Rerata Nilai Kelas</p>
          <p class="mt-2 text-3xl font-black text-emerald-800">{{ classSummary.class_average }}</p>
          <p class="mt-1 text-[11px] text-emerald-600">KKTP Standar: 75.0</p>
        </div>

        <div class="rounded-3xl border border-purple-200/80 bg-purple-50/60 p-5 shadow-xs">
          <p class="text-[11px] font-bold uppercase tracking-wider text-purple-700">Bintang Kelas (Rank 1)</p>
          <p class="mt-2 text-xl font-black text-purple-900 truncate">{{ classSummary.top_student?.name || '-' }}</p>
          <p class="mt-1 text-[11px] text-purple-700 font-semibold">Rata-rata: {{ classSummary.top_student?.average_score || 0 }}</p>
        </div>

        <div class="rounded-3xl border border-blue-200/80 bg-blue-50/60 p-5 shadow-xs">
          <p class="text-[11px] font-bold uppercase tracking-wider text-blue-700">Total Mata Pelajaran</p>
          <p class="mt-2 text-3xl font-black text-blue-800">{{ classSummary.total_subjects }}</p>
          <p class="mt-1 text-[11px] text-blue-600">Kurikulum Merdeka MAN 11</p>
        </div>
      </div>

      <!-- Print Letterhead Header -->
      <div class="hidden print:block mb-6 text-center border-b-2 border-black pb-4">
        <h2 class="text-xl font-bold uppercase tracking-wide">KEMENTERIAN AGAMA REPUBLIK INDONESIA</h2>
        <h1 class="text-2xl font-black">MADRASAH ALIYAH NEGERI 11 JAKARTA</h1>
        <p class="text-xs">LEDGER NILAI CAPAIAN HASIL BELAJAR SISWA - TAHUN AJARAN 2026/2027</p>
        <p class="text-xs font-bold mt-1">Kelas: {{ rombelData?.class_name }} | Wali Kelas: {{ rombelData?.homeroom_teacher }}</p>
      </div>

      <!-- Freeze-Column Ledger Table -->
      <div class="mt-6 rounded-3xl border border-slate-200 bg-white shadow-xs overflow-hidden">
        <div v-if="dataLoading" class="p-12 text-center text-xs text-slate-400">
          Menghitung dan memuat ledger nilai rombel...
        </div>

        <div v-else-if="filteredStudents.length === 0" class="p-12 text-center text-xs text-slate-400">
          Belum ada data nilai atau siswa pada rombel ini.
        </div>

        <div v-else class="overflow-x-auto max-h-[75vh]">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase tracking-wider text-[10px] sticky top-0 z-20">
                <!-- Freeze Columns Left -->
                <th class="py-3.5 px-3 font-bold text-center w-14 sticky left-0 bg-slate-100 z-30">Rank</th>
                <th class="py-3.5 px-3 font-bold w-28 sticky left-14 bg-slate-100 z-30">NISN</th>
                <th class="py-3.5 px-4 font-bold min-w-[200px] sticky left-42 bg-slate-100 z-30 border-r border-slate-200">
                  Nama Siswa
                </th>

                <!-- Dynamic Subject Columns -->
                <th
                  v-for="subj in subjectsData"
                  :key="subj.course_id"
                  class="py-3.5 px-3 font-bold text-center min-w-[90px] border-r border-slate-100"
                  :title="subj.subject_name"
                >
                  <div class="truncate max-w-[100px]">{{ subj.subject_name }}</div>
                  <span class="text-[9px] text-slate-400 font-normal">KKTP: 75</span>
                </th>

                <!-- Calculated Columns Right -->
                <th class="py-3.5 px-4 font-bold text-center w-28 bg-emerald-50 text-emerald-800 border-l border-slate-200">Total</th>
                <th class="py-3.5 px-4 font-bold text-center w-28 bg-emerald-50 text-emerald-800">Rerata</th>
                <th class="py-3.5 px-4 font-bold text-center w-32 bg-slate-50">Status KKTP</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-sans">
              <tr
                v-for="s in filteredStudents"
                :key="s.student_id"
                class="hover:bg-slate-50/80 transition"
                :class="s.rank <= 3 ? 'bg-amber-50/20' : ''"
              >
                <!-- Freeze Left: Rank -->
                <td class="py-3 px-3 text-center sticky left-0 bg-white group-hover:bg-slate-50 z-10">
                  <span
                    class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-black"
                    :class="{
                      'bg-amber-400 text-amber-950 shadow-xs': s.rank === 1,
                      'bg-slate-300 text-slate-800': s.rank === 2,
                      'bg-amber-700/30 text-amber-900': s.rank === 3,
                      'text-slate-500 font-bold': s.rank > 3
                    }"
                  >
                    {{ s.rank }}
                  </span>
                </td>

                <!-- Freeze Left: NISN -->
                <td class="py-3 px-3 font-mono text-slate-500 sticky left-14 bg-white group-hover:bg-slate-50 z-10">
                  {{ s.nisn || '-' }}
                </td>

                <!-- Freeze Left: Name -->
                <td class="py-3 px-4 font-bold text-slate-900 sticky left-42 bg-white group-hover:bg-slate-50 z-10 border-r border-slate-200">
                  <div class="flex items-center gap-1.5">
                    <span>{{ s.name }}</span>
                    <span v-if="s.rank === 1" class="text-amber-500" title="Juara 1">⭐</span>
                  </div>
                </td>

                <!-- Subject Scores -->
                <td
                  v-for="subj in subjectsData"
                  :key="subj.course_id"
                  class="py-3 px-3 text-center font-bold border-r border-slate-100"
                  :class="s.subject_scores[subj.course_id] < 75 ? 'text-rose-600 bg-rose-50/40 font-black' : 'text-slate-800'"
                >
                  {{ s.subject_scores[subj.course_id] ?? '-' }}
                </td>

                <!-- Calculated Columns -->
                <td class="py-3 px-4 text-center font-bold text-slate-900 bg-emerald-50/30 border-l border-slate-200">
                  {{ s.total_score }}
                </td>

                <td class="py-3 px-4 text-center font-black text-slate-900 bg-emerald-50/50">
                  <span :class="s.average_score >= 85 ? 'text-emerald-700' : s.average_score < 75 ? 'text-rose-600' : 'text-slate-800'">
                    {{ s.average_score }}
                  </span>
                </td>

                <td class="py-3 px-4 text-center">
                  <span
                    class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                    :class="{
                      'bg-emerald-100 text-emerald-800 border border-emerald-200': s.status_kktp === 'TUNTAS',
                      'bg-amber-100 text-amber-800 border border-amber-200': s.status_kktp === 'PERLU PENGAYAAN',
                      'bg-rose-100 text-rose-800 border border-rose-200': s.status_kktp === 'BELUM TUNTAS'
                    }"
                  >
                    {{ s.status_kktp }}
                  </span>
                </td>
              </tr>
            </tbody>

            <!-- Table Footer: Subject Averages -->
            <tfoot class="bg-slate-100 border-t-2 border-slate-200 text-[11px] font-bold text-slate-700">
              <tr>
                <td colspan="3" class="py-3 px-4 text-right uppercase tracking-wider sticky left-0 bg-slate-100 z-10 border-r border-slate-200">
                  Rata-rata Nilai Mata Pelajaran:
                </td>
                <td
                  v-for="subj in subjectsData"
                  :key="subj.course_id"
                  class="py-3 px-3 text-center text-emerald-800 border-r border-slate-200"
                >
                  {{ subjectStats[subj.course_id]?.average || '-' }}
                </td>
                <td colspan="3" class="py-3 px-4 text-center text-slate-500">
                  Rerata Umum: <b>{{ classSummary?.class_average }}</b>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </main>
</template>
