<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import BackButton from '~/components/ui/BackButton.vue'

definePageMeta({ layout: 'teacher', middleware: 'auth' })

const route = useRoute()
const quizId = computed(() => route.params.id)

const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const storageBase = config.public.storageBase || 'http://localhost:8000/storage'

const getFullUrl = (url: string | null | undefined) => {
  if (!url) return ''
  if (url.startsWith('http://') || url.startsWith('https://')) return url
  const cleanPath = url.replace(/^\/storage\/?/, '')
  return `${storageBase}/${cleanPath}`
}

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
  violation_count?: number
  finished_reason?: string | null
  file_path?: string | null
  file_url?: string | null
  submission_text?: string | null
}

interface QuizDetail {
  id: number
  title: string
  quiz_type: string
  duration_minutes?: number | null
  access_token?: string | null
  token_expires_at?: string | null
  available_from?: string | null
  available_until?: string | null
  max_violations?: number | null
  questions_data: any[] | null
  meet?: { course?: { id: number; subject?: { name: string } } }
}

const loading = ref(true)
const actionLoading = ref(false)
const quiz = ref<QuizDetail | null>(null)
const submissions = ref<SubmissionItem[]>([])

// Security State (F1)
const regeneratingToken = ref(false)
const tokenCopied = ref(false)
const isSecurityModalOpen = ref(false)
const securityForm = ref({
  access_token: '',
  available_from: '',
  available_until: '',
  max_violations: 3,
  duration_minutes: 60,
})
const securityError = ref('')
const securitySaving = ref(false)

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

    if (quiz.value) {
      securityForm.value = {
        access_token: quiz.value.access_token || '',
        available_from: quiz.value.available_from ? quiz.value.available_from.substring(0, 16) : '',
        available_until: quiz.value.available_until ? quiz.value.available_until.substring(0, 16) : '',
        max_violations: quiz.value.max_violations || 3,
        duration_minutes: quiz.value.duration_minutes || 60,
      }
    }
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

// F1: Regenerate Token
const handleRegenerateToken = async () => {
  regeneratingToken.value = true
  try {
    const res: any = await api(`/quizzes/${quizId.value}/token/regenerate`, {
      method: 'POST',
      body: { expires_hours: 4 },
    })
    if (res?.data?.access_token) {
      if (quiz.value) {
        quiz.value.access_token = res.data.access_token
        quiz.value.token_expires_at = res.data.token_expires_at
      }
      securityForm.value.access_token = res.data.access_token
    }
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal meregenerasi token ujian.')
  } finally {
    regeneratingToken.value = false
  }
}

const copyToken = () => {
  if (!quiz.value?.access_token) return
  navigator.clipboard.writeText(quiz.value.access_token)
  tokenCopied.value = true
  setTimeout(() => {
    tokenCopied.value = false
  }, 2000)
}

const openSecurityModal = () => {
  if (quiz.value) {
    securityForm.value = {
      access_token: quiz.value.access_token || '',
      available_from: quiz.value.available_from ? quiz.value.available_from.substring(0, 16) : '',
      available_until: quiz.value.available_until ? quiz.value.available_until.substring(0, 16) : '',
      max_violations: quiz.value.max_violations || 3,
      duration_minutes: quiz.value.duration_minutes || 60,
    }
  }
  securityError.value = ''
  isSecurityModalOpen.value = true
}

const saveSecuritySettings = async () => {
  securitySaving.value = true
  securityError.value = ''
  try {
    await api(`/quizzes/${quizId.value}/security-settings`, {
      method: 'PUT',
      body: {
        access_token: securityForm.value.access_token ? securityForm.value.access_token.toUpperCase() : null,
        available_from: securityForm.value.available_from || null,
        available_until: securityForm.value.available_until || null,
        max_violations: securityForm.value.max_violations,
        duration_minutes: securityForm.value.duration_minutes,
      },
    })
    isSecurityModalOpen.value = false
    await fetchData()
  } catch (err: any) {
    securityError.value = err?.data?.message || 'Gagal menyimpan pengaturan keamanan ujian.'
  } finally {
    securitySaving.value = false
  }
}

const openGradingModal = (sub: SubmissionItem) => {
  activeSubmission.value = sub
  essayScoreInput.value = sub.score !== null ? Number(sub.score) : (sub.score_essay !== null ? Number(sub.score_essay) : 85)
  feedbackInput.value = sub.teacher_feedback || ''
  gradeError.value = ''
  isGradingModalOpen.value = true
}

const submitGrading = async () => {
  if (!activeSubmission.value) return
  actionLoading.value = true
  gradeError.value = ''

  try {
    const isAssignment = Boolean(activeSubmission.value.file_path || activeSubmission.value.submission_text || quiz.value?.quiz_type === 'assignment')
    if (isAssignment) {
      await api(`/submissions/${activeSubmission.value.id}/grade`, {
        method: 'PUT',
        body: {
          score: essayScoreInput.value,
          teacher_feedback: feedbackInput.value || null,
        },
      })
    } else {
      await api(`/submissions/${activeSubmission.value.id}/grade-cbt`, {
        method: 'PUT',
        body: {
          score_essay: essayScoreInput.value,
          teacher_feedback: feedbackInput.value,
        },
      })
    }
    isGradingModalOpen.value = false
    await fetchData()
  } catch (err: any) {
    gradeError.value = err?.data?.message || err?.message || 'Gagal menyimpan penilaian.'
  } finally {
    actionLoading.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-2">
      <BackButton fallback="/teacher/quizzes" label="Kembali ke Manajemen Kuis" />
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
          Periksa kiriman ujian siswa, tinjau skor otomatis pilihan ganda, dan kelola token serta keamanan ujian aman.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          @click="openSecurityModal"
          class="px-3.5 py-2 rounded-2xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition flex items-center gap-1.5 shadow-2xs"
        >
          <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          Pengaturan Keamanan Ujian
        </button>

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

    <!-- F1: KARTU TOKEN UJIAN BESAR (RAMAH PROYEKTOR RUANG KELAS) -->
    <div class="relative overflow-hidden bg-radial from-slate-900 via-slate-950 to-slate-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl border border-slate-800 flex flex-col lg:flex-row items-center justify-between gap-6">
      <div class="space-y-2 text-center lg:text-left">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-extrabold">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
          TOKEN CBT PROYEKTOR KELAS
        </div>
        <h2 class="text-lg font-extrabold text-white">Token Akses Ujian Aktif</h2>
        <p class="text-xs text-slate-400 max-w-lg leading-relaxed">
          Bagikan token 6 karakter ini kepada siswa di proyektor kelas untuk memulai ujian. Token peka huruf kapital & anti karakter ambigu.
        </p>

        <!-- Jadwal & Batas Pelanggaran Ringkas -->
        <div class="flex flex-wrap items-center gap-3 pt-2 text-[11px] text-slate-300">
          <span class="flex items-center gap-1 bg-slate-800/80 px-2.5 py-1 rounded-xl border border-slate-700/60">
            ⏱ Durasi: <b>{{ quiz?.duration_minutes || 60 }} Menit</b>
          </span>
          <span class="flex items-center gap-1 bg-slate-800/80 px-2.5 py-1 rounded-xl border border-slate-700/60">
            🛡 Max Toleransi Layar: <b>{{ quiz?.max_violations || 3 }}x</b>
          </span>
          <span v-if="quiz?.available_from" class="flex items-center gap-1 bg-slate-800/80 px-2.5 py-1 rounded-xl border border-slate-700/60">
            📅 Buka: <b>{{ new Date(quiz.available_from).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' }) }}</b>
          </span>
          <span v-if="quiz?.available_until" class="flex items-center gap-1 bg-slate-800/80 px-2.5 py-1 rounded-xl border border-slate-700/60">
            ⛔ Tutup: <b>{{ new Date(quiz.available_until).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' }) }}</b>
          </span>
        </div>
      </div>

      <!-- Tampilan Angka Token Raksasa -->
      <div class="flex flex-col items-center gap-3 shrink-0">
        <div class="bg-slate-900/90 border-2 border-emerald-500/40 px-8 py-4 rounded-3xl shadow-inner text-center">
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">KODE TOKEN SISWA</span>
          <span class="font-mono text-4xl sm:text-5xl font-black tracking-[0.25em] text-emerald-400 select-all">
            {{ quiz?.access_token || 'BELUM AKTIF' }}
          </span>
          <span v-if="quiz?.token_expires_at" class="text-[10px] text-slate-400 block mt-1">
            Berlaku s/d: {{ new Date(quiz.token_expires_at).toLocaleTimeString('id-ID') }} WIB
          </span>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="copyToken"
            :disabled="!quiz?.access_token"
            class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs border border-slate-700 transition flex items-center gap-1.5"
          >
            <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
            <span>{{ tokenCopied ? 'Tersalin ✓' : 'Salin Token' }}</span>
          </button>

          <button
            @click="handleRegenerateToken"
            :disabled="regeneratingToken"
            class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-lg shadow-emerald-600/30 transition flex items-center gap-1.5 disabled:opacity-50"
          >
            <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': regeneratingToken }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>{{ regeneratingToken ? 'Mengacak...' : 'Segarkan Token' }}</span>
          </button>
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
            <th class="px-5 py-3.5 text-center">Fokus Layar</th>
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
            
            <!-- Kolom Fokus Layar & Pelanggaran (F1) -->
            <td class="px-5 py-4 text-center">
              <div class="flex flex-col items-center gap-1">
                <span
                  v-if="(sub.violation_count || 0) === 0"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600"
                >
                  Fokus Penuh
                </span>
                <span
                  v-else
                  :class="[
                    'px-2 py-0.5 rounded-full text-[10px] font-extrabold',
                    (sub.violation_count || 0) >= (quiz?.max_violations || 3)
                      ? 'bg-rose-100 text-rose-800 border border-rose-300'
                      : 'bg-amber-100 text-amber-800 border border-amber-300'
                  ]"
                >
                  {{ sub.violation_count }}x Pindah Layar
                </span>

                <!-- Alasan Selesai jika Timeout/Violation -->
                <span v-if="sub.finished_reason === 'violation'" class="text-[9px] font-extrabold text-rose-600 uppercase">
                  ⛔ Dihentikan Sistem
                </span>
                <span v-else-if="sub.finished_reason === 'timeout'" class="text-[9px] font-semibold text-amber-700">
                  ⏱ Waktu Habis
                </span>
              </div>
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
              <div class="flex items-center justify-end gap-2">
                <a
                  v-if="sub.file_url"
                  :href="sub.file_url"
                  target="_blank"
                  download
                  class="px-2.5 py-1.5 rounded-xl bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 font-bold text-xs transition flex items-center gap-1 shadow-2xs"
                  title="Unduh Berkas Tugas Siswa"
                >
                  <span>📄 Berkas</span>
                  <span>⤓</span>
                </a>
                <button
                  @click="openGradingModal(sub)"
                  class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white font-bold text-xs transition"
                >
                  {{ sub.is_graded ? 'Edit Nilai' : (sub.file_path ? 'Nilai Tugas' : 'Koreksi Esai') }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Pengaturan Keamanan Ujian (F1) -->
    <div
      v-if="isSecurityModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in"
    >
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="font-extrabold text-slate-900 text-sm flex items-center gap-2">
            <span>🛡</span> Pengaturan Keamanan & Jadwal CBT
          </h3>
          <button @click="isSecurityModalOpen = false" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <div v-if="securityError" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700 font-semibold">
          {{ securityError }}
        </div>

        <form @submit.prevent="saveSecuritySettings" class="space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Kode Token Kustom (Kosongkan jika tanpa token)</label>
            <input
              v-model="securityForm.access_token"
              type="text"
              placeholder="Contoh: BIO11X"
              maxlength="10"
              class="w-full px-3 py-2 rounded-xl border border-slate-200 font-mono font-bold uppercase focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Durasi Pengerjaan (Menit)</label>
              <input
                v-model.number="securityForm.duration_minutes"
                type="number"
                min="5"
                max="300"
                required
                class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-bold"
              />
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Max Toleransi Pelanggaran Layar</label>
              <input
                v-model.number="securityForm.max_violations"
                type="number"
                min="1"
                max="20"
                required
                class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-bold"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Waktu Dibuka (Available From)</label>
              <input
                v-model="securityForm.available_from"
                type="datetime-local"
                class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-xs"
              />
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Batas Sesi Ditutup (Available Until)</label>
              <input
                v-model="securityForm.available_until"
                type="datetime-local"
                class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-xs"
              />
            </div>
          </div>

          <p class="text-[11px] text-slate-400 italic">
            * Jika siswa meninggalkan layar ujian (ganti tab / blur) melebihi batas toleransi, sistem akan otomatis mengumpulkan ujian secara paksa.
          </p>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="isSecurityModalOpen = false"
              class="px-4 py-2 rounded-xl border border-slate-200 font-semibold text-slate-600 hover:bg-slate-50"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="securitySaving"
              class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 disabled:opacity-50"
            >
              {{ securitySaving ? 'Menyimpan...' : 'Simpan Pengaturan' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Form Koreksi Esai -->
    <div
      v-if="isGradingModalOpen && activeSubmission"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in"
    >
      <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-100 space-y-4 max-h-[90vh] flex flex-col justify-between">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="font-extrabold text-slate-900 text-sm">
              {{ activeSubmission.file_path || quiz?.quiz_type === 'assignment' ? 'Penilaian Tugas Mandiri Siswa' : 'Koreksi & Penilaian Esai Ujian' }}
            </h3>
            <p class="text-xs text-emerald-700 font-bold mt-0.5">{{ activeSubmission.student?.name }}</p>
          </div>
          <button @click="isGradingModalOpen = false" class="text-slate-400 hover:text-slate-600">
            ✕
          </button>
        </div>

        <div v-if="gradeError" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700 font-semibold">
          {{ gradeError }}
        </div>

        <div class="flex-1 overflow-y-auto space-y-4 text-xs pr-1">
          <!-- BLOK BERKAS TUGAS MANDIRI SISWA (JIKA ADA BERKAS ATAU TEKS TUGAS) -->
          <div v-if="activeSubmission.file_path || activeSubmission.submission_text || quiz?.quiz_type === 'assignment'" class="p-4 rounded-2xl bg-sky-50/70 border border-sky-200 space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-sky-900 flex items-center gap-1.5">
                <span class="w-6 h-6 rounded-lg bg-sky-200/80 text-sky-800 flex items-center justify-center font-bold text-xs">📂</span>
                <span>Berkas Dokumen Tugas Mandiri Siswa</span>
              </span>
              <span v-if="activeSubmission.submitted_at" class="text-[10px] text-sky-700 font-medium">
                Dikumpulkan: {{ new Date(activeSubmission.submitted_at).toLocaleString('id-ID') }}
              </span>
            </div>

            <!-- Kartu Unduh Berkas Siswa -->
            <div v-if="activeSubmission.file_url" class="flex items-center justify-between p-3 rounded-xl bg-white border border-sky-100 shadow-2xs">
              <div class="flex items-center gap-2.5 overflow-hidden">
                <span
                  class="w-9 h-9 rounded-xl flex items-center justify-center font-black text-xs shrink-0 text-white shadow-2xs"
                  :class="activeSubmission.file_path?.endsWith('.pdf') ? 'bg-rose-500' : 'bg-blue-600'"
                >
                  {{ activeSubmission.file_path?.endsWith('.pdf') ? 'PDF' : 'DOC' }}
                </span>
                <div class="truncate">
                  <p class="text-xs font-bold text-slate-800 truncate">
                    {{ activeSubmission.file_path ? activeSubmission.file_path.split('/').pop() : 'Berkas Tugas Siswa' }}
                  </p>
                  <p class="text-[10px] text-slate-400 font-medium">Format berkas tugas yang diunggah siswa</p>
                </div>
              </div>
              <a
                :href="activeSubmission.file_url"
                target="_blank"
                download
                class="px-3 py-1.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs transition flex items-center gap-1.5 shrink-0 shadow-xs"
              >
                <span>Unduh Berkas</span>
                <span>⤓</span>
              </a>
            </div>
            <div v-else class="p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-xs">
              Siswa tidak melampirkan berkas dokumen digital.
            </div>

            <!-- Catatan Pengantar Siswa jika ada -->
            <div v-if="activeSubmission.submission_text" class="p-3 rounded-xl bg-white border border-sky-100">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Catatan / Jawaban Teks Siswa:</span>
              <p class="text-xs text-slate-800 whitespace-pre-wrap font-medium leading-relaxed">
                {{ activeSubmission.submission_text }}
              </p>
            </div>
          </div>

          <!-- Skor Pilihan Ganda Info (Hanya jika CBT objektif) -->
          <div v-if="activeSubmission.score_pg !== null && activeSubmission.score_pg !== undefined && quiz?.quiz_type !== 'assignment'" class="p-3 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between">
            <span class="font-bold text-slate-700">Skor Otomatis (Objektif):</span>
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

              <!-- Lampiran Gambar Soal jika ada -->
              <div v-if="q.image_url" class="max-w-md my-2 rounded-2xl overflow-hidden border border-slate-200/90 bg-slate-50 p-2 shadow-2xs">
                <img
                  :src="getFullUrl(q.image_url)"
                  :alt="q.image_meta?.alt_text || 'Diagram soal'"
                  class="max-h-52 w-auto rounded-xl object-contain mx-auto"
                  loading="lazy"
                />
                <div v-if="q.image_meta?.alt_text" class="text-[11px] text-slate-500 text-center mt-1.5 italic font-medium">
                  {{ q.image_meta.alt_text }}
                </div>
              </div>

              <!-- Jawaban Siswa per Tipe Soal -->
              <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Jawaban Siswa:</span>
                
                <!-- 1. PG -->
                <div v-if="q.type === 'pg'">
                  <div v-if="activeSubmission?.answers?.[String(q.number)]" class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-emerald-600 text-white font-mono font-extrabold flex items-center justify-center text-xs">
                      {{ activeSubmission?.answers?.[String(q.number)] }}
                    </span>
                    <span class="text-xs font-semibold text-slate-700">
                      {{ (q.options || []).find((o: any) => o.key === activeSubmission?.answers?.[String(q.number)])?.text || '' }}
                    </span>
                  </div>
                  <span v-else class="text-slate-400 italic text-xs">(Tidak dijawab)</span>
                </div>

                <!-- 2. PG Kompleks -->
                <div v-else-if="q.type === 'pg_kompleks'">
                  <div v-if="Array.isArray(activeSubmission?.answers?.[String(q.number)]) && activeSubmission?.answers?.[String(q.number)]?.length" class="flex flex-wrap gap-1.5">
                    <span
                      v-for="k in activeSubmission?.answers?.[String(q.number)]"
                      :key="k"
                      class="px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-900 font-bold font-mono text-xs"
                    >
                      ✓ {{ k }}
                    </span>
                  </div>
                  <span v-else class="text-slate-400 italic text-xs">(Tidak dijawab)</span>
                </div>

                <!-- 3. Isian Singkat -->
                <div v-else-if="q.type === 'isian'">
                  <div v-if="activeSubmission?.answers?.[String(q.number)]" class="p-2 rounded-xl bg-white border border-slate-200 font-medium text-slate-900 text-xs">
                    "{{ activeSubmission?.answers?.[String(q.number)] }}"
                  </div>
                  <span v-else class="text-slate-400 italic text-xs">(Tidak dijawab)</span>
                  <div v-if="q.data?.accepted_answers?.length" class="text-[11px] text-amber-800 bg-amber-50/70 p-2 rounded-xl mt-1.5">
                    <b>Kunci diterima:</b> {{ q.data.accepted_answers.join(' | ') }}
                  </div>
                </div>

                <!-- 4. Menjodohkan -->
                <div v-else-if="q.type === 'menjodohkan'">
                  <div v-if="activeSubmission?.answers?.[String(q.number)] && Object.keys(activeSubmission?.answers?.[String(q.number)] || {}).length" class="space-y-1.5">
                    <div
                      v-for="pair in (q.data?.pairs || [])"
                      :key="pair.id"
                      class="p-2 rounded-xl bg-white border border-slate-200 text-xs flex items-center justify-between gap-2"
                    >
                      <span class="font-semibold text-slate-800">{{ pair.left }}</span>
                      <div class="flex items-center gap-1.5 font-bold">
                        <span :class="activeSubmission?.answers?.[String(q.number)]?.[pair.id] === pair.right ? 'text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200' : 'text-rose-700 bg-rose-50 px-2 py-0.5 rounded-md border border-rose-200'">
                          {{ activeSubmission?.answers?.[String(q.number)]?.[pair.id] || '(Kosong)' }}
                        </span>
                        <span v-if="activeSubmission?.answers?.[String(q.number)]?.[pair.id] !== pair.right" class="text-[10px] text-slate-400 font-normal">
                          (Kunci: {{ pair.right }})
                        </span>
                      </div>
                    </div>
                  </div>
                  <span v-else class="text-slate-400 italic text-xs">(Tidak dijawab)</span>
                </div>

                <!-- 5. Benar / Salah -->
                <div v-else-if="q.type === 'benar_salah'">
                  <div v-if="activeSubmission?.answers?.[String(q.number)] && Object.keys(activeSubmission?.answers?.[String(q.number)] || {}).length" class="space-y-1.5">
                    <div
                      v-for="st in (q.data?.statements || [])"
                      :key="st.id"
                      class="p-2 rounded-xl bg-white border border-slate-200 text-xs flex items-center justify-between gap-2"
                    >
                      <span class="font-medium text-slate-800">{{ st.text }}</span>
                      <div class="flex items-center gap-1.5">
                        <span
                          :class="[
                            'px-2 py-0.5 rounded-md font-extrabold text-[10px]',
                            activeSubmission?.answers?.[String(q.number)]?.[st.id] === st.answer
                              ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                              : 'bg-rose-100 text-rose-800 border border-rose-300'
                          ]"
                        >
                          Jawaban: {{ activeSubmission?.answers?.[String(q.number)]?.[st.id] === true ? 'BENAR' : (activeSubmission?.answers?.[String(q.number)]?.[st.id] === false ? 'SALAH' : '-') }}
                        </span>
                        <span v-if="activeSubmission?.answers?.[String(q.number)]?.[st.id] !== st.answer" class="text-[10px] text-slate-400">
                          (Kunci: {{ st.answer ? 'BENAR' : 'SALAH' }})
                        </span>
                      </div>
                    </div>
                  </div>
                  <span v-else class="text-slate-400 italic text-xs">(Tidak dijawab)</span>
                </div>

                <!-- 6. Esai -->
                <div v-else>
                  <div v-if="activeSubmission?.answers?.[String(q.number)]" class="p-2.5 rounded-xl bg-white border border-slate-200 font-medium text-slate-900 text-xs whitespace-pre-wrap leading-relaxed">
                    {{ activeSubmission?.answers?.[String(q.number)] }}
                  </div>
                  <span v-else class="text-slate-400 italic text-xs">(Tidak dijawab)</span>
                </div>
              </div>

              <!-- Panduan Kunci / Rubrik jika ada -->
              <div v-if="q.correct_answer && q.type === 'esai'" class="text-[11px] text-amber-800 bg-amber-50/70 p-2 rounded-xl">
                <b>Panduan Kunci / Rubrik:</b> {{ q.correct_answer }}
              </div>
            </div>
          </div>

          <!-- Input Nilai Esai / Tugas -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">
              {{ activeSubmission.file_path || quiz?.quiz_type === 'assignment' ? 'Nilai Tugas Mandiri (Skala 0 - 100) *' : 'Nilai Uraian / Esai (Skala 0 - 100) *' }}
            </label>
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
