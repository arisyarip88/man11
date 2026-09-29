<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

definePageMeta({ layout: 'student', middleware: 'auth' })

const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'

interface SubmissionScore {
  id: number
  quiz_title: string
  quiz_type: string
  subject: string
  submitted_at: string
  score: number | null
  teacher_feedback: string | null
  status: string
}

interface StudentScoreData {
  student: {
    id: number
    name: string
    nisn: string
    email: string
  }
  average_score: number
  submissions: SubmissionScore[]
}

const loading = ref(true)
const scoreData = ref<StudentScoreData | null>(null)
const search = ref('')

const api = async <T>(path: string) => {
  return await $fetch<T>(`${apiBase}${path}`, {
    headers: {
      Authorization: `Bearer ${token.value}`,
      Accept: 'application/json',
    },
  })
}

const fetchScores = async () => {
  loading.value = true
  try {
    const res: any = await api('/scores/student/me')
    scoreData.value = res
  } catch (err) {
    console.error('Gagal mengambil nilai rapor siswa:', err)
  } finally {
    loading.value = false
  }
}

const submissions = computed(() => scoreData.value?.submissions || [])

const filteredSubmissions = computed(() => {
  if (!search.value.trim()) return submissions.value
  const q = search.value.toLowerCase()
  return submissions.value.filter(s => {
    return (s.quiz_title || '').toLowerCase().includes(q) || (s.subject || '').toLowerCase().includes(q)
  })
})

// Group by Subject
const groupedBySubject = computed(() => {
  const groups: Record<string, SubmissionScore[]> = {}
  filteredSubmissions.value.forEach(s => {
    const sub = s.subject || 'Mata Pelajaran Lainnya'
    if (!groups[sub]) groups[sub] = []
    groups[sub].push(s)
  })
  return groups
})

const totalTasks = computed(() => submissions.value.length)
const gradedTasks = computed(() => submissions.value.filter(s => s.status === 'graded').length)
const avgScore = computed(() => scoreData.value?.average_score || 0)
const isPassed = computed(() => avgScore.value >= 75)

onMounted(() => {
  fetchScores()
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
            Transkrip Nilai
          </span>
          <span class="text-xs text-slate-400">Kurikulum Merdeka Asesmen</span>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">Buku Nilai & Rapor Hasil Belajar</h1>
        <p class="text-xs text-slate-500 mt-1">
          Pantau seluruh capaian kompetensi nilai tugas, formatif, dan ujian CBT Anda.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchScores"
          :disabled="loading"
          class="px-4 py-2 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-semibold shadow-xs transition flex items-center gap-1.5"
        >
          <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': loading }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Perbarui Nilai
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-4 gap-4 animate-pulse">
      <div v-for="i in 4" :key="i" class="h-28 bg-white rounded-2xl border border-slate-100 p-5"></div>
    </div>

    <template v-else>
      <!-- Summary Cards -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-xs space-y-1">
          <p class="text-[11px] font-bold uppercase text-slate-400">Total Tugas & Ujian</p>
          <p class="text-3xl font-black text-slate-800">{{ totalTasks }}</p>
          <p class="text-[11px] text-slate-500">Telah dikumpulkan</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-xs space-y-1">
          <p class="text-[11px] font-bold uppercase text-slate-400">Sudah Dinilai</p>
          <p class="text-3xl font-black text-emerald-600">{{ gradedTasks }}</p>
          <p class="text-[11px] text-slate-500">Dari {{ totalTasks }} kiriman</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-xs space-y-1">
          <p class="text-[11px] font-bold uppercase text-slate-400">Rata-Rata Kumulatif</p>
          <p class="text-3xl font-black text-slate-900">{{ avgScore }}</p>
          <p class="text-[11px] text-slate-500">Skala 100 poin</p>
        </div>

        <div
          class="p-5 rounded-2xl border shadow-xs space-y-1"
          :class="isPassed ? 'bg-emerald-500 text-white border-emerald-600' : 'bg-amber-500 text-white border-amber-600'"
        >
          <p class="text-[11px] font-bold uppercase opacity-80">Status KKTP (Kriteria)</p>
          <p class="text-2xl font-black">{{ isPassed ? 'TUNTAS (LULUS)' : 'PERLU REMIDI' }}</p>
          <p class="text-[11px] opacity-90">Standar KKTP: 75.00</p>
        </div>
      </div>

      <!-- Search Filter -->
      <div class="relative max-w-md">
        <input
          v-model="search"
          type="text"
          placeholder="Cari tugas atau mata pelajaran..."
          class="w-full pl-9 pr-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none transition shadow-xs"
        />
        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>

      <!-- Empty State -->
      <div v-if="filteredSubmissions.length === 0" class="bg-white rounded-3xl border border-slate-100 p-12 text-center space-y-3">
        <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <h3 class="text-sm font-bold text-slate-800">Belum ada data nilai</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto">
          Belum ada riwayat tugas atau ujian yang dikumpulkan atau dinilai oleh guru pengampu.
        </p>
      </div>

      <!-- Grouped by Subject Tables -->
      <div v-else class="space-y-6">
        <div
          v-for="(items, subjectName) in groupedBySubject"
          :key="subjectName"
          class="bg-white rounded-3xl border border-slate-100 shadow-xs overflow-hidden"
        >
          <div class="p-5 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
              <h2 class="text-sm font-bold text-slate-900">{{ subjectName }}</h2>
            </div>
            <span class="text-xs font-semibold text-slate-500">
              {{ items.length }} Item Penilaian
            </span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-100 text-slate-500 uppercase tracking-wider text-[11px]">
                  <th class="py-3 px-4 font-bold">Judul Asesmen / Tugas</th>
                  <th class="py-3 px-4 font-bold">Tipe</th>
                  <th class="py-3 px-4 font-bold">Waktu Dikirim</th>
                  <th class="py-3 px-4 font-bold text-center">Status</th>
                  <th class="py-3 px-4 font-bold text-center">Nilai</th>
                  <th class="py-3 px-4 font-bold">Ulasan Guru</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="item in items" :key="item.id" class="hover:bg-slate-50/60 transition">
                  <td class="py-3 px-4 font-bold text-slate-900">
                    {{ item.quiz_title }}
                  </td>
                  <td class="py-3 px-4">
                    <span
                      class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                      :class="item.quiz_type === 'multiple_choice' ? 'bg-purple-100 text-purple-700' : 'bg-amber-100 text-amber-700'"
                    >
                      {{ item.quiz_type === 'multiple_choice' ? 'CBT' : (item.quiz_type || 'Tugas') }}
                    </span>
                  </td>
                  <td class="py-3 px-4 font-mono text-slate-500 text-[11px]">
                    {{ item.submitted_at || '-' }}
                  </td>
                  <td class="py-3 px-4 text-center">
                    <span
                      class="px-2.5 py-0.5 rounded-full text-[10px] font-bold"
                      :class="item.status === 'graded' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                    >
                      {{ item.status === 'graded' ? 'Dinilai' : 'Menunggu' }}
                    </span>
                  </td>
                  <td class="py-3 px-4 text-center">
                    <span
                      v-if="item.score !== null"
                      class="font-black text-sm"
                      :class="item.score >= 75 ? 'text-emerald-600' : 'text-rose-600'"
                    >
                      {{ item.score }}
                    </span>
                    <span v-else class="text-slate-400 font-mono">-</span>
                  </td>
                  <td class="py-3 px-4 text-slate-500 text-[11px] italic">
                    {{ item.teacher_feedback || '-' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
