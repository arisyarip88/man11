<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import BackButton from '~/components/ui/BackButton.vue'
import AppButton from '~/components/ui/AppButton.vue'
import AppBadge from '~/components/ui/AppBadge.vue'
import AppCard from '~/components/ui/AppCard.vue'
import AppSkeleton from '~/components/ui/AppSkeleton.vue'
import AppEmptyState from '~/components/ui/AppEmptyState.vue'

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
    if (filterType.value === 'multiple_choice' && q.quiz_type !== 'multiple_choice') return false
    if (filterType.value === 'assignment' && q.quiz_type === 'multiple_choice') return false

    if (!search.value.trim()) return true
    const s = search.value.toLowerCase()
    const titleMatch = (q.title || '').toLowerCase().includes(s)
    const mapelMatch = (q.meet?.course?.subject?.name || '').toLowerCase().includes(s)
    const kelasMatch = (q.meet?.course?.class?.className?.name || '').toLowerCase().includes(s)
    return titleMatch || mapelMatch || kelasMatch
  })
})

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
    <!-- Top Back Link -->
    <div>
      <BackButton fallback="/teacher/dashboard" label="Kembali ke Dashboard Guru" />
    </div>

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-5">
      <div>
        <div class="flex items-center gap-2">
          <AppBadge variant="brand" size="xs">Asesmen & CBT</AppBadge>
          <span class="text-xs text-slate-500 font-medium">Monitoring Evaluasi Belajar Siswa</span>
        </div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 mt-1">Daftar Kuis & Ujian CBT</h1>
        <p class="text-xs text-slate-500 mt-1">
          Pantau seluruh penugasan mandiri dan asesmen CBT yang telah Anda terbitkan di kelas pengampu.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <AppButton to="/teacher/questions/bank" variant="outline" size="sm">
          Kelola Bank Soal
        </AppButton>
      </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <AppCard class="space-y-1">
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Kuis & Tugas</p>
        <p class="text-2xl font-black text-slate-900">{{ totalQuizzes }}</p>
        <p class="text-[11px] text-slate-500 font-medium">Lintas pertemuan</p>
      </AppCard>
      <AppCard class="space-y-1">
        <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">Ujian CBT Aktif</p>
        <p class="text-2xl font-black text-emerald-700">{{ totalCbt }}</p>
        <p class="text-[11px] text-slate-500 font-medium">Sinkron Bank Soal</p>
      </AppCard>
      <AppCard class="space-y-1">
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Kiriman Siswa</p>
        <p class="text-2xl font-black text-emerald-700">{{ totalSubmissions }}</p>
        <p class="text-[11px] text-slate-500 font-medium">Terkumpul</p>
      </AppCard>
      <AppCard class="space-y-1">
        <p class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Perlu Dinilai</p>
        <p class="text-2xl font-black text-amber-700">{{ pendingGradingCount }}</p>
        <p class="text-[11px] text-amber-800/80 font-medium">Belum diberi nilai</p>
      </AppCard>
    </div>

    <!-- Filter & Search Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
      <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-lg text-xs font-semibold w-full sm:w-auto">
        <button
          type="button"
          @click="filterType = 'all'"
          class="px-3 py-1.5 rounded-md transition min-tap-target"
          :class="filterType === 'all' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900'"
        >
          Semua ({{ quizzes.length }})
        </button>
        <button
          type="button"
          @click="filterType = 'multiple_choice'"
          class="px-3 py-1.5 rounded-md transition min-tap-target"
          :class="filterType === 'multiple_choice' ? 'bg-white text-emerald-800 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900'"
        >
          Ujian CBT ({{ totalCbt }})
        </button>
        <button
          type="button"
          @click="filterType = 'assignment'"
          class="px-3 py-1.5 rounded-md transition min-tap-target"
          :class="filterType === 'assignment' ? 'bg-white text-amber-800 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900'"
        >
          Tugas Mandiri ({{ totalQuizzes - totalCbt }})
        </button>
      </div>

      <div class="relative w-full sm:w-72">
        <input
          v-model="search"
          type="text"
          placeholder="Cari judul, kelas, atau mapel..."
          class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none transition font-medium text-slate-800 placeholder-slate-400"
        />
        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
    </div>

    <!-- Quizzes List -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <AppSkeleton v-for="i in 3" :key="i" height="h-44" class="rounded-xl" />
    </div>

    <div v-else-if="filteredQuizzes.length === 0">
      <AppEmptyState
        title="Tidak ada kuis atau tugas"
        description="Belum ada asesmen yang sesuai dengan kriteria pencarian atau filter yang dipilih."
      />
    </div>

    <div v-else class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-xs">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase tracking-wider text-[11px]">
              <th class="py-3.5 px-4 font-bold">Judul Asesmen</th>
              <th class="py-3.5 px-4 font-bold">Mata Pelajaran & Kelas</th>
              <th class="py-3.5 px-4 font-bold">Durasi / Batas Waktu</th>
              <th class="py-3.5 px-4 font-bold text-center">Pengumpulan</th>
              <th class="py-3.5 px-4 font-bold text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200/80">
            <tr v-for="q in filteredQuizzes" :key="q.id" class="hover:bg-slate-50 transition">
              <td class="py-3.5 px-4">
                <div class="flex items-center gap-2">
                  <AppBadge :variant="q.quiz_type === 'multiple_choice' ? 'brand' : 'warning'" size="xs">
                    {{ q.quiz_type === 'multiple_choice' ? 'CBT' : 'Tugas' }}
                  </AppBadge>
                  <span class="font-bold text-slate-900">{{ q.title }}</span>
                </div>
              </td>
              <td class="py-3.5 px-4 text-slate-600 font-medium">
                {{ q.meet?.course?.subject?.name || 'Mata Pelajaran' }} · {{ q.meet?.course?.class?.className?.name || 'Kelas' }}
              </td>
              <td class="py-3.5 px-4 text-slate-500 font-mono text-[11px]">
                <span v-if="q.duration_minutes">{{ q.duration_minutes }} Menit · </span>
                <span>{{ q.due_date || 'Tanpa batas' }}</span>
              </td>
              <td class="py-3.5 px-4 text-center font-bold">
                <span class="text-slate-900">{{ q.submissions_count || (q.submissions ? q.submissions.length : 0) }}</span> Siswa
              </td>
              <td class="py-3.5 px-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <AppButton
                    v-if="q.quiz_type === 'multiple_choice'"
                    :to="`/teacher/quizzes/${q.id}/grading`"
                    variant="primary"
                    size="xs"
                  >
                    Koreksi CBT
                  </AppButton>
                  <AppButton
                    :to="`/elearning/meets/${q.meet_id}?tab=quizzes`"
                    variant="outline"
                    size="xs"
                  >
                    Pertemuan
                  </AppButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
