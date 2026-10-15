<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'

definePageMeta({ layout: false, middleware: 'auth' })

const route = useRoute()
const quizId = computed(() => route.params.id)

const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'

interface QuestionOption {
  key: string
  text: string
}

interface ExamQuestion {
  number: number
  type: 'pg' | 'pg_kompleks' | 'esai' | 'isian' | 'menjodohkan' | 'benar_salah'
  question: string
  options?: QuestionOption[]
  pairs?: { id: string; left: string }[]
  choices?: string[]
  statements?: { id: string; text: string }[]
  weight: number
  difficulty: string
}

const loading = ref(true)
const submitting = ref(false)
const quizTitle = ref('')
const questions = ref<ExamQuestion[]>([])
const currentIndex = ref(0)
const answers = ref<Record<string, any>>({})
const doubtful = ref<Record<string, boolean>>({})

// Security & Token State
const examInfo = ref<any>(null)
const isStarted = ref(false)
const inputToken = ref('')
const tokenError = ref('')
const startingExam = ref(false)
const maxViolations = ref(3)
const violationCount = ref(0)
const finishedReason = ref<string | null>(null)
const isViolationModalOpen = ref(false)
const violationMessage = ref('')
const isFullscreen = ref(false)
const showFullscreenPrompt = ref(false)
const lastViolationTime = ref(0)

// Timer State
const remainingSeconds = ref(3600)
let timerInterval: any = null

// Autosave State
const lastSaved = ref('Belum ada perubahan')
const isSaving = ref(false)
let autosaveTimeout: any = null

// Result State
const isFinished = ref(false)
const examResult = ref<any>(null)
const isConfirmModalOpen = ref(false)

const api = async <T>(path: string, options: any = {}) => {
  return await $fetch<T>(`${apiBase}${path}`, {
    ...options,
    headers: {
      Authorization: `Bearer ${token.value}`,
      Accept: 'application/json',
      ...options.headers,
    },
  })
}

const currentQuestion = computed(() => {
  return questions.value[currentIndex.value] || null
})

// Inisialisasi Ujian: Ambil info persiapan sebelum mulai
const initExam = async () => {
  loading.value = true
  tokenError.value = ''
  try {
    const res: any = await api(`/quizzes/${quizId.value}/cbt/info`)
    examInfo.value = res?.data || null

    if (examInfo.value) {
      quizTitle.value = examInfo.value.quiz_title
      maxViolations.value = examInfo.value.max_violations || 3

      if (examInfo.value.is_submitted) {
        await startExam()
        return
      }

      if (examInfo.value.has_active_session || !examInfo.value.requires_token) {
        await startExam()
        return
      }
    }
  } catch (err: any) {
    tokenError.value = err?.data?.message || 'Gagal memuat informasi sesi ujian.'
  } finally {
    loading.value = false
  }
}

// Mulai Ujian
const startExam = async (tokenInput?: string) => {
  startingExam.value = true
  tokenError.value = ''
  try {
    const payload: any = {}
    if (tokenInput) {
      payload.token = tokenInput.trim().toUpperCase()
    }

    const res = await api<any>(`/quizzes/${quizId.value}/cbt/start`, {
      method: 'POST',
      body: payload,
    })

    const data = res?.data
    if (data) {
      quizTitle.value = data.quiz_title
      remainingSeconds.value = data.remaining_seconds
      questions.value = data.questions || []
      answers.value = data.saved_answers || {}
      maxViolations.value = data.max_violations || maxViolations.value
      violationCount.value = data.violation_count || 0
      finishedReason.value = data.finished_reason || null

      if (data.status === 'submitted' || data.is_graded || data.remaining_seconds <= 0) {
        isFinished.value = true
        isStarted.value = true
        examResult.value = {
          score_pg: data.score,
          is_graded: data.is_graded,
          finished_reason: data.finished_reason,
          breakdown: data.breakdown || [],
        }
      } else {
        isStarted.value = true
        startTimer()
        setupSecurityListeners()
        enterFullscreen()
      }
    }
  } catch (err: any) {
    tokenError.value = err?.data?.message || 'Gagal memulai ujian CBT. Pastikan token valid.'
  } finally {
    startingExam.value = false
  }
}

// Timer Countdown
const startTimer = () => {
  if (timerInterval) clearInterval(timerInterval)
  timerInterval = setInterval(() => {
    if (remainingSeconds.value > 0) {
      remainingSeconds.value--
    } else {
      clearInterval(timerInterval)
      autoSubmitExam('timeout')
    }
  }, 1000)
}

const formattedTime = computed(() => {
  const h = Math.floor(remainingSeconds.value / 3600)
  const m = Math.floor((remainingSeconds.value % 3600) / 60)
  const s = remainingSeconds.value % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

// Autosave Jawaban
const triggerAutosave = () => {
  if (autosaveTimeout) clearTimeout(autosaveTimeout)
  isSaving.value = true
  autosaveTimeout = setTimeout(async () => {
    try {
      await api(`/quizzes/${quizId.value}/cbt/save-progress`, {
        method: 'POST',
        body: { answers: answers.value },
      })
      lastSaved.value = `Tersimpan ${new Date().toLocaleTimeString('id-ID')}`
    } catch (e) {
      lastSaved.value = 'Gagal menyimpan'
    } finally {
      isSaving.value = false
    }
  }, 1200)
}

const answerComponent = (type: string) => {
  switch (type) {
    case 'pg': return resolveComponent('ExamAnswerPg')
    case 'pg_kompleks': return resolveComponent('ExamAnswerPgKompleks')
    case 'isian': return resolveComponent('ExamAnswerIsian')
    case 'menjodohkan': return resolveComponent('ExamAnswerMenjodohkan')
    case 'benar_salah': return resolveComponent('ExamAnswerBenarSalah')
    default: return resolveComponent('ExamAnswerEsai')
  }
}

const setAnswer = (value: any) => {
  if (!currentQuestion.value) return
  answers.value[String(currentQuestion.value.number)] = value
  triggerAutosave()
}

const isAnswered = (num: number | string) => {
  const v = answers.value[String(num)]
  if (v === undefined || v === null) return false
  if (typeof v === 'string') return v.trim() !== ''
  if (Array.isArray(v)) return v.length > 0
  if (typeof v === 'object') return Object.keys(v).length > 0
  return true
}

const statusLabel: Record<string, string> = {
  benar: 'Benar',
  sebagian: 'Sebagian',
  salah: 'Salah',
  kosong: 'Kosong',
}

const toggleDoubtful = () => {
  if (!currentQuestion.value) return
  const qNum = String(currentQuestion.value.number)
  doubtful.value[qNum] = !doubtful.value[qNum]
}

// Selesaikan Ujian
const finishExam = async () => {
  submitting.value = true
  try {
    const res = await api<any>(`/quizzes/${quizId.value}/cbt/finish`, {
      method: 'POST',
      body: { answers: answers.value },
    })
    isFinished.value = true
    finishedReason.value = 'manual'
    examResult.value = res?.data
    if (timerInterval) clearInterval(timerInterval)
    isConfirmModalOpen.value = false
    cleanupSecurityListeners()
    exitFullscreen()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal mengumpulkan ujian.')
  } finally {
    submitting.value = false
  }
}

const autoSubmitExam = (reason: string = 'timeout') => {
  if (reason === 'timeout') {
    alert('Waktu ujian telah habis! Sistem mengumpulkan lembar jawaban Anda secara otomatis.')
  }
  finishExam()
}

// Fullscreen Controls
const enterFullscreen = async () => {
  try {
    if (document.documentElement.requestFullscreen && !document.fullscreenElement) {
      await document.documentElement.requestFullscreen()
      isFullscreen.value = true
      showFullscreenPrompt.value = false
    }
  } catch (e) {
    console.warn('Fullscreen tidak diizinkan oleh browser:', e)
  }
}

const exitFullscreen = () => {
  try {
    if (document.fullscreenElement && document.exitFullscreen) {
      document.exitFullscreen()
    }
  } catch (e) {}
}

const handleFullscreenChange = () => {
  isFullscreen.value = !!document.fullscreenElement
  if (!isFullscreen.value && isStarted.value && !isFinished.value) {
    showFullscreenPrompt.value = true
    recordSecurityViolation('fullscreen_exit')
  } else {
    showFullscreenPrompt.value = false
  }
}

// Deteksi Pindah Tab / Blur
const recordSecurityViolation = async (type: string) => {
  if (!isStarted.value || isFinished.value) return

  const now = Date.now()
  if (now - lastViolationTime.value < 2000) return
  lastViolationTime.value = now

  try {
    const res: any = await api(`/quizzes/${quizId.value}/cbt/violation`, {
      method: 'POST',
      body: { violation_type: type },
    })

    if (res?.terminated) {
      isFinished.value = true
      finishedReason.value = 'violation'
      examResult.value = res.data
      if (timerInterval) clearInterval(timerInterval)
      isViolationModalOpen.value = true
      violationMessage.value = res.message || `Ujian dihentikan otomatis karena Anda telah meninggalkan layar ujian sebanyak ${maxViolations.value} kali.`
      cleanupSecurityListeners()
      exitFullscreen()
    } else {
      violationCount.value = res.violation_count || (violationCount.value + 1)
      maxViolations.value = res.max_violations || maxViolations.value
      violationMessage.value = res.message || `Peringatan fokus ujian! Anda terdeteksi meninggalkan layar ujian (${violationCount.value}/${maxViolations.value}).`
      isViolationModalOpen.value = true
    }
  } catch (e) {
    console.error('Gagal mencatat violation', e)
  }
}

const handleVisibilityChange = () => {
  if (document.hidden && isStarted.value && !isFinished.value) {
    recordSecurityViolation('visibilitychange')
  }
}

const handleWindowBlur = () => {
  if (isStarted.value && !isFinished.value) {
    recordSecurityViolation('blur')
  }
}

const handleKeyDown = (e: KeyboardEvent) => {
  if (
    e.key === 'F12' ||
    (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j' || e.key === 'C' || e.key === 'c')) ||
    (e.ctrlKey && (e.key === 'u' || e.key === 'U'))
  ) {
    e.preventDefault()
    e.stopPropagation()
    return false
  }
}

const setupSecurityListeners = () => {
  document.addEventListener('fullscreenchange', handleFullscreenChange)
  document.addEventListener('visibilitychange', handleVisibilityChange)
  window.addEventListener('blur', handleWindowBlur)
  window.addEventListener('keydown', handleKeyDown)
}

const cleanupSecurityListeners = () => {
  document.removeEventListener('fullscreenchange', handleFullscreenChange)
  document.removeEventListener('visibilitychange', handleVisibilityChange)
  window.removeEventListener('blur', handleWindowBlur)
  window.removeEventListener('keydown', handleKeyDown)
}

onMounted(() => {
  initExam()
})

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval)
  if (autosaveTimeout) clearTimeout(autosaveTimeout)
  cleanupSecurityListeners()
})
</script>

<template>
  <div
    class="min-h-screen bg-slate-100 flex flex-col font-sans text-slate-800 select-none"
    @contextmenu.prevent
    @copy.prevent
    @cut.prevent
  >
    <!-- MODAL INPUT TOKEN SEBELUM UJIAN DIMULAI -->
    <div
      v-if="!loading && !isStarted && !isFinished"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm"
    >
      <div class="bg-white rounded-2xl p-6 sm:p-8 max-w-md w-full shadow-2xl border border-slate-200 space-y-6 text-center">
        <div class="w-14 h-14 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center justify-center mx-auto">
          <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>

        <div>
          <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 text-[10px] font-extrabold uppercase tracking-wider">
            SESI UJIAN TERLINDUNGI
          </span>
          <h2 class="text-xl font-extrabold text-slate-900 mt-2">
            {{ quizTitle || 'Ruang Ujian Mandiri CBT' }}
          </h2>
          <p class="text-xs text-slate-500 mt-1 leading-relaxed">
            Sesi ujian ini menggunakan pengawasan layar fokus dan token akses guru. Pastikan Anda siap sebelum memulai.
          </p>
        </div>

        <div class="bg-slate-50 rounded-xl p-4 border border-slate-200/80 space-y-2 text-left text-xs text-slate-600">
          <div class="flex items-center justify-between">
            <span>Durasi Pengerjaan:</span>
            <b class="text-slate-900 font-mono">{{ examInfo?.duration_minutes || 60 }} Menit</b>
          </div>
          <div class="flex items-center justify-between">
            <span>Batas Toleransi Layar:</span>
            <b class="text-slate-900 font-mono">{{ maxViolations }}x Pindah Tab</b>
          </div>
          <div v-if="examInfo?.available_until" class="flex items-center justify-between">
            <span>Batas Sesi Ditutup:</span>
            <b class="text-slate-900 font-mono">{{ new Date(examInfo.available_until).toLocaleTimeString('id-ID') }} WIB</b>
          </div>
        </div>

        <div v-if="tokenError" class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs font-semibold text-rose-700 text-left">
          {{ tokenError }}
        </div>

        <form @submit.prevent="startExam(inputToken)" class="space-y-4">
          <div v-if="examInfo?.requires_token">
            <label class="block text-xs font-bold text-slate-700 mb-1 text-left">
              Masukkan Token Ujian Pengawas:
            </label>
            <input
              v-model="inputToken"
              type="text"
              maxlength="10"
              placeholder="Contoh: 8AK3E9"
              required
              class="w-full px-4 py-3 rounded-xl border-2 border-slate-300 focus:border-emerald-600 focus:outline-none font-mono text-center text-xl font-black tracking-[0.3em] uppercase bg-slate-50 focus:bg-white transition"
            />
          </div>

          <div class="pt-2 space-y-2">
            <button
              type="submit"
              :disabled="startingExam"
              class="w-full py-3 rounded-xl bg-emerald-600 text-white font-extrabold text-xs hover:bg-emerald-700 disabled:opacity-50 transition shadow-xs"
            >
              {{ startingExam ? 'Memverifikasi Sesi...' : 'Mulai Ujian Sekarang' }}
            </button>
            <NuxtLink
              to="/student/quizzes"
              class="block w-full py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 transition"
            >
              Kembali ke Daftar Ujian
            </NuxtLink>
          </div>
        </form>
      </div>
    </div>

    <!-- OVERLAY PERINGATAN KELUAR DARI FULLSCREEN -->
    <div
      v-if="showFullscreenPrompt && isStarted && !isFinished"
      class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-md"
    >
      <div class="bg-white rounded-2xl p-6 sm:p-8 max-w-md w-full text-center space-y-4 shadow-2xl border border-rose-200">
        <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center mx-auto">
          <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <h3 class="text-lg font-black text-slate-900">Kembali ke Mode Layar Penuh</h3>
        <p class="text-xs text-slate-600 leading-relaxed">
          Ujian ini mewajibkan mode layar penuh (fullscreen) agar fokus pengerjaan tetap terjaga. Keluar dari fullscreen dihitung sebagai pelanggaran fokus.
        </p>
        <button
          @click="enterFullscreen"
          class="w-full py-3 rounded-xl bg-slate-900 text-white font-extrabold text-xs shadow-xs hover:bg-slate-800 transition"
        >
          Masuk Kembali ke Layar Penuh
        </button>
      </div>
    </div>

    <!-- MODAL PERINGATAN PELANGGARAN PINDAH TAB -->
    <div
      v-if="isViolationModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
    >
      <div class="bg-white rounded-2xl p-6 sm:p-8 max-w-md w-full text-center space-y-4 shadow-2xl border border-rose-300">
        <div class="w-14 h-14 rounded-2xl bg-rose-100 text-rose-700 flex items-center justify-center mx-auto">
          <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h3 class="text-lg font-black text-slate-900">
          {{ isFinished && finishedReason === 'violation' ? 'Ujian Dihentikan Otomatis' : 'Peringatan Fokus Ujian' }}
        </h3>
        <p class="text-xs text-slate-700 leading-relaxed">
          {{ violationMessage }}
        </p>

        <div v-if="!isFinished" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs font-bold text-rose-800">
          Sisa toleransi: {{ Math.max(0, maxViolations - violationCount) }} kesempatan lagi.
        </div>

        <div class="pt-2">
          <button
            v-if="!isFinished"
            @click="isViolationModalOpen = false; enterFullscreen()"
            class="w-full py-3 rounded-xl bg-slate-900 text-white font-extrabold text-xs hover:bg-slate-800 transition"
          >
            Saya Mengerti & Kembali Mengerjakan
          </button>
          <button
            v-else
            @click="isViolationModalOpen = false"
            class="w-full py-3 rounded-xl bg-slate-900 text-white font-extrabold text-xs hover:bg-slate-800 transition"
          >
            Lihat Ringkasan Hasil
          </button>
        </div>
      </div>
    </div>

    <!-- TOP HEADER NAVIGATION BAR -->
    <header class="h-16 bg-white border-b border-slate-200 px-4 sm:px-6 flex items-center justify-between sticky top-0 z-30 shadow-xs">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white font-extrabold flex items-center justify-center text-xs">
          CBT
        </div>
        <div>
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Sesi Ujian Aman</span>
          <h1 class="text-sm font-extrabold text-slate-900 truncate max-w-xs sm:max-w-md">{{ quizTitle || 'Ujian Online' }}</h1>
        </div>
      </div>

      <!-- Center Countdown Timer & Quota Pelanggaran -->
      <div v-if="!isFinished && isStarted" class="flex items-center gap-2">
        <div :class="[
          'px-3.5 py-1.5 rounded-xl font-mono text-sm sm:text-base font-extrabold flex items-center gap-2 border shadow-xs',
          remainingSeconds < 300
            ? 'bg-rose-50 border-rose-200 text-rose-700 animate-pulse'
            : 'bg-slate-50 border-slate-200 text-slate-800'
        ]">
          <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{{ formattedTime }}</span>
        </div>

        <span
          :class="[
            'hidden sm:inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-[11px] font-extrabold border',
            violationCount > 0 ? 'bg-rose-50 border-rose-300 text-rose-700' : 'bg-slate-100 border-slate-200 text-slate-600'
          ]"
        >
          Pelanggaran: {{ violationCount }}/{{ maxViolations }}
        </span>
      </div>

      <!-- Right Autosave & Actions -->
      <div class="flex items-center gap-2 sm:gap-3">
        <span class="text-[11px] text-slate-400 font-medium hidden lg:inline-block">
          {{ isSaving ? 'Menyimpan...' : lastSaved }}
        </span>

        <button
          v-if="!isFinished && isStarted && !isFullscreen"
          @click="enterFullscreen"
          class="p-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 transition"
          title="Layar Penuh"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
          </svg>
        </button>

        <button
          v-if="!isFinished && isStarted"
          @click="isConfirmModalOpen = true"
          class="px-3.5 sm:px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold shadow-xs hover:bg-emerald-700 transition"
        >
          Selesaikan Ujian
        </button>
      </div>
    </header>

    <!-- Finished Screen -->
    <main v-if="isFinished" class="flex-1 flex items-center justify-center p-6">
      <div class="bg-white rounded-2xl p-8 max-w-md w-full text-center shadow-xl border border-slate-200 space-y-4">
        <div :class="[
          'w-14 h-14 rounded-2xl flex items-center justify-center mx-auto',
          finishedReason === 'violation' ? 'bg-rose-100 text-rose-700 border border-rose-200' : 'bg-emerald-100 text-emerald-700 border border-emerald-200'
        ]">
          <svg v-if="finishedReason === 'violation'" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <svg v-else class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>

        <h2 class="text-xl font-extrabold text-slate-900">
          {{ finishedReason === 'violation' ? 'Ujian Dihentikan Sistem' : 'Ujian Telah Selesai' }}
        </h2>
        
        <p class="text-xs text-slate-500 leading-relaxed">
          <span v-if="finishedReason === 'violation'" class="text-rose-600 font-semibold block">
            Ujian dihentikan karena Anda telah melebihi batas maksimal perpindahan layar fokus ({{ maxViolations }}x).
          </span>
          <span v-else-if="finishedReason === 'timeout'" class="text-amber-700 font-semibold block">
            Waktu sesi ujian telah habis dan jawaban disimpan otomatis.
          </span>
          <span v-else>
            Seluruh jawaban Anda telah berhasil terkirim ke sistem penilaian guru.
          </span>
        </p>

        <div v-if="examResult?.score_pg !== undefined" class="p-4 rounded-xl bg-slate-50 border border-slate-200">
          <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Skor Pilihan Ganda</span>
          <span class="text-4xl font-extrabold text-slate-900 block mt-1">{{ examResult.score_pg }}</span>
          <p v-if="examResult.has_essay" class="text-[11px] text-slate-500 mt-1 italic">
            * Jawaban esai sedang menunggu pemeriksaan oleh guru.
          </p>
        </div>

        <div v-if="examResult?.breakdown?.length" class="text-left space-y-2">
          <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Rincian per soal</span>
          <div class="grid grid-cols-5 gap-2">
            <div
              v-for="b in examResult.breakdown"
              :key="b.number"
              :class="[
                'rounded-lg border px-1 py-1.5 text-center',
                b.status === 'benar' ? 'bg-emerald-50 border-emerald-300 text-emerald-900'
                  : b.status === 'sebagian' ? 'bg-amber-50 border-amber-300 text-amber-900'
                  : b.status === 'salah' ? 'bg-rose-50 border-rose-300 text-rose-900'
                  : 'bg-slate-50 border-slate-200 text-slate-600'
              ]"
            >
              <span class="block font-mono text-xs font-extrabold">{{ b.number }}</span>
              <span class="block text-[10px] font-semibold">{{ b.status ? statusLabel[b.status] : 'Dinilai' }}</span>
            </div>
          </div>
        </div>

        <NuxtLink
          to="/student/quizzes"
          class="block w-full py-2.5 rounded-xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition"
        >
          Kembali ke Daftar Ujian
        </NuxtLink>
      </div>
    </main>

    <!-- Active Exam Working Area -->
    <main v-else-if="!loading && isStarted && questions.length" class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Main Question Container -->
      <div class="lg:col-span-3 space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6 min-h-[420px] flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
              <span class="px-3 py-1 rounded-lg bg-slate-100 font-mono text-xs font-bold text-slate-800">
                Soal {{ currentQuestion?.number }} / {{ questions.length }}
              </span>
              <span class="text-xs font-semibold text-slate-500">
                Bobot: {{ currentQuestion?.weight }} Poin
              </span>
            </div>

            <div class="text-base font-medium text-slate-900 leading-relaxed whitespace-pre-wrap">
              {{ currentQuestion?.question }}
            </div>

            <component
              v-if="currentQuestion"
              :is="answerComponent(currentQuestion.type)"
              :question="currentQuestion"
              :model-value="answers[String(currentQuestion?.number)]"
              @update:model-value="setAnswer"
            />
          </div>

          <!-- Bottom Navigation Controls -->
          <div class="flex items-center justify-between pt-6 border-t border-slate-100 gap-2">
            <button
              :disabled="currentIndex === 0"
              @click="currentIndex--"
              class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-40 transition"
            >
              Soal Sebelumnya
            </button>

            <button
              @click="toggleDoubtful"
              :class="[
                'px-4 py-2 rounded-xl text-xs font-bold border transition flex items-center gap-1.5',
                doubtful[String(currentQuestion?.number)]
                  ? 'bg-amber-100 border-amber-300 text-amber-900'
                  : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'
              ]"
            >
              <svg class="w-4 h-4" :class="doubtful[String(currentQuestion?.number)] ? 'text-amber-700' : 'text-slate-400'" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
              </svg>
              <span>{{ doubtful[String(currentQuestion?.number)] ? 'Ragu-ragu' : 'Tandai Ragu' }}</span>
            </button>

            <button
              :disabled="currentIndex === questions.length - 1"
              @click="currentIndex++"
              class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 disabled:opacity-40 transition"
            >
              Soal Selanjutnya
            </button>
          </div>
        </div>
      </div>
    </main>

    <!-- Modal Konfirmasi Selesaikan Ujian -->
    <div
      v-if="isConfirmModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
    >
      <div class="bg-white rounded-2xl max-w-sm w-full p-6 text-center space-y-4 shadow-2xl border border-slate-200">
        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center mx-auto">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <h3 class="font-extrabold text-slate-900 text-base">Selesaikan Ujian Sekarang?</h3>
        <p class="text-xs text-slate-500 leading-relaxed">
          Pastikan Anda telah memeriksa kembali seluruh jawaban. Setelah dikumpulkan, Anda tidak dapat mengubah jawaban lagi.
        </p>

        <div class="flex items-center justify-center gap-2 pt-2">
          <button
            @click="isConfirmModalOpen = false"
            class="flex-1 py-2.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-50"
          >
            Batal
          </button>
          <button
            @click="finishExam"
            :disabled="submitting"
            class="flex-1 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 disabled:opacity-50"
          >
            {{ submitting ? 'Mengirim...' : 'Ya, Kumpulkan' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
