<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

definePageMeta({ layout: 'teacher', middleware: 'auth' })

const route = useRoute()
const quizId = computed(() => route.params.id)

const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'

interface SubmissionItem {
  id: number
  quiz_id: number
  student_id: number
  student?: { id: number; name: string; nisn: string }
  started_at: string | null
  submitted_at: string | null
  score: number | null
  score_pg: number | null
  score_essay: number | null
  is_graded: boolean
  teacher_feedback: string | null
  answers: Record<string, any> | null
  status: string
}

interface QuizDetail {
  id: number
  title: string
  quiz_type: string
  questions_data: any[] | null
  meet?: { course?: { id: number; subject?: { name: string } } }
}

const loading = ref(true)
const actionLoading = ref(false)
const quiz = ref<QuizDetail | null>(null)
const submissions = ref<SubmissionItem[]>([])

// Grading Modal
const isGradingModalOpen = ref(false)
const activeSubmission = ref<SubmissionItem | null>(null)
const essayScoreInput = ref<number>(0)
const feedbackInput = ref<string>('')
const gradeError = ref('')

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

const fetchData = async () => {
  loading.value = true
  try {
    const [quizRes, subRes] = await Promise.all([
      api<any>(`/quizzes/${quizId.value}`),
      api<any>(`/submissions?quiz_id=${quizId.value}`)
    ])
    quiz.value = quizRes?.data || quizRes || null
    submissions.value = subRes?.data || subRes || []
  } catch (err: any) {
    console.error('Gagal memuat koreksi kuis:', err)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchData()
})

const pendingCount = computed(() => submissions.value.filter(s => !s.is_graded).length)
const gradedCount = computed(() => submissions.value.filter(s => s.is_graded).length)

const openGradingModal = (sub: SubmissionItem) => {
  activeSubmission.value = sub
  essayScoreInput.value = Number(sub.score_essay ?? 80)
  feedbackInput.value = sub.teacher_feedback || ''
  gradeError.value = ''
  isGradingModalOpen.value = true
}

const submitGrading = async () => {
  if (!activeSubmission.value) return
  actionLoading.value = true
  gradeError.value = ''

  try {
    await api(`/submissions/${activeSubmission.value.id}/grade-cbt`, {
      method: 'PUT',
      body: {
        score_essay: essayScoreInput.value,
        teacher_feedback: feedbackInput.value,
      },
    })
    isGradingModalOpen.value = false
    await fetchData()
  } catch (err: any) {
    gradeError.value = err?.data?.message || 'Gagal menyimpan penilaian.'
  } finally {
    actionLoading.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-2">
      <NuxtLink
        to="/teacher/classes"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-600 hover:bg-slate-50 hover:text-emerald-700 transition"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Kembali ke Kelas
      </NuxtLink>
      <span class="text-slate-300">/</span>
      <span class="text-xs font-semibold text-slate-500">Pemeriksaan & Koreksi CBT</span>
    </div>

    <!-- Header Info -->
    <div class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <span class="px-2.5 py-0.5 rounded-md bg-emerald-50 border border-emerald-200/60 font-mono text-[10px] font-extrabold text-emerald-800">
          Kuis / Asesmen #{{ quizId }}
        </span>
        <h1 class="text-xl font-extrabold text-slate-900 tracking-tight mt-1">
          {{ quiz?.title || 'Koreksi Hasil Ujian' }}
        </h1>
        <p class="text-xs text-slate-500 mt-1">
          Periksa kiriman ujian siswa, tinjau skor otomatis pilihan ganda, dan berikan nilai pada butir soal esai.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <div class="px-4 py-2 rounded-2xl bg-amber-50 border border-amber-200 text-center">
          <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800 block">Perlu Dinilai</span>
          <span class="text-xl font-extrabold text-amber-900">{{ pendingCount }}</span>
        </div>
        <div class="px-4 py-2 rounded-2xl bg-emerald-50 border border-emerald-200 text-center">
          <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-800 block">Selesai</span>
          <span class="text-xl font-extrabold text-emerald-900">{{ gradedCount }}</span>
        </div>
      </div>
    </div>

    <!-- Submissions List -->
    <div v-if="loading" class="p-16 text-center text-xs text-slate-400 bg-white rounded-3xl border border-slate-100">
      <svg class="animate-spin h-6 w-6 text-emerald-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      Memuat kiriman ujian siswa...
    </div>

    <div v-else-if="submissions.length === 0" class="p-12 text-center bg-white rounded-3xl border border-slate-200/80">
      <h3 class="text-sm font-bold text-slate-800">Belum ada siswa yang mengumpulkan ujian</h3>
      <p class="text-xs text-slate-400 mt-1">Kiriman jawaban akan otomatis masuk ke halaman ini saat siswa menyelesaikan ujian.</p>
    </div>

    <div v-else class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
      <table class="w-full text-left border-collapse text-xs">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
            <th class="px-5 py-3.5">Nama Siswa</th>
            <th class="px-5 py-3.5">Waktu Selesai</th>
            <th class="px-5 py-3.5 text-center">Skor PG</th>
            <th class="px-5 py-3.5 text-center">Skor Esai</th>
            <th class="px-5 py-3.5 text-center">Total Nilai</th>
            <th class="px-5 py-3.5 text-center">Status</th>
            <th class="px-5 py-3.5 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="sub in submissions" :key="sub.id" class="hover:bg-slate-50/60 transition">
            <td class="px-5 py-4">
              <p class="font-bold text-slate-900">{{ sub.student?.name || `Siswa #${sub.student_id}` }}</p>
              <p class="text-[10px] text-slate-400 font-mono">{{ sub.student?.nisn }}</p>
            </td>
            <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
              {{ sub.submitted_at ? new Date(sub.submitted_at).toLocaleString('id-ID') : '-' }}
            </td>
            <td class="px-5 py-4 text-center font-extrabold text-slate-800">
              {{ sub.score_pg ?? '-' }}
            </td>
            <td class="px-5 py-4 text-center font-extrabold text-slate-800">
              {{ sub.score_essay ?? '-' }}
            </td>
            <td class="px-5 py-4 text-center font-extrabold text-emerald-800 text-sm">
              {{ sub.score ?? '-' }}
            </td>
            <td class="px-5 py-4 text-center">
              <span
                v-if="sub.is_graded"
                class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800"
              >
                ✓ Selesai
              </span>
              <span
                v-else
                class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800"
              >
                Perlu Koreksi
              </span>
            </td>
            <td class="px-5 py-4 text-right">
              <button
                @click="openGradingModal(sub)"
                class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white font-bold text-xs transition"
              >
                {{ sub.is_graded ? 'Edit Nilai' : 'Koreksi Esai' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Form Koreksi Esai -->
    <div
      v-if="isGradingModalOpen && activeSubmission"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in"
    >
      <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-100 space-y-4 max-h-[90vh] flex flex-col justify-between">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="font-extrabold text-slate-900 text-sm">Koreksi & Penilaian Esai</h3>
            <p class="text-xs text-emerald-700 font-bold mt-0.5">{{ activeSubmission.student?.name }}</p>
          </div>
          <button @click="isGradingModalOpen = false" class="text-slate-400 hover:text-slate-600">
            ✕
          </button>
        </div>

        <div v-if="gradeError" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700">
          {{ gradeError }}
        </div>

        <div class="flex-1 overflow-y-auto space-y-4 text-xs pr-1">
          <!-- Skor Pilihan Ganda Info -->
          <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between">
            <span class="font-bold text-slate-700">Skor Pilihan Ganda (Otomatis):</span>
            <span class="font-mono text-sm font-extrabold text-emerald-800">{{ activeSubmission.score_pg ?? 0 }}</span>
          </div>

          <!-- Jawaban Siswa per Butir Soal -->
          <div v-if="quiz?.questions_data" class="space-y-3">
            <div
              v-for="q in quiz.questions_data"
              :key="q.number"
              class="p-4 rounded-2xl border border-slate-200/80 space-y-2 bg-white"
            >
              <div class="flex items-center justify-between">
                <span class="font-extrabold text-slate-900">Soal {{ q.number }} ({{ q.type.toUpperCase() }})</span>
                <span class="text-[10px] text-slate-400 font-semibold">Bobot: {{ q.weight }} Poin</span>
              </div>
              <p class="text-slate-800 font-medium">{{ q.question }}</p>

              <!-- Jawaban Siswa -->
              <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Jawaban Siswa:</span>
                <p class="text-slate-900 font-semibold mt-0.5">
                  {{ activeSubmission.answers?.[String(q.number)] || '(Tidak dijawab)' }}
                </p>
              </div>

              <!-- Panduan Kunci / Rubrik jika ada -->
              <div v-if="q.correct_answer" class="text-[11px] text-amber-800 bg-amber-50/70 p-2 rounded-xl">
                <b>Panduan Kunci / Rubrik:</b> {{ q.correct_answer }}
              </div>
            </div>
          </div>

          <!-- Input Nilai Esai -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Nilai Uraian / Esai (Skala 0 - 100) *</label>
            <input
              v-model.number="essayScoreInput"
              type="number"
              min="0"
              max="100"
              step="1"
              required
              class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-sm font-bold"
            />
          </div>

          <!-- Feedback Catatan Guru -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Catatan / Umpan Balik Guru (Feedback)</label>
            <textarea
              v-model="feedbackInput"
              rows="3"
              placeholder="Berikan masukan atau catatan konstruktif untuk siswa..."
              class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            ></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
          <button
            type="button"
            @click="isGradingModalOpen = false"
            class="px-4 py-2 rounded-xl border border-slate-200 font-semibold text-slate-600 hover:bg-slate-50"
          >
            Batal
          </button>
          <button
            type="button"
            @click="submitGrading"
            :disabled="actionLoading"
            class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 disabled:opacity-50"
          >
            {{ actionLoading ? 'Menyimpan...' : 'Simpan Penilaian' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
