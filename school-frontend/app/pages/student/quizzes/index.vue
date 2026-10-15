<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import BackButton from '~/components/ui/BackButton.vue'
import AppButton from '~/components/ui/AppButton.vue'
import AppBadge from '~/components/ui/AppBadge.vue'
import AppCard from '~/components/ui/AppCard.vue'
import AppSkeleton from '~/components/ui/AppSkeleton.vue'
import AppEmptyState from '~/components/ui/AppEmptyState.vue'

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
  available_from?: string
  available_until?: string
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

const getQuizStatus = (q: any) => {
  const now = new Date()
  const from = q.available_from ? new Date(q.available_from) : null
  const until = q.available_until ? new Date(q.available_until) : (q.due_date ? new Date(q.due_date) : null)

  const formatShort = (d: Date) => {
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) + ' ' + 
      d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB'
  }

  if (until && !isNaN(until.getTime()) && now > until) {
    return {
      canStart: false,
      status: 'expired',
      label: `Sesi Berakhir (${formatShort(until)})`,
      buttonText: 'Sesi Ujian Ditutup'
    }
  }

  if (from && !isNaN(from.getTime()) && now < from) {
    return {
      canStart: false,
      status: 'scheduled',
      label: `Dibuka ${formatShort(from)}`,
      buttonText: 'Ujian Belum Dibuka'
    }
  }

  if (until && !isNaN(until.getTime())) {
    return {
      canStart: true,
      status: 'active',
      label: `Batas: ${formatShort(until)}`,
      buttonText: 'Mulai Ujian CBT'
    }
  }

  return {
    canStart: true,
    status: 'open',
    label: 'Akses Terbuka',
    buttonText: 'Mulai Ujian CBT'
  }
}

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

const activeQuizzes = computed(() => {
  return quizzes.value.filter(q => !submittedQuizMap.value[q.id])
})

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
    <!-- Top Navigation -->
    <div>
      <BackButton fallback="/student/dashboard" label="Kembali ke Dashboard Siswa" />
    </div>

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-5">
      <div>
        <div class="flex items-center gap-2">
          <AppBadge variant="brand" size="xs">CBT & Penugasan</AppBadge>
          <span class="text-xs text-slate-500 font-medium">Computer Based Test</span>
        </div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 mt-1">Ujian & Evaluasi Belajar</h1>
        <p class="text-xs text-slate-500 mt-1">
          Ikuti sesi ujian CBT online dan pantau riwayat hasil evaluasi akademik Anda.
        </p>
      </div>

      <!-- Tab Switcher -->
      <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200/70 text-xs font-semibold">
        <button
          type="button"
          @click="activeTab = 'active'"
          class="px-4 py-2 rounded-lg transition min-tap-target flex items-center gap-2"
          :class="activeTab === 'active' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900'"
        >
          <span>Ujian Aktif</span>
          <span class="px-2 py-0.5 rounded-md text-[11px] font-bold" :class="activeTab === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'">
            {{ activeQuizzes.length }}
          </span>
        </button>
        <button
          type="button"
          @click="activeTab = 'history'"
          class="px-4 py-2 rounded-lg transition min-tap-target flex items-center gap-2"
          :class="activeTab === 'history' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900'"
        >
          <span>Riwayat & Nilai</span>
          <span class="px-2 py-0.5 rounded-md text-[11px] font-bold" :class="activeTab === 'history' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'">
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
        class="w-full pl-9 pr-4 py-2 bg-white border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-600 focus:outline-none transition shadow-xs font-medium text-slate-800 placeholder-slate-400"
      />
      <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <AppSkeleton v-for="i in 3" :key="i" height="h-52" class="rounded-2xl" />
    </div>

    <!-- TAB 1: ACTIVE / UNCOMPLETED EXAMS -->
    <div v-else-if="activeTab === 'active'">
      <AppEmptyState
        v-if="filteredActive.length === 0"
        title="Semua Ujian & Tugas Selesai"
        description="Tidak ada ujian atau tugas aktif yang perlu dikerjakan. Anda dapat memeriksa hasil di tab Riwayat & Nilai."
      />

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <AppCard
          v-for="q in filteredActive"
          :key="q.id"
          variant="interactive"
          class="flex flex-col justify-between space-y-4"
        >
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <AppBadge :variant="q.quiz_type === 'multiple_choice' ? 'brand' : 'warning'" size="xs">
                {{ q.quiz_type === 'multiple_choice' ? 'Ujian CBT' : 'Tugas Mandiri' }}
              </AppBadge>

              <span v-if="q.duration_minutes" class="text-[11px] font-mono text-slate-700 font-bold bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200 flex items-center gap-1">
                <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ q.duration_minutes }} Menit
              </span>
            </div>

            <div>
              <h3 class="text-base font-bold text-slate-900 leading-snug">
                {{ q.title }}
              </h3>
              <p class="text-xs text-slate-500 font-medium mt-1">
                {{ q.meet?.course?.subject?.name || 'Mata Pelajaran' }} · {{ q.meet?.course?.class?.className?.name || 'Kelas' }}
              </p>
            </div>

            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60 text-xs text-slate-600 line-clamp-2">
              {{ q.instructions || 'Ikuti petunjuk ujian dengan teliti dan selesaikan sebelum batas waktu.' }}
            </div>

            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500 font-medium">
              <span>Status Akses:</span>
              <span
                class="px-2 py-0.5 rounded text-[10px] font-bold"
                :class="{
                  'bg-rose-50 text-rose-700 border border-rose-200': getQuizStatus(q).status === 'expired',
                  'bg-amber-50 text-amber-700 border border-amber-200': getQuizStatus(q).status === 'scheduled',
                  'bg-emerald-50 text-emerald-700 border border-emerald-200': getQuizStatus(q).status === 'active',
                  'bg-slate-50 text-slate-700 border border-slate-200': getQuizStatus(q).status === 'open'
                }"
              >
                {{ getQuizStatus(q).label }}
              </span>
            </div>
          </div>

          <div class="pt-2">
            <template v-if="q.quiz_type === 'multiple_choice'">
              <AppButton
                v-if="getQuizStatus(q).canStart"
                :to="`/student/quizzes/${q.id}/exam`"
                variant="primary"
                size="sm"
                block
              >
                <span>Mulai Ujian CBT</span>
                <svg class="w-4 h-4 ml-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
              </AppButton>
              <div
                v-else
                class="w-full py-2.5 px-3 rounded-xl bg-slate-100 border border-slate-200 text-center text-xs font-bold text-slate-500 cursor-not-allowed"
              >
                {{ getQuizStatus(q).buttonText }}
              </div>
            </template>

            <AppButton
              v-else
              :to="q.meet?.course?.id ? `/elearning/courses/${q.meet.course.id}?meet=${q.meet.meeting_number}` : `/elearning`"
              variant="outline"
              size="sm"
              block
            >
              <span>Kumpulkan Tugas Mandiri</span>
              <svg class="w-4 h-4 ml-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
              </svg>
            </AppButton>
          </div>
        </AppCard>
      </div>
    </div>

    <!-- TAB 2: EXAM HISTORY & SCORES -->
    <div v-else-if="activeTab === 'history'">
      <AppEmptyState
        v-if="filteredHistory.length === 0"
        title="Belum ada riwayat ujian"
        description="Anda belum menyelesaikan sesi ujian atau mengumpulkan tugas."
      />

      <AppCard v-else class="!p-0 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase tracking-wider text-[11px]">
                <th class="py-3.5 px-4 font-bold">Judul Asesmen / Tugas</th>
                <th class="py-3.5 px-4 font-bold">Mata Pelajaran</th>
                <th class="py-3.5 px-4 font-bold">Waktu Dikirim</th>
                <th class="py-3.5 px-4 font-bold text-center">Status</th>
                <th class="py-3.5 px-4 font-bold text-center">Nilai Perolehan</th>
                <th class="py-3.5 px-4 font-bold">Catatan Guru</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200/80">
              <tr v-for="item in filteredHistory" :key="item.submission.id" class="hover:bg-slate-50 transition">
                <td class="py-3.5 px-4 font-bold text-slate-900">
                  <div class="flex items-center gap-2">
                    <AppBadge :variant="item.quiz?.quiz_type === 'multiple_choice' ? 'brand' : 'warning'" size="xs">
                      {{ item.quiz?.quiz_type === 'multiple_choice' ? 'CBT' : 'Tugas' }}
                    </AppBadge>
                    <span>{{ item.quiz?.title || 'Ujian / Tugas' }}</span>
                  </div>
                </td>
                <td class="py-3.5 px-4 text-slate-600 font-medium">
                  {{ item.quiz?.meet?.course?.subject?.name || '-' }}
                </td>
                <td class="py-3.5 px-4 text-slate-500 font-mono text-[11px]">
                  {{ item.submission.submitted_at || '-' }}
                </td>
                <td class="py-3.5 px-4 text-center">
                  <AppBadge :variant="item.submission.status === 'graded' ? 'success' : 'warning'" size="xs">
                    {{ item.submission.status === 'graded' ? 'Sudah Dinilai' : 'Menunggu Koreksi' }}
                  </AppBadge>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span v-if="item.submission.score !== null" class="font-extrabold text-sm text-emerald-700">
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
      </AppCard>
    </div>
  </div>
</template>
