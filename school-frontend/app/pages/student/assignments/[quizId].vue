<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppSkeleton from '~/components/ui/AppSkeleton.vue'

definePageMeta({ layout: 'student', middleware: 'auth' })

const route = useRoute()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie<string | null>('auth_token')

// Sidebar open/collapse synced with student layout header hamburger
const isSidebarOpen = useState('isStudentSidebarOpen', () => true)

const quizId = computed(() => route.params.quizId as string)

const loading = ref(true)
const submitting = ref(false)
const quiz = ref<any>(null)
const submission = ref<any>(null)
const showResubmitForm = ref(false)
const sidebarSearch = ref('')

// Form fields
const notes = ref('')
const selectedFile = ref<File | null>(null)
const fileError = ref('')
const submitSuccess = ref('')
const submitError = ref('')

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

const hasSubmission = computed(() => !!submission.value && !!submission.value.submitted_at)
const isGraded = computed(() => submission.value?.is_graded || submission.value?.status === 'graded')

const statusBadge = computed(() => {
  if (!hasSubmission.value) {
    return { text: 'Belum Dikumpulkan', bg: 'bg-rose-50 text-rose-700 border-rose-200' }
  }
  if (isGraded.value) {
    return { text: 'Sudah Dinilai', bg: 'bg-emerald-50 text-emerald-700 border-emerald-200' }
  }
  return { text: 'Terkumpul (Menunggu Penilaian)', bg: 'bg-blue-50 text-blue-700 border-blue-200' }
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
  submitSuccess.value = ''
  submitError.value = ''
  try {
    const [quizRes, subRes] = await Promise.allSettled([
      api<any>(`/quizzes/${quizId.value}`),
      api<any>(`/quizzes/${quizId.value}/my-submission`)
    ])

    if (quizRes.status === 'fulfilled') {
      quiz.value = quizRes.value
    }
    if (subRes.status === 'fulfilled') {
      const data = (subRes.value as any)?.data
      submission.value = data || null
      if (submission.value?.submission_text) {
        notes.value = submission.value.submission_text
      }
    }
  } catch (err) {
    console.error('Gagal memuat tugas:', err)
  } finally {
    loading.value = false
  }
}

const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement
  fileError.value = ''
  if (!target.files || target.files.length === 0) {
    selectedFile.value = null
    return
  }

  const file = target.files[0]
  const allowedExtensions = ['pdf', 'doc', 'docx']
  const fileExt = file.name.split('.').pop()?.toLowerCase() || ''

  if (!allowedExtensions.includes(fileExt)) {
    fileError.value = 'Format file tidak diizinkan. Harap upload dokumen PDF, DOC, atau DOCX.'
    selectedFile.value = null
    return
  }

  if (file.size > 10 * 1024 * 1024) {
    fileError.value = 'Ukuran file melebihi batas maksimal 10 MB.'
    selectedFile.value = null
    return
  }

  selectedFile.value = file
}

const handleDrop = (event: DragEvent) => {
  event.preventDefault()
  fileError.value = ''
  if (!event.dataTransfer?.files || event.dataTransfer.files.length === 0) return

  const file = event.dataTransfer.files[0]
  const allowedExtensions = ['pdf', 'doc', 'docx']
  const fileExt = file.name.split('.').pop()?.toLowerCase() || ''

  if (!allowedExtensions.includes(fileExt)) {
    fileError.value = 'Format file tidak diizinkan. Harap upload dokumen PDF, DOC, atau DOCX.'
    return
  }

  if (file.size > 10 * 1024 * 1024) {
    fileError.value = 'Ukuran file melebihi batas maksimal 10 MB.'
    return
  }

  selectedFile.value = file
}

const handleSubmitAssignment = async () => {
  submitError.value = ''
  submitSuccess.value = ''
  fileError.value = ''

  if (!selectedFile.value && !hasSubmission.value) {
    fileError.value = 'Silakan pilih atau unggah file tugas Anda.'
    return
  }

  submitting.value = true
  try {
    const formData = new FormData()
    formData.append('quiz_id', quizId.value)
    if (selectedFile.value) {
      formData.append('file', selectedFile.value)
    }
    if (notes.value.trim()) {
      formData.append('submission_text', notes.value.trim())
      formData.append('notes', notes.value.trim())
    }

    const res = await $fetch<any>(`${apiBase}/quizzes/submit`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token.value}`,
        Accept: 'application/json'
      },
      body: formData
    })

    submitSuccess.value = res?.message || 'Tugas mandiri berhasil dikumpulkan!'
    showResubmitForm.value = false
    selectedFile.value = null
    await fetchData()
  } catch (err: any) {
    submitError.value = err?.data?.message || 'Gagal mengumpulkan tugas. Silakan coba kembali.'
  } finally {
    submitting.value = false
  }
}

// -------------------------------------------------------------------
// 6 Kategori Aktivitas Belajar Siswa (Dropdown Sidebar LMS)
// -------------------------------------------------------------------
const getMeetPretests = computed(() => (meet.value?.quizzes || []).filter((q: any) => q.activity_category === 'pretest'))
const getMeetMaterials = computed(() => meet.value?.materials || [])
const getMeetDiscussions = computed(() => meet.value?.discussions || [])
const getMeetQuizzes = computed(() => (meet.value?.quizzes || []).filter((q: any) => q.activity_category === 'quiz' || (!q.activity_category && (q.quiz_type === 'multiple_choice' || q.quiz_type === 'quiz'))))
const getMeetAssignments = computed(() => (meet.value?.quizzes || []).filter((q: any) => q.activity_category === 'assignment' || (!q.activity_category && (q.quiz_type === 'assignment' || q.quiz_type === 'essay'))))
const getMeetPosttests = computed(() => (meet.value?.quizzes || []).filter((q: any) => q.activity_category === 'posttest'))

const activityMenuItems = computed(() => [
  {
    key: 'pretest',
    number: 1,
    label: 'Pretest',
    count: getMeetPretests.value.length,
    active: false,
    url: getMeetPretests.value.length > 0 ? `/student/quizzes/${getMeetPretests.value[0].id}/onboarding` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=pretest`
  },
  {
    key: 'material',
    number: 2,
    label: 'Modul & Bahan Ajar',
    count: getMeetMaterials.value.length,
    active: false,
    url: getMeetMaterials.value.length > 0 ? `/student/materials/${getMeetMaterials.value[0].id}` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=material`
  },
  {
    key: 'discussion',
    number: 3,
    label: 'Ruang Diskusi',
    count: getMeetDiscussions.value.length,
    active: false,
    url: `/student/discussions/${meet.value?.id}`
  },
  {
    key: 'quiz',
    number: 4,
    label: 'Kuis Formatif',
    count: getMeetQuizzes.value.length,
    active: false,
    url: getMeetQuizzes.value.length > 0 ? `/student/quizzes/${getMeetQuizzes.value[0].id}/onboarding` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=quiz`
  },
  {
    key: 'assignment',
    number: 5,
    label: 'Tugas Mandiri',
    count: getMeetAssignments.value.length,
    active: true,
    url: '#'
  },
  {
    key: 'posttest',
    number: 6,
    label: 'Posttest',
    count: getMeetPosttests.value.length,
    active: false,
    url: getMeetPosttests.value.length > 0 ? `/student/quizzes/${getMeetPosttests.value[0].id}/onboarding` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=posttest`
  },
])

onMounted(fetchData)
</script>

<template>
  <div class="flex min-h-[calc(100vh-64px)] w-full bg-slate-50/50">
    
    <!-- 1. LEFT SIDEBAR: IDENTICAL TO DASHBOARD & COURSE PAGE -->
    <aside
      v-show="isSidebarOpen"
      class="w-64 sm:w-72 border-r border-slate-200 bg-white shrink-0 p-4 space-y-4 flex flex-col justify-between transition-all"
    >
      <div class="space-y-3">
        <!-- Sidebar Header Title matching Dashboard Courses List -->
        <div class="flex items-center justify-between">
          <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">
            Courses List
          </h2>
          <span
            v-if="meet"
            class="text-[10px] font-black px-2 py-0.5 rounded-full bg-blue-50 text-[#1565c0] border border-blue-200 shadow-2xs"
          >
            P{{ meet.meeting_number }}
          </span>
        </div>

        <!-- Search Input Sidebar identical to dashboard -->
        <div class="relative">
          <input
            v-model="sidebarSearch"
            type="text"
            placeholder="Pencarian tugas..."
            class="w-full pl-8 pr-3 py-2 text-xs border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:border-blue-600 transition font-medium text-slate-800 placeholder-slate-400"
          />
          <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <!-- Sesi Pertemuan Active Info Card -->
        <div v-if="loading" class="space-y-2 py-2">
          <AppSkeleton height="h-20" class="rounded-xl" />
        </div>
        <div v-else-if="meet" class="p-3 bg-blue-50/60 rounded-xl border border-blue-200/60 space-y-1.5">
          <div class="text-[10px] font-black uppercase tracking-wider text-blue-700">
            {{ subjectName }}
          </div>
          <div class="text-xs font-extrabold text-slate-900 leading-tight">
            Pertemuan {{ meet.meeting_number }}: {{ meet.title }}
          </div>
          <div class="text-[11px] text-slate-600 font-medium">
            {{ className }} • {{ teacherName }}
          </div>
          <div class="text-[10px] text-slate-400 pt-1 border-t border-blue-100 flex items-center justify-between">
            <span>Maks {{ quiz?.max_score || 100 }} Poin</span>
            <span class="text-emerald-700 font-bold">● Terhubung</span>
          </div>
        </div>

        <!-- 6 Aktivitas Belajar Standard LMS Dropdown / Menu -->
        <div v-if="meet" class="space-y-1.5 pt-1">
          <div class="flex items-center justify-between px-1 mb-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
              Aktivitas Belajar
            </span>
            <span class="text-[10px] text-slate-500 font-semibold">P{{ meet.meeting_number }}</span>
          </div>

          <div class="space-y-1">
            <template v-for="act in activityMenuItems" :key="act.key">
              <div
                v-if="act.active"
                class="p-2 rounded-xl bg-blue-50 border border-blue-200 flex items-center justify-between text-xs font-bold text-[#1565c0]"
              >
                <span class="flex items-center gap-2 truncate">
                  <span class="w-4 h-4 rounded text-[10px] font-bold flex items-center justify-center shrink-0 bg-[#1565c0] text-white">
                    {{ act.number }}
                  </span>
                  <span class="truncate">{{ act.label }}</span>
                </span>
                <span class="text-[10px] bg-blue-100 text-[#1565c0] px-1.5 py-0.5 rounded-md shrink-0 font-semibold">Aktif</span>
              </div>

              <NuxtLink
                v-else
                :to="act.url"
                class="p-2 rounded-xl hover:bg-slate-50 border border-transparent hover:border-slate-200 flex items-center justify-between text-xs font-medium text-slate-700 transition group"
              >
                <span class="flex items-center gap-2 truncate">
                  <span class="w-4 h-4 rounded text-[10px] font-bold flex items-center justify-center shrink-0 bg-slate-200 text-slate-700 group-hover:bg-blue-100 group-hover:text-blue-800">
                    {{ act.number }}
                  </span>
                  <span class="truncate">{{ act.label }}</span>
                </span>
                <span v-if="act.count > 0" class="text-[10px] text-slate-400 bg-slate-100 px-1.5 py-0.2 rounded-md shrink-0 font-semibold">
                  {{ act.count }}
                </span>
                <span v-else class="text-[10px] text-slate-300 group-hover:text-[#1565c0]">➔</span>
              </NuxtLink>
            </template>
          </div>
        </div>
      </div>

      <!-- Bottom of Sidebar: Navigasi Kembali ke Kursus & Dashboard -->
      <div class="pt-3 border-t border-slate-100 space-y-2">
        <NuxtLink
          :to="courseId ? `/elearning/courses/${courseId}` : '/student/dashboard'"
          class="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border border-blue-200 hover:border-blue-300 bg-blue-50 hover:bg-blue-100 text-[#1565c0] text-xs font-bold transition shadow-2xs group"
        >
          <span class="group-hover:-translate-x-0.5 transition-transform">←</span>
          <span>Kembali ke Kursus</span>
        </NuxtLink>

        <NuxtLink
          to="/student/dashboard"
          class="w-full flex items-center justify-center gap-2 px-3 py-1.5 text-slate-500 hover:text-slate-800 text-xs font-medium transition"
        >
          <span>Dashboard Utama</span>
        </NuxtLink>
      </div>
    </aside>

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
            <span
              class="text-xs font-bold px-2 py-0.5 rounded-full border"
              :class="statusBadge.bg"
            >
              {{ statusBadge.text }}
            </span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight uppercase">
            {{ subjectName }}
          </h1>
          <p class="text-xs sm:text-sm font-semibold text-slate-600 mt-1">
            {{ quiz?.title || 'Tugas Mandiri Siswa' }} • {{ className }} • {{ teacherName }}
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

      <!-- B. ASSIGNMENT MAIN BODY -->
      <div v-if="loading" class="space-y-4">
        <AppSkeleton height="h-28" class="rounded-2xl" />
        <AppSkeleton height="h-64" class="rounded-2xl" />
      </div>

      <div v-else-if="quiz" class="space-y-6">
        
        <!-- Header & Instructions Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-6 sm:p-7 shadow-xs space-y-4">
          <div>
            <h2 class="text-lg sm:text-xl font-extrabold text-slate-900">
              {{ quiz.title }}
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">
              Batas waktu: <span class="font-bold text-slate-600">{{ formatDate(quiz.due_date) }}</span>
            </p>
          </div>

          <div class="bg-amber-50/70 border border-amber-200/70 rounded-xl p-4 sm:p-5 space-y-2">
            <div class="text-xs font-bold text-amber-900 flex items-center gap-1.5 uppercase tracking-wider">
              <span>📋</span>
              <span>Instruksi Tugas dari Guru</span>
            </div>
            <p class="text-xs sm:text-sm text-amber-950 leading-relaxed whitespace-pre-line font-normal">
              {{ quiz.instructions || 'Tidak ada instruksi khusus. Kumpulkan dokumen tugas sesuai arahan saat sesi pembelajaran berlangsung.' }}
            </p>
          </div>

          <!-- Stats Bar -->
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 pt-2 text-xs">
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60">
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Tenggat Waktu</span>
              <span class="font-bold text-slate-800 mt-0.5 block truncate" :title="formatDate(quiz.due_date)">
                {{ formatDate(quiz.due_date) }}
              </span>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60">
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Skor Maksimal</span>
              <span class="font-bold text-emerald-700 mt-0.5 block">{{ quiz.max_score || 100 }} Poin</span>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60 col-span-2 sm:col-span-1">
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Format Berkas</span>
              <span class="font-bold text-slate-800 mt-0.5 block">PDF, DOC, DOCX (Maks 10 MB)</span>
            </div>
          </div>
        </div>

        <!-- Grade & Feedback Card (if graded) -->
        <div v-if="isGraded" class="bg-linear-to-r from-emerald-50 to-teal-50 border border-emerald-200 rounded-2xl p-6 sm:p-7 shadow-xs space-y-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <span class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-lg shadow-xs">
                ★
              </span>
              <div>
                <h3 class="text-sm font-bold text-emerald-950">Nilai & Ulasan Guru</h3>
                <p class="text-xs text-emerald-700">Tugas Anda telah diperiksa dan dinilai oleh guru pengampu.</p>
              </div>
            </div>
            <div class="text-right">
              <span class="text-2xl sm:text-3xl font-black text-emerald-800">{{ submission?.score }}</span>
              <span class="text-xs text-emerald-600 font-bold block">/ {{ quiz.max_score || 100 }} Poin</span>
            </div>
          </div>

          <div v-if="submission?.teacher_feedback" class="bg-white/90 rounded-xl p-4 border border-emerald-200/70 text-xs text-slate-700">
            <strong class="text-emerald-950 block mb-1">Catatan Evaluasi Guru:</strong>
            <p class="whitespace-pre-line leading-relaxed font-normal">{{ submission.teacher_feedback }}</p>
          </div>
        </div>

        <!-- Existing Submission Detail Card -->
        <div v-if="hasSubmission && !showResubmitForm" class="bg-white rounded-2xl border border-slate-200/80 p-6 sm:p-7 shadow-xs space-y-5">
          <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div>
              <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Berkas yang Telah Dikumpulkan</h2>
              <p class="text-xs text-slate-400 mt-0.5">Dikumpulkan pada {{ formatDate(submission?.submitted_at) }}</p>
            </div>
            <button
              v-if="!isGraded"
              type="button"
              @click="showResubmitForm = true"
              class="px-3.5 py-1.5 rounded-lg border border-slate-300 hover:border-slate-400 text-slate-700 text-xs font-bold transition hover:bg-slate-50 cursor-pointer"
            >
              Kirim Ulang / Perbarui
            </button>
          </div>

          <!-- File Download Widget -->
          <div v-if="submission?.file_url" class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-200">
            <div class="flex items-center gap-3 min-w-0">
              <span class="text-2xl">📄</span>
              <div class="min-w-0">
                <span class="text-xs font-bold text-slate-800 block truncate">
                  {{ submission.file_path?.split('/').pop() || 'Dokumen Tugas Siswa' }}
                </span>
                <span class="text-[11px] text-slate-400">Berkas Tugas Terlampir</span>
              </div>
            </div>
            <a
              :href="submission.file_url"
              target="_blank"
              download
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg transition shrink-0 shadow-2xs cursor-pointer"
            >
              Unduh Berkas
            </a>
          </div>

          <!-- Notes / Submission Text -->
          <div v-if="submission?.submission_text" class="p-4 bg-slate-50/60 rounded-xl border border-slate-200/60 text-xs text-slate-700 space-y-1">
            <strong class="text-slate-500 uppercase tracking-wider text-[10px] block">Catatan Siswa:</strong>
            <p class="whitespace-pre-line font-normal">{{ submission.submission_text }}</p>
          </div>
        </div>

        <!-- Submission Form (New or Re-submit) -->
        <div v-if="!hasSubmission || showResubmitForm" class="bg-white rounded-2xl border border-slate-200/80 p-6 sm:p-8 shadow-xs space-y-6">
          <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div>
              <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">
                {{ hasSubmission ? 'Form Perbarui Tugas' : 'Form Pengumpulan Tugas Mandiri' }}
              </h2>
              <p class="text-xs text-slate-400 mt-0.5">Unggah dokumen pengerjaan Anda dalam format yang ditentukan.</p>
            </div>
            <button
              v-if="hasSubmission"
              type="button"
              @click="showResubmitForm = false"
              class="text-xs font-semibold text-slate-500 hover:text-slate-700 cursor-pointer"
            >
              Batal
            </button>
          </div>

          <!-- Success Alert -->
          <div v-if="submitSuccess" class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 font-medium">
            ✓ {{ submitSuccess }}
          </div>

          <!-- Error Alert -->
          <div v-if="submitError" class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 font-medium">
            ⚠️ {{ submitError }}
          </div>

          <!-- Drag and Drop Upload Zone -->
          <div class="space-y-2">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
              Unggah File Tugas (PDF, DOC, DOCX - Maks 10 MB) <span class="text-rose-500">*</span>
            </label>

            <div
              @dragover.prevent
              @dragenter.prevent
              @drop="handleDrop"
              class="border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-2xl p-6 sm:p-8 text-center bg-slate-50/50 hover:bg-emerald-50/20 transition cursor-pointer relative"
            >
              <input
                id="assignment-file-input"
                type="file"
                accept=".pdf,.doc,.docx"
                @change="handleFileSelect"
                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
              />
              <div class="space-y-2 pointer-events-none">
                <span class="text-3xl block">📤</span>
                <div v-if="selectedFile">
                  <span class="text-xs font-bold text-emerald-700 block">{{ selectedFile.name }}</span>
                  <span class="text-[11px] text-slate-400 block">
                    {{ (selectedFile.size / (1024 * 1024)).toFixed(2) }} MB • Klik atau seret file lain untuk mengganti
                  </span>
                </div>
                <div v-else>
                  <span class="text-xs font-bold text-slate-700 block">Klik untuk memilih file atau seret file ke sini</span>
                  <span class="text-[11px] text-slate-400 block">Format yang didukung: .pdf, .doc, .docx (maksimum 10 MB)</span>
                </div>
              </div>
            </div>

            <p v-if="fileError" class="text-xs text-rose-600 font-semibold mt-1">
              {{ fileError }}
            </p>
          </div>

          <!-- Notes / Submission Text Field -->
          <div class="space-y-2">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
              Catatan / Keterangan Pengumpulan Tugas (Opsional)
            </label>
            <textarea
              v-model="notes"
              rows="3"
              placeholder="Tuliskan catatan tambahan mengenai tugas ini kepada guru pengampu..."
              class="w-full text-xs p-3.5 rounded-xl border border-slate-300 focus:outline-hidden focus:ring-2 focus:ring-blue-500 bg-white resize-y font-normal"
            ></textarea>
          </div>

          <!-- Submit Button -->
          <div class="pt-2 flex items-center justify-end gap-3">
            <button
              v-if="hasSubmission"
              type="button"
              @click="showResubmitForm = false"
              class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-xs font-bold hover:bg-slate-50 transition cursor-pointer"
            >
              Batal
            </button>

            <button
              type="button"
              :disabled="submitting"
              @click="handleSubmitAssignment"
              class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition shadow-xs disabled:opacity-50 flex items-center gap-2 cursor-pointer"
            >
              <span v-if="submitting" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <span>{{ submitting ? 'Mengunggah Berkas...' : (hasSubmission ? 'Simpan Perubahan' : 'Kumpulkan Tugas Sekarang') }}</span>
            </button>
          </div>
        </div>

      </div>

      <div v-else class="bg-white rounded-2xl border border-dashed border-slate-200 p-12 text-center text-slate-400">
        Informasi tugas tidak ditemukan atau telah dihapus.
      </div>

    </div>

  </div>
</template>
