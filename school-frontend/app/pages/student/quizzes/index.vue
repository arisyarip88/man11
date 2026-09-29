<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

definePageMeta({ layout: 'student', middleware: 'auth' })

const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'

interface QuizItem {
  id: number
  meet_id: number
  title: string
  instructions?: string
  quiz_type: string
  due_date: string
  max_score: number
  duration_minutes?: number
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

interface SubmissionItem {
  id: number
  quiz_id: number
  score: number | null
  status: string
  submitted_at: string
  teacher_feedback?: string
  quiz?: QuizItem
}

const loading = ref(true)
const quizzes = ref<QuizItem[]>([])
const submissions = ref<SubmissionItem[]>([])
const activeTab = ref<'active' | 'history'>('active')
const search = ref('')

const api = async <T>(path: string) => {
  return await $fetch<T>(`${apiBase}${path}`, {
    headers: {
      Authorization: `Bearer ${token.value}`,
      Accept: 'application/json',
    },
  })
}

const fetchData = async () => {
  loading.value = true
  try {
    const [quizRes, subRes]: any = await Promise.all([
      api('/quizzes?my_quizzes=true'),
      api('/submissions?my_submissions=true')
    ])
    quizzes.value = quizRes.data || (Array.isArray(quizRes) ? quizRes : [])
    submissions.value = subRes.data || (Array.isArray(subRes) ? subRes : [])
  } catch (err) {
    console.error('Gagal mengambil data ujian & kuis:', err)
  } finally {
    loading.value = false
  }
}

const submittedQuizMap = computed(() => {
  const map: Record<number, SubmissionItem> = {}
  submissions.value.forEach(s => {
    if (s.quiz_id) {
      map[s.quiz_id] = s
    }
  })
  return map
})

// Unsubmitted / Active Quizzes
const activeQuizzes = computed(() => {
  return quizzes.value.filter(q => !submittedQuizMap.value[q.id])
})

// Submitted Quizzes (History)
const historyQuizzes = computed(() => {
  return submissions.value.map(s => {
    const quizDetail = quizzes.value.find(q => q.id === s.quiz_id) || s.quiz
    return {
      submission: s,
      quiz: quizDetail
    }
  })
})

const filteredActive = computed(() => {
  if (!search.value.trim()) return activeQuizzes.value
  const q = search.value.toLowerCase()
  return activeQuizzes.value.filter(item => {
    const titleMatch = (item.title || '').toLowerCase().includes(q)
    const mapelMatch = (item.meet?.course?.subject?.name || '').toLowerCase().includes(q)
    return titleMatch || mapelMatch
  })
})

const filteredHistory = computed(() => {
  if (!search.value.trim()) return historyQuizzes.value
  const q = search.value.toLowerCase()
  return historyQuizzes.value.filter(item => {
    const titleMatch = (item.quiz?.title || '').toLowerCase().includes(q)
    const mapelMatch = (item.quiz?.meet?.course?.subject?.name || '').toLowerCase().includes(q)
    return titleMatch || mapelMatch
  })
})

onMounted(() => {
  fetchData()
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
          <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-purple-100 text-purple-800">
            CBT & Penugasan
          </span>
          <span class="text-xs text-slate-400">Computer Based Test Siswa</span>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">Ujian & Tugas Belajar</h1>
        <p class="text-xs text-slate-500 mt-1">
          Ikuti sesi ujian CBT online dan pantau seluruh riwayat nilai hasil evaluasi Anda.
        </p>
      </div>

      <!-- Tab Switcher -->
      <div class="flex items-center gap-1.5 bg-white p-1 rounded-2xl border border-slate-100 shadow-xs text-xs font-bold">
        <button
          type="button"
          @click="activeTab = 'active'"
          class="px-4 py-2 rounded-xl transition flex items-center gap-1.5"
          :class="activeTab === 'active' ? 'bg-purple-600 text-white shadow-xs' : 'text-slate-500 hover:text-slate-800'"
        >
          <span>Ujian Aktif</span>
          <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'active' ? 'bg-purple-700 text-white' : 'bg-slate-100 text-slate-600'">
            {{ activeQuizzes.length }}
          </span>
        </button>
        <button
          type="button"
          @click="activeTab = 'history'"
          class="px-4 py-2 rounded-xl transition flex items-center gap-1.5"
          :class="activeTab === 'history' ? 'bg-purple-600 text-white shadow-xs' : 'text-slate-500 hover:text-slate-800'"
        >
          <span>Riwayat & Nilai</span>
          <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'history' ? 'bg-purple-700 text-white' : 'bg-slate-100 text-slate-600'">
            {{ historyQuizzes.length }}
          </span>
        </button>
      </div>
    </div>

    <!-- Search Input -->
    <div class="relative max-w-md">
      <input
        v-model="search"
        type="text"
        placeholder="Cari nama ujian atau mata pelajaran..."
        class="w-full pl-9 pr-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-purple-500 focus:outline-none transition shadow-xs"
      />
      <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div v-for="i in 3" :key="i" class="h-44 bg-white rounded-2xl border border-slate-100 p-6 animate-pulse"></div>
    </div>

    <!-- TAB 1: ACTIVE / UNCOMPLETED EXAMS -->
    <div v-else-if="activeTab === 'active'">
      <div v-if="filteredActive.length === 0" class="bg-white rounded-3xl border border-slate-100 p-12 text-center space-y-3">
        <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mx-auto">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h3 class="text-sm font-bold text-slate-800">Semua Ujian & Tugas Sudah Selesai!</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto">
          Tidak ada ujian atau tugas yang perlu dikerjakan saat ini. Anda dapat memeriksa hasil nilai Anda di tab Riwayat.
        </p>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div
          v-for="q in filteredActive"
          :key="q.id"
          class="bg-white rounded-2xl border border-slate-100 p-6 shadow-xs hover:shadow-md hover:border-purple-200 transition flex flex-col justify-between space-y-4 group"
        >
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span
                class="px-2.5 py-1 text-[10px] font-bold uppercase rounded-lg"
                :class="q.quiz_type === 'multiple_choice' ? 'bg-purple-100 text-purple-800' : 'bg-amber-100 text-amber-800'"
              >
                {{ q.quiz_type === 'multiple_choice' ? 'Ujian CBT' : 'Tugas Mandiri' }}
              </span>
              <span v-if="q.duration_minutes" class="text-[11px] font-mono text-purple-700 font-bold bg-purple-50 px-2 py-0.5 rounded-md">
                ⏱ {{ q.duration_minutes }} Menit
              </span>
            </div>

            <div>
              <h3 class="text-base font-bold text-slate-900 group-hover:text-purple-700 transition">
                {{ q.title }}
              </h3>
              <p class="text-xs text-slate-500 font-medium mt-0.5">
                {{ q.meet?.course?.subject?.name || 'Mata Pelajaran' }} · {{ q.meet?.course?.class?.className?.name || 'Kelas' }}
              </p>
            </div>

            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 text-xs text-slate-600 line-clamp-2">
              {{ q.instructions || 'Ikuti petunjuk ujian dengan teliti dan selesaikan sebelum batas waktu pengumpulan.' }}
            </div>

            <div class="pt-2 border-t border-slate-50 flex items-center justify-between text-[11px] text-slate-500">
              <span>Batas Waktu:</span>
              <span class="font-mono text-rose-600 font-semibold">{{ q.due_date || 'Tidak ditentukan' }}</span>
            </div>
          </div>

          <div class="pt-2">
            <NuxtLink
              v-if="q.quiz_type === 'multiple_choice'"
              :to="`/student/quizzes/${q.id}/exam`"
              class="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold shadow-xs transition group-hover:shadow"
            >
              <span>Mulai Ujian CBT</span>
              <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
              </svg>
            </NuxtLink>

            <NuxtLink
              v-else
              :to="`/elearning/meets/${q.meet_id}?tab=quizzes`"
              class="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs transition"
            >
              <span>Kumpulkan Tugas di Kelas →</span>
            </NuxtLink>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: EXAM HISTORY & SCORES -->
    <div v-else-if="activeTab === 'history'">
      <div v-if="filteredHistory.length === 0" class="bg-white rounded-3xl border border-slate-100 p-12 text-center space-y-3">
        <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <h3 class="text-sm font-bold text-slate-800">Belum ada riwayat ujian</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto">
          Anda belum mengumpulkan ujian atau tugas apa pun.
        </p>
      </div>

      <div v-else class="bg-white rounded-3xl border border-slate-100 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-100 text-slate-500 uppercase tracking-wider text-[11px]">
                <th class="py-3.5 px-4 font-bold">Judul Asesmen / Tugas</th>
                <th class="py-3.5 px-4 font-bold">Mata Pelajaran</th>
                <th class="py-3.5 px-4 font-bold">Waktu Dikirim</th>
                <th class="py-3.5 px-4 font-bold text-center">Status</th>
                <th class="py-3.5 px-4 font-bold text-center">Nilai Perolehan</th>
                <th class="py-3.5 px-4 font-bold">Catatan Guru</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in filteredHistory" :key="item.submission.id" class="hover:bg-slate-50/70 transition">
                <td class="py-3.5 px-4 font-bold text-slate-900">
                  <div class="flex items-center gap-2">
                    <span
                      class="px-2 py-0.5 rounded-md text-[10px] font-bold"
                      :class="item.quiz?.quiz_type === 'multiple_choice' ? 'bg-purple-100 text-purple-700' : 'bg-amber-100 text-amber-700'"
                    >
                      {{ item.quiz?.quiz_type === 'multiple_choice' ? 'CBT' : 'Tugas' }}
                    </span>
                    <span>{{ item.quiz?.title || 'Ujian / Tugas' }}</span>
                  </div>
                </td>
                <td class="py-3.5 px-4 text-slate-600 font-medium">
                  {{ item.quiz?.meet?.course?.subject?.name || '-' }}
                </td>
                <td class="py-3.5 px-4 text-slate-400 font-mono text-[11px]">
                  {{ item.submission.submitted_at || '-' }}
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span
                    class="px-2.5 py-1 rounded-full text-[10px] font-bold"
                    :class="item.submission.status === 'graded' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                  >
                    {{ item.submission.status === 'graded' ? 'Sudah Dinilai' : 'Menunggu Koreksi' }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span v-if="item.submission.score !== null" class="font-extrabold text-sm text-emerald-600">
                    {{ item.submission.score }} <span class="text-xs text-slate-400 font-normal">/ {{ item.quiz?.max_score || 100 }}</span>
                  </span>
                  <span v-else class="text-slate-400 font-mono">-</span>
                </td>
                <td class="py-3.5 px-4 text-slate-500 italic text-[11px]">
                  {{ item.submission.teacher_feedback || 'Belum ada catatan feedback' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
