<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppSkeleton from '~/components/ui/AppSkeleton.vue'
import StudentActivitySidebar from '~/components/student/StudentActivitySidebar.vue'

definePageMeta({ layout: 'student', middleware: 'auth' })

const route = useRoute()
const router = useRouter()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie<string | null>('auth_token')

// Sidebar open/collapse synced with student layout header hamburger
const isSidebarOpen = useState('isStudentSidebarOpen', () => true)

const quizId = computed(() => route.params.id as string)
const loading = ref(true)
const quiz = ref<any>(null)
const examInfo = ref<any>(null)
const inputToken = ref('')
const tokenError = ref('')
const sidebarSearch = ref('')

const api = <T>(path: string, opts?: any) =>
  $fetch<T>(`${apiBase}${path}`, {
    headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
    ...opts
  })

const meet = computed(() => quiz.value?.meet)
const courseId = computed(() => meet.value?.course_id || meet.value?.course?.id)
const subjectName = computed(() => meet.value?.course?.subject?.name || 'Mata Pelajaran')
const teacherName = computed(() => meet.value?.course?.teacher?.name || 'Guru Pengampu')
const className = computed(() =>
  meet.value?.course?.class?.class_name?.name ||
  meet.value?.course?.class?.className?.name || 'Kelas'
)

const questionsCount = computed(() => {
  if (quiz.value?.questions_count !== undefined) return quiz.value.questions_count
  if (Array.isArray(quiz.value?.questions_data)) return quiz.value.questions_data.length
  return 0
})

const durationMinutes = computed(() => quiz.value?.duration_minutes || examInfo.value?.duration_minutes || 60)

const isSubmitted = computed(() => {
  if (examInfo.value?.is_submitted) return true
  const subs = quiz.value?.submissions
  if (Array.isArray(subs) && subs.length > 0) {
    return subs.some((s: any) => s.status === 'submitted' || s.is_graded || s.submitted_at)
  }
  return false
})

const mySubmission = computed(() => {
  const subs = quiz.value?.submissions
  if (Array.isArray(subs) && subs.length > 0) return subs[0]
  return null
})

const activityCategoryLabel = computed(() => {
  const cat = quiz.value?.activity_category || quiz.value?.quiz_type
  switch (cat) {
    case 'pretest': return 'Pre-Test Diagnostik'
    case 'posttest': return 'Post-Test Sumatif'
    case 'assignment': return 'Tugas Mandiri'
    default: return 'Kuis Formatif CBT'
  }
})

const formatDate = (dtStr?: string) => {
  if (!dtStr) return '-'
  try {
    const d = new Date(dtStr)
    return d.toLocaleDateString('id-ID', {
      day: 'numeric', month: 'short', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    }) + ' WIB'
  } catch { return dtStr }
}

const fetchData = async () => {
  loading.value = true
  tokenError.value = ''
  try {
    const [quizRes, infoRes] = await Promise.allSettled([
      api<any>(`/quizzes/${quizId.value}`),
      api<any>(`/quizzes/${quizId.value}/cbt/info`)
    ])

    if (quizRes.status === 'fulfilled') {
      quiz.value = quizRes.value
    }

    if (infoRes.status === 'fulfilled') {
      examInfo.value = (infoRes.value as any)?.data || null
    }
  } catch (err) {
    console.error('Gagal memuat detail kuis:', err)
  } finally {
    loading.value = false
  }
}

const handleStartExam = () => {
  tokenError.value = ''
  if (examInfo.value?.requires_token && !examInfo.value?.has_active_session) {
    if (!inputToken.value.trim()) {
      tokenError.value = 'Token ujian wajib diisi untuk memulai tes ini.'
      return
    }
  }

  // Navigate to exam page
  if (inputToken.value.trim()) {
    router.push({
      path: `/student/quizzes/${quizId.value}/exam`,
      query: { token: inputToken.value.trim().toUpperCase() }
    })
  } else {
    router.push(`/student/quizzes/${quizId.value}/exam`)
  }
}

const { isActivityDone, isQuizCompleted } = useStudentActivityProgress()

// -------------------------------------------------------------------
// 6 Kategori Aktivitas Belajar Siswa (Dropdown Sidebar LMS)
// -------------------------------------------------------------------
const getMeetPretests = computed(() => (meet.value?.quizzes || []).filter((q: any) => q.activity_category === 'pretest'))
const getMeetMaterials = computed(() => meet.value?.materials || [])
const getMeetDiscussions = computed(() => meet.value?.discussions || [])
const getMeetQuizzes = computed(() => (meet.value?.quizzes || []).filter((q: any) => q.activity_category === 'quiz' || (!q.activity_category && (q.quiz_type === 'multiple_choice' || q.quiz_type === 'quiz'))))
const getMeetAssignments = computed(() => (meet.value?.quizzes || []).filter((q: any) => q.activity_category === 'assignment' || (!q.activity_category && (q.quiz_type === 'assignment' || q.quiz_type === 'essay'))))
const getMeetPosttests = computed(() => (meet.value?.quizzes || []).filter((q: any) => q.activity_category === 'posttest'))

const currentCategory = computed(() => quiz.value?.activity_category || (quiz.value?.quiz_type === 'assignment' ? 'assignment' : 'quiz'))

const activityMenuItems = computed(() => [
  {
    key: 'pretest',
    label: 'Pretest',
    isDone: isActivityDone(meet.value, 'pretest'),
    active: currentCategory.value === 'pretest',
    url: currentCategory.value === 'pretest' ? '#' : (getMeetPretests.value.length > 0 ? `/student/quizzes/${getMeetPretests.value[0].id}/onboarding` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=pretest`)
  },
  {
    key: 'material',
    label: 'Modul & Bahan Ajar',
    isDone: isActivityDone(meet.value, 'material'),
    active: false,
    url: getMeetMaterials.value.length > 0 ? `/student/materials/${getMeetMaterials.value[0].id}` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=material`
  },
  {
    key: 'discussion',
    label: 'Ruang Diskusi',
    isDone: isActivityDone(meet.value, 'discussion'),
    active: false,
    url: `/student/discussions/${meet.value?.id}`
  },
  {
    key: 'quiz',
    label: 'Kuis Formatif',
    isDone: isActivityDone(meet.value, 'quiz'),
    active: currentCategory.value === 'quiz',
    url: currentCategory.value === 'quiz' ? '#' : (getMeetQuizzes.value.length > 0 ? `/student/quizzes/${getMeetQuizzes.value[0].id}/onboarding` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=quiz`)
  },
  {
    key: 'assignment',
    label: 'Tugas Mandiri',
    isDone: isActivityDone(meet.value, 'assignment'),
    active: currentCategory.value === 'assignment',
    url: currentCategory.value === 'assignment' ? '#' : (getMeetAssignments.value.length > 0 ? `/student/assignments/${getMeetAssignments.value[0].id}` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=assignment`)
  },
  {
    key: 'posttest',
    label: 'Posttest',
    isDone: isActivityDone(meet.value, 'posttest'),
    active: currentCategory.value === 'posttest',
    url: currentCategory.value === 'posttest' ? '#' : (getMeetPosttests.value.length > 0 ? `/student/quizzes/${getMeetPosttests.value[0].id}/onboarding` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=posttest`)
  },
])

onMounted(fetchData)
</script>

<template>
  <div class="flex min-h-[calc(100vh-64px)] w-full bg-slate-50/50">
    
    <!-- 1. LEFT SIDEBAR COMPONENT -->
    <StudentActivitySidebar
      :meet="meet"
      :subject-name="subjectName"
      :teacher-name="teacherName"
      :class-name="className"
      :course-id="courseId"
      :current-category="currentCategory"
      :loading="loading"
      :meta-text="`${questionsCount} Soal • ${durationMinutes}m`"
    />


    <!-- 2. RIGHT MAIN CONTENT AREA: IDENTICAL TO DASHBOARD MAIN AREA -->
    <div class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 overflow-x-hidden max-w-7xl w-full">
      
      <!-- A. HERO BANNER CARD IDENTICAL TO SCREENSHOT -->
      <div class="bg-[#dce9f8] rounded-2xl p-6 sm:p-7 border border-blue-200/60 flex items-center justify-between shadow-xs">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="text-xs font-bold uppercase tracking-wider text-blue-800">
              PERTEMUAN {{ meet?.meeting_number || '1' }}
            </span>
            <span class="text-blue-400">•</span>
            <span class="text-xs font-bold text-[#1565c0] uppercase">
              {{ activityCategoryLabel }}
            </span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight uppercase">
            {{ subjectName }}
          </h1>
          <p class="text-xs sm:text-sm font-semibold text-slate-600 mt-1">
            {{ quiz?.title || 'Sesi Ujian / Kuis Siswa' }} • {{ className }} • {{ teacherName }}
          </p>
        </div>

        <!-- Right Connected Nodes Emblem Icon identical to screenshot -->
        <div class="w-16 h-16 shrink-0 flex items-center justify-center text-[#1565c0]">
          <svg class="w-12 h-12" viewBox="0 0 48 48" fill="none">
            <circle cx="16" cy="16" r="5" stroke="currentColor" stroke-width="3" fill="none" />
            <circle cx="36" cy="20" r="4.5" stroke="currentColor" stroke-width="3" fill="none" />
            <circle cx="28" cy="36" r="5" stroke="currentColor" stroke-width="3" fill="none" />
            <path d="M21 17l10.5 2m-16 1.5l8.5 12.5m10-12.5l-3.5 10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
          </svg>
        </div>
      </div>

      <!-- B. ONBOARDING MAIN BODY -->
      <div v-if="loading" class="space-y-4">
        <AppSkeleton height="h-28" class="rounded-2xl" />
        <AppSkeleton height="h-64" class="rounded-2xl" />
      </div>

      <div v-else-if="quiz" class="space-y-6">
        
        <!-- Header Info Card -->
        <div
          class="bg-white rounded-2xl border p-6 sm:p-7 shadow-xs space-y-4 transition"
          :class="(isSubmitted || isQuizCompleted(quiz))
            ? 'border-2 border-emerald-500 shadow-md ring-2 ring-emerald-100/80 bg-emerald-50/10'
            : 'border-slate-200/80'"
        >
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-lg sm:text-xl font-extrabold text-slate-900">
                {{ quiz.title }}
              </h2>
              <span
                v-if="isSubmitted || isQuizCompleted(quiz)"
                class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1 shadow-2xs"
              >
                <span>✓</span>
                <span>Sudah Dikerjakan</span>
              </span>
            </div>
            <p v-if="quiz.instructions" class="text-xs sm:text-sm text-slate-600 leading-relaxed whitespace-pre-line bg-slate-50/80 p-4 rounded-xl border border-slate-200/60 mt-3 font-normal">
              {{ quiz.instructions }}
            </p>
          </div>

          <!-- Quick Stats Grid -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
            <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-200/50 text-center">
              <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Jumlah Soal</div>
              <div class="text-lg font-black text-slate-800 mt-0.5">
                {{ questionsCount > 0 ? `${questionsCount} Butir` : 'Dinamis' }}
              </div>
            </div>

            <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-200/50 text-center">
              <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Durasi</div>
              <div class="text-lg font-black text-slate-800 mt-0.5">{{ durationMinutes }} Menit</div>
            </div>

            <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-200/50 text-center">
              <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Skor Maksimal</div>
              <div class="text-lg font-black text-emerald-700 mt-0.5">{{ quiz.max_score || 100 }}</div>
            </div>

            <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-200/50 text-center">
              <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Tenggat Waktu</div>
              <div class="text-xs font-bold text-slate-700 mt-1 truncate" :title="formatDate(quiz.due_date || quiz.available_until)">
                {{ formatDate(quiz.due_date || quiz.available_until) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Submission Status Banner (if already submitted) -->
        <div v-if="isSubmitted" class="bg-emerald-500/10 border-2 border-emerald-500/30 rounded-2xl p-6 sm:p-7 flex flex-col sm:flex-row items-center justify-between gap-5">
          <div class="flex items-center gap-4 text-center sm:text-left">
            <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xl shrink-0 shadow-xs">
              ✓
            </div>
            <div>
              <h3 class="text-base font-bold text-emerald-950">Anda Sudah Menyelesaikan Tes Ini</h3>
              <p class="text-xs text-emerald-800/90 mt-0.5">
                Pengumpulan tercatat pada {{ formatDate(mySubmission?.submitted_at) }}.
                <span v-if="mySubmission?.is_graded" class="font-bold">
                  Nilai Anda: {{ mySubmission?.score }}/{{ quiz.max_score || 100 }}
                </span>
                <span v-else class="italic font-medium"> (Sedang diperiksa / hasil tercatat)</span>
              </p>
            </div>
          </div>

          <NuxtLink
            :to="`/student/quizzes/${quizId}/exam`"
            class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs whitespace-nowrap cursor-pointer"
          >
            Buka Lembar Review
          </NuxtLink>
        </div>

        <!-- Rules & Instructions Box -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-6 sm:p-7 shadow-xs space-y-4">
          <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
            Petunjuk & Peraturan Pengerjaan
          </h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs text-slate-600">
            <div class="flex items-start gap-3 p-3.5 bg-slate-50/70 rounded-xl border border-slate-200/60">
              <span class="text-base">⏱️</span>
              <div>
                <strong class="text-slate-800 block mb-0.5">Penghitung Waktu Otomatis</strong>
                Waktu akan mulai berjalan saat tombol "Mulai Mengerjakan" ditekan dan tidak dapat dihentikan sementara.
              </div>
            </div>

            <div class="flex items-start gap-3 p-3.5 bg-slate-50/70 rounded-xl border border-slate-200/60">
              <span class="text-base">💾</span>
              <div>
                <strong class="text-slate-800 block mb-0.5">Penyimpanan Jawaban Real-time</strong>
                Setiap jawaban yang Anda pilih akan otomatis tersimpan ke server.
              </div>
            </div>

            <div class="flex items-start gap-3 p-3.5 bg-slate-50/70 rounded-xl border border-slate-200/60">
              <span class="text-base">🛡️</span>
              <div>
                <strong class="text-slate-800 block mb-0.5">Sistem Anti-Kecurangan</strong>
                Dilarang berganti tab atau meminimalkan jendela ujian. Pelanggaran berulang dapat menyebabkan ujian terkunci.
              </div>
            </div>

            <div class="flex items-start gap-3 p-3.5 bg-slate-50/70 rounded-xl border border-slate-200/60">
              <span class="text-base">📶</span>
              <div>
                <strong class="text-slate-800 block mb-0.5">Koneksi Internet</strong>
                Pastikan perangkat terhubung dengan koneksi stabil sebelum memulai pengerjaan tes.
              </div>
            </div>
          </div>
        </div>

        <!-- Action / Launch Box -->
        <div v-if="!isSubmitted" class="bg-white rounded-2xl border border-slate-200/80 p-6 sm:p-8 shadow-xs text-center space-y-5">
          <!-- Token Input if Required -->
          <div v-if="examInfo?.requires_token && !examInfo?.has_active_session" class="max-w-xs mx-auto space-y-2">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
              Token Masuk Ujian
            </label>
            <input
              v-model="inputToken"
              type="text"
              maxlength="10"
              placeholder="MASUKKAN TOKEN"
              class="w-full text-center tracking-widest uppercase font-mono text-base font-bold px-4 py-3 rounded-xl border border-slate-300 focus:outline-hidden focus:ring-2 focus:ring-blue-500 bg-slate-50"
            />
            <p v-if="tokenError" class="text-xs text-rose-600 font-semibold mt-1">
              {{ tokenError }}
            </p>
            <p class="text-[11px] text-slate-400">
              Minta token kepada guru pengampu jika belum memilikinya.
            </p>
          </div>

          <div class="pt-2">
            <button
              type="button"
              @click="handleStartExam"
              class="inline-flex items-center justify-center gap-3 px-8 py-3.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-extrabold transition shadow-md shadow-blue-700/20 hover:shadow-lg hover:shadow-blue-700/30 active:scale-[0.99] cursor-pointer"
            >
              <span>🚀 Mulai Mengerjakan Sekarang</span>
              <span>→</span>
            </button>
            <p class="text-[11px] text-slate-400 mt-2">
              Dengan mengklik tombol di atas, Anda menyatakan siap mengerjakan tes secara jujur dan mandiri.
            </p>
          </div>
        </div>

      </div>

      <div v-else class="bg-white rounded-2xl border border-dashed border-slate-200 p-12 text-center text-slate-400">
        Informasi kuis tidak ditemukan atau telah dihapus.
      </div>

    </div>

  </div>
</template>
