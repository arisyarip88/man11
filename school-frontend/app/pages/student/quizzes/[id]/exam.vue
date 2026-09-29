<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'

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
  type: 'pg' | 'pg_kompleks' | 'esai' | 'isian'
  question: string
  options: QuestionOption[]
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

const startExam = async () => {
  loading.value = true
  try {
    const res = await api<any>(`/quizzes/${quizId.value}/cbt/start`, { method: 'POST' })
    const data = res?.data
    if (data) {
      quizTitle.value = data.quiz_title
      remainingSeconds.value = data.remaining_seconds || (data.duration_minutes * 60)
      questions.value = data.questions || []
      answers.value = data.saved_answers || {}

      if (data.is_graded || (data.status === 'submitted' && data.remaining_seconds <= 0)) {
        isFinished.value = true
        examResult.value = {
          score_pg: data.score,
          is_graded: data.is_graded,
        }
      } else {
        startTimer()
      }
    }
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal memulai ujian CBT')
  } finally {
    loading.value = false
  }
}

const startTimer = () => {
  if (timerInterval) clearInterval(timerInterval)
  timerInterval = setInterval(() => {
    if (remainingSeconds.value > 0) {
      remainingSeconds.value--
    } else {
      clearInterval(timerInterval)
      autoSubmitExam()
    }
  }, 1000)
}

const formattedTime = computed(() => {
  const h = Math.floor(remainingSeconds.value / 3600)
  const m = Math.floor((remainingSeconds.value % 3600) / 60)
  const s = remainingSeconds.value % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

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

const selectAnswer = (key: string) => {
  if (!currentQuestion.value) return
  const qNum = String(currentQuestion.value.number)
  answers.value[qNum] = key
  triggerAutosave()
}

const toggleDoubtful = () => {
  if (!currentQuestion.value) return
  const qNum = String(currentQuestion.value.number)
  doubtful.value[qNum] = !doubtful.value[qNum]
}

const finishExam = async () => {
  submitting.value = true
  try {
    const res = await api<any>(`/quizzes/${quizId.value}/cbt/finish`, {
      method: 'POST',
      body: { answers: answers.value },
    })
    isFinished.value = true
    examResult.value = res?.data
    if (timerInterval) clearInterval(timerInterval)
    isConfirmModalOpen.value = false
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal mengumpulkan ujian.')
  } finally {
    submitting.value = false
  }
}

const autoSubmitExam = () => {
  alert('Waktu ujian telah habis! Sistem mengumpulkan jawaban Anda secara otomatis.')
  finishExam()
}

onMounted(() => {
  startExam()
})

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval)
  if (autosaveTimeout) clearTimeout(autosaveTimeout)
})
</script>

<template>
  <div class="min-h-screen bg-slate-100 flex flex-col font-sans text-slate-800 select-none">
    <!-- Top Header Navigation Bar (Distraction Free) -->
    <header class="h-16 bg-white border-b border-slate-200 px-6 flex items-center justify-between sticky top-0 z-30 shadow-xs">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white font-extrabold flex items-center justify-center text-xs">
          CBT
        </div>
        <div>
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Ruang Ujian Mandiri</span>
          <h1 class="text-sm font-extrabold text-slate-900 truncate max-w-md">{{ quizTitle || 'Ujian Online' }}</h1>
        </div>
      </div>

      <!-- Center Countdown Timer -->
      <div v-if="!isFinished && !loading" class="flex items-center gap-2">
        <div :class="[
          'px-4 py-1.5 rounded-xl font-mono text-base font-extrabold flex items-center gap-2 border shadow-xs',
          remainingSeconds < 300
            ? 'bg-rose-50 border-rose-200 text-rose-700 animate-pulse'
            : 'bg-slate-50 border-slate-200 text-slate-800'
        ]">
          <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{{ formattedTime }}</span>
        </div>
      </div>

      <!-- Right Autosave & Submit Button -->
      <div class="flex items-center gap-3">
        <span class="text-[11px] text-slate-400 hidden sm:inline-block">
          {{ isSaving ? 'Menyimpan jawaban...' : lastSaved }}
        </span>

        <button
          v-if="!isFinished && !loading"
          @click="isConfirmModalOpen = true"
          class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold shadow-md shadow-emerald-600/20 hover:bg-emerald-700 transition"
        >
          Selesaikan Ujian
        </button>
      </div>
    </header>

    <!-- Finished Screen -->
    <main v-if="isFinished" class="flex-1 flex items-center justify-center p-6">
      <div class="bg-white rounded-3xl p-8 max-w-md w-full text-center shadow-xl border border-slate-200/80 space-y-4">
        <div class="w-16 h-16 rounded-3xl bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto text-2xl font-bold">
          ✓
        </div>
        <h2 class="text-xl font-extrabold text-slate-900">Ujian Telah Diselesaikan!</h2>
        <p class="text-xs text-slate-500 leading-relaxed">
          Seluruh jawaban Anda telah berhasil terkirim ke sistem penilaian guru MAN 11 Jakarta.
        </p>

        <div v-if="examResult?.score_pg !== undefined" class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200">
          <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider block">Skor Pilihan Ganda</span>
          <span class="text-4xl font-extrabold text-emerald-900 block mt-1">{{ examResult.score_pg }}</span>
          <p v-if="examResult.has_essay" class="text-[11px] text-emerald-700 mt-1 italic">
            * Jawaban esai sedang menunggu pemeriksaan oleh guru.
          </p>
        </div>

        <NuxtLink
          to="/student/dashboard?tab=tasks"
          class="block w-full py-2.5 rounded-xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition"
        >
          Kembali ke Dashboard Belajar Siswa
        </NuxtLink>
      </div>
    </main>

    <!-- Active Exam Working Area -->
    <main v-else-if="!loading && questions.length" class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Main Question Container (Col 3) -->
      <div class="lg:col-span-3 space-y-6">
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-6 min-h-[420px] flex flex-col justify-between">
          <div>
            <!-- Question Metadata -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
              <span class="px-3 py-1 rounded-xl bg-slate-100 font-extrabold text-xs text-slate-800">
                Soal Nomor {{ currentQuestion.number }} dari {{ questions.length }}
              </span>
              <span class="text-xs font-semibold text-slate-400">
                Bobot: {{ currentQuestion.weight }} Poin
              </span>
            </div>

            <!-- Question Text -->
            <div class="text-base font-semibold text-slate-900 leading-relaxed whitespace-pre-wrap">
              {{ currentQuestion.question }}
            </div>

            <!-- Options (For Multiple Choice) -->
            <div v-if="currentQuestion.options && currentQuestion.options.length" class="mt-6 space-y-3">
              <button
                type="button"
                v-for="opt in currentQuestion.options"
                :key="opt.key"
                @click="selectAnswer(opt.key)"
                :class="[
                  'w-full text-left p-4 rounded-2xl border transition flex items-center gap-3.5',
                  answers[String(currentQuestion.number)] === opt.key
                    ? 'border-emerald-600 bg-emerald-50/70 text-emerald-950 font-bold ring-2 ring-emerald-500/20 shadow-xs'
                    : 'border-slate-200 bg-white hover:bg-slate-50 text-slate-700'
                ]"
              >
                <span
                  :class="[
                    'w-7 h-7 rounded-xl font-mono text-xs font-extrabold flex items-center justify-center shrink-0 transition',
                    answers[String(currentQuestion.number)] === opt.key
                      ? 'bg-emerald-600 text-white'
                      : 'bg-slate-100 text-slate-600'
                  ]"
                >
                  {{ opt.key }}
                </span>
                <span class="text-sm leading-relaxed">{{ opt.text }}</span>
              </button>
            </div>

            <!-- Textarea (For Essay) -->
            <div v-else class="mt-6">
              <label class="block font-bold text-xs text-slate-700 mb-1">Tulis Jawaban Uraian Anda di sini:</label>
              <textarea
                v-model="answers[String(currentQuestion.number)]"
                @input="triggerAutosave"
                rows="6"
                placeholder="Ketik uraian jawaban secara jelas dan terperinci..."
                class="w-full p-4 rounded-2xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm"
              ></textarea>
            </div>
          </div>

          <!-- Bottom Navigation Controls -->
          <div class="flex items-center justify-between pt-6 border-t border-slate-100 gap-2">
            <button
              :disabled="currentIndex === 0"
              @click="currentIndex--"
              class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-40 transition"
            >
              ← Soal Sebelumnya
            </button>

            <button
              @click="toggleDoubtful"
              :class="[
                'px-4 py-2 rounded-xl text-xs font-bold border transition',
                doubtful[String(currentQuestion.number)]
                  ? 'bg-amber-100 border-amber-300 text-amber-900'
                  : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
              ]"
            >
              {{ doubtful[String(currentQuestion.number)] ? '★ Ragu-ragu' : '☆ Tandai Ragu' }}
            </button>

            <button
              :disabled="currentIndex === questions.length - 1"
              @click="currentIndex++"
              class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 disabled:opacity-40 transition"
            >
              Soal Selanjutnya →
            </button>
          </div>
        </div>
      </div>

      <!-- Right Sidebar: Number Grid Navigation (Col 1) -->
      <div class="space-y-4">
        <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs">
          <h3 class="font-extrabold text-xs text-slate-900 mb-3 uppercase tracking-wider">Navigasi Nomor Soal</h3>
          
          <div class="grid grid-cols-5 gap-2">
            <button
              v-for="(q, idx) in questions"
              :key="q.number"
              @click="currentIndex = idx"
              :class="[
                'h-10 rounded-xl font-mono text-xs font-bold transition flex items-center justify-center border',
                currentIndex === idx ? 'ring-2 ring-emerald-500 ring-offset-2' : '',
                doubtful[String(q.number)]
                  ? 'bg-amber-200 border-amber-400 text-amber-950'
                  : answers[String(q.number)]
                    ? 'bg-emerald-600 border-emerald-600 text-white'
                    : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'
              ]"
            >
              {{ q.number }}
            </button>
          </div>

          <!-- Color Legend -->
          <div class="mt-5 pt-4 border-t border-slate-100 space-y-2 text-[11px] text-slate-500">
            <div class="flex items-center gap-2">
              <span class="w-3.5 h-3.5 rounded bg-emerald-600"></span>
              <span>Sudah Dijawab</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="w-3.5 h-3.5 rounded bg-amber-200 border border-amber-400"></span>
              <span>Ragu-ragu</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="w-3.5 h-3.5 rounded bg-slate-100 border border-slate-200"></span>
              <span>Belum Dijawab</span>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Modal Konfirmasi Selesaikan Ujian -->
    <div
      v-if="isConfirmModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in"
    >
      <div class="bg-white rounded-3xl max-w-sm w-full p-6 text-center space-y-4 shadow-2xl">
        <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center mx-auto text-xl font-bold">
          ⚠️
        </div>
        <h3 class="font-extrabold text-slate-900 text-base">Selesaikan Ujian Sekarang?</h3>
        <p class="text-xs text-slate-500 leading-relaxed">
          Pastikan Anda telah memeriksa kembali seluruh jawaban. Setelah dikumpulkan, Anda tidak dapat mengubah jawaban lagi.
        </p>

        <div class="flex items-center justify-center gap-2 pt-2">
          <button
            @click="isConfirmModalOpen = false"
            class="flex-1 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50"
          >
            Batal
          </button>
          <button
            @click="finishExam"
            :disabled="submitting"
            class="flex-1 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 disabled:opacity-50"
          >
            {{ submitting ? 'Mengirim...' : 'Ya, Kumpulkan' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
