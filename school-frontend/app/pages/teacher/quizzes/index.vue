<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

definePageMeta({ layout: 'teacher', middleware: 'auth' })

const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'

interface QuizItem {
  id: number
  meet_id: number
  title: string
  instructions: string
  quiz_type: string
  due_date: string
  max_score: number
  duration_minutes?: number
  submissions_count?: number
  submissions?: any[]
  meet?: {
    id: number
    title: string
    meeting_number: number
    course?: {
      id: number
      subject?: { name: string }
      class?: { className?: { name: string } }
    }
  }
}

const loading = ref(true)
const quizzes = ref<QuizItem[]>([])
const search = ref('')
const filterType = ref('all') // 'all' | 'multiple_choice' | 'assignment'

const api = async <T>(path: string) => {
  return await $fetch<T>(`${apiBase}${path}`, {
    headers: {
      Authorization: `Bearer ${token.value}`,
      Accept: 'application/json',
    },
  })
}

const fetchQuizzes = async () => {
  loading.value = true
  try {
    const res: any = await api('/quizzes?my_quizzes=true')
    quizzes.value = res.data || (Array.isArray(res) ? res : [])
  } catch (err) {
    console.error('Gagal mengambil kuis guru:', err)
  } finally {
    loading.value = false
  }
}

const filteredQuizzes = computed(() => {
  return quizzes.value.filter(q => {
    // Type filter
    if (filterType.value === 'multiple_choice' && q.quiz_type !== 'multiple_choice') return false
    if (filterType.value === 'assignment' && q.quiz_type === 'multiple_choice') return false

    // Search query
    if (!search.value.trim()) return true
    const s = search.value.toLowerCase()
    const titleMatch = (q.title || '').toLowerCase().includes(s)
    const mapelMatch = (q.meet?.course?.subject?.name || '').toLowerCase().includes(s)
    const kelasMatch = (q.meet?.course?.class?.className?.name || '').toLowerCase().includes(s)
    return titleMatch || mapelMatch || kelasMatch
  })
})

// Quick Stats
const totalQuizzes = computed(() => quizzes.value.length)
const totalCbt = computed(() => quizzes.value.filter(q => q.quiz_type === 'multiple_choice').length)
const totalSubmissions = computed(() => quizzes.value.reduce((acc, q) => acc + (q.submissions_count || (q.submissions ? q.submissions.length : 0)), 0))
const pendingGradingCount = computed(() => {
  let count = 0
  quizzes.value.forEach(q => {
    if (q.submissions && Array.isArray(q.submissions)) {
      count += q.submissions.filter((s: any) => s.status !== 'graded').length
    }
  })
  return count
})

onMounted(() => {
  fetchQuizzes()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-purple-100 text-purple-800">
            Asesmen & CBT
          </span>
          <span class="text-xs text-slate-400">Monitoring Evaluasi Belajar Siswa</span>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">Daftar Kuis & Ujian CBT</h1>
        <p class="text-xs text-slate-500 mt-1">
          Pantau seluruh penugasan mandiri dan asesmen CBT yang telah Anda terbitkan di kelas pengampu.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <NuxtLink
          to="/teacher/questions/bank"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition"
        >
          Kelola Bank Soal ➔
        </NuxtLink>
      </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-xs space-y-1">
        <p class="text-[11px] font-bold uppercase text-slate-400">Total Kuis & Tugas</p>
        <p class="text-2xl font-black text-slate-800">{{ totalQuizzes }}</p>
        <p class="text-[11px] text-slate-500">Lintas pertemuan</p>
      </div>
      <div class="bg-white p-4 rounded-2xl border border-purple-100 shadow-xs space-y-1">
        <p class="text-[11px] font-bold uppercase text-purple-600">Ujian CBT Aktif</p>
        <p class="text-2xl font-black text-purple-700">{{ totalCbt }}</p>
        <p class="text-[11px] text-purple-600/80">Sinkron Bank Soal</p>
      </div>
      <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-xs space-y-1">
        <p class="text-[11px] font-bold uppercase text-slate-400">Total Kiriman Siswa</p>
        <p class="text-2xl font-black text-emerald-600">{{ totalSubmissions }}</p>
        <p class="text-[11px] text-slate-500">Terkumpul</p>
      </div>
      <div class="bg-white p-4 rounded-2xl border border-amber-100 shadow-xs space-y-1">
        <p class="text-[11px] font-bold uppercase text-amber-600">Perlu Dinilai</p>
        <p class="text-2xl font-black text-amber-600">{{ pendingGradingCount }}</p>
        <p class="text-[11px] text-amber-700/80">Belum diberi nilai</p>
      </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-slate-100 shadow-xs">
      <div class="flex items-center gap-1.5 bg-slate-100 p-1 rounded-xl text-xs font-bold w-full sm:w-auto">
        <button
          type="button"
          @click="filterType = 'all'"
          class="px-3 py-1.5 rounded-lg transition"
          :class="filterType === 'all' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
        >
          Semua ({{ quizzes.length }})
        </button>
        <button
          type="button"
          @click="filterType = 'multiple_choice'"
          class="px-3 py-1.5 rounded-lg transition"
          :class="filterType === 'multiple_choice' ? 'bg-purple-600 text-white shadow-xs' : 'text-slate-500 hover:text-purple-600'"
        >
          ⚡ Ujian CBT ({{ totalCbt }})
        </button>
        <button
          type="button"
          @click="filterType = 'assignment'"
          class="px-3 py-1.5 rounded-lg transition"
          :class="filterType === 'assignment' ? 'bg-amber-600 text-white shadow-xs' : 'text-slate-500 hover:text-amber-600'"
        >
          📁 Tugas Mandiri ({{ totalQuizzes - totalCbt }})
        </button>
      </div>

      <div class="relative w-full sm:w-72">
        <input
          v-model="search"
          type="text"
          placeholder="Cari judul, kelas, atau mapel..."
          class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-purple-500 focus:outline-none transition"
        />
        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
    </div>

    <!-- Quizzes Table / List -->
    <div v-if="loading" class="bg-white rounded-3xl border border-slate-100 p-12 text-center text-xs text-slate-400">
      Memuat daftar kuis dan ujian...
    </div>

    <div v-else-if="filteredQuizzes.length === 0" class="bg-white rounded-3xl border border-slate-100 p-12 text-center space-y-3">
      <div class="w-12 h-12 bg-purple-50 text-purple-500 rounded-full flex items-center justify-center mx-auto">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
      </div>
      <h3 class="text-sm font-bold text-slate-800">Tidak ada kuis / tugas</h3>
      <p class="text-xs text-slate-500 max-w-sm mx-auto">
        Belum ada tugas atau ujian yang sesuai dengan filter pencarian ini. Buat tugas baru melalui halaman pertemuan di E-Learning.
      </p>
    </div>

    <div v-else class="bg-white rounded-3xl border border-slate-100 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100 text-slate-500 uppercase tracking-wider text-[11px]">
              <th class="py-3.5 px-4 font-bold">Judul Asesmen / Tugas</th>
              <th class="py-3.5 px-4 font-bold">Mata Pelajaran & Kelas</th>
              <th class="py-3.5 px-4 font-bold">Pertemuan</th>
              <th class="py-3.5 px-4 font-bold">Deadline</th>
              <th class="py-3.5 px-4 font-bold text-center">Terkumpul</th>
              <th class="py-3.5 px-4 font-bold text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="q in filteredQuizzes" :key="q.id" class="hover:bg-slate-50/70 transition">
              <td class="py-3.5 px-4">
                <div class="flex items-center gap-2">
                  <span
                    class="px-2 py-0.5 rounded-md text-[10px] font-bold"
                    :class="q.quiz_type === 'multiple_choice' ? 'bg-purple-100 text-purple-700' : 'bg-amber-100 text-amber-700'"
                  >
                    {{ q.quiz_type === 'multiple_choice' ? 'CBT' : 'Tugas' }}
                  </span>
                  <span class="font-bold text-slate-900">{{ q.title }}</span>
                </div>
                <div v-if="q.duration_minutes" class="text-[10px] text-purple-600 font-semibold mt-0.5">
                  ⏱ Durasi CBT: {{ q.duration_minutes }} Menit • Max: {{ q.max_score }} Poin
                </div>
              </td>
              <td class="py-3.5 px-4 font-medium text-slate-700">
                <div>{{ q.meet?.course?.subject?.name || 'Mata Pelajaran' }}</div>
                <div class="text-[11px] text-slate-400 font-normal">
                  {{ q.meet?.course?.class?.className?.name || 'Rombel' }}
                </div>
              </td>
              <td class="py-3.5 px-4 text-slate-600">
                <span class="px-2.5 py-1 bg-slate-100 rounded-lg text-[11px] font-semibold">
                  Pertemuan {{ q.meet?.meeting_number || '-' }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-slate-600">
                <span class="font-mono text-[11px] text-rose-600 font-medium">{{ q.due_date || '-' }}</span>
              </td>
              <td class="py-3.5 px-4 text-center">
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700">
                  {{ q.submissions_count || (q.submissions ? q.submissions.length : 0) }} Siswa
                </span>
              </td>
              <td class="py-3.5 px-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <NuxtLink
                    v-if="q.quiz_type === 'multiple_choice'"
                    :to="`/teacher/quizzes/${q.id}/grading`"
                    class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-[11px] font-bold rounded-lg shadow-xs transition"
                  >
                    Koreksi CBT 📊
                  </NuxtLink>
                  <NuxtLink
                    :to="`/elearning/meets/${q.meet_id}?tab=quizzes`"
                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-bold rounded-lg transition"
                  >
                    Buka Pertemuan ➔
                  </NuxtLink>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
