<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppSkeleton from '~/components/ui/AppSkeleton.vue'
import AppEmptyState from '~/components/ui/AppEmptyState.vue'

definePageMeta({ layout: 'teacher', middleware: 'auth' })

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie<string | null>('auth_token')

const loading = ref(true)
const submissions = ref<any[]>([])
const courses = ref<any[]>([])
const quizzes = ref<any[]>([])

// Filter state
const selectedCourseId = ref<string>('')
const selectedQuizId = ref<string>('')
const statusFilter = ref<string>('all') // 'all', 'submitted', 'graded'
const searchQuery = ref<string>('')

// Grading Modal state
const showGradeModal = ref(false)
const gradingSubmission = ref<any>(null)
const gradeScore = ref<number>(100)
const gradeFeedback = ref<string>('')
const savingGrade = ref(false)
const gradeSuccessMsg = ref('')

const api = <T>(path: string, opts?: any) =>
  $fetch<T>(`${apiBase}${path}`, {
    headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
    ...opts
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
  try {
    const [coursesRes, quizzesRes, subsRes] = await Promise.allSettled([
      api<any>('/courses'),
      api<any>('/quizzes'),
      api<any>('/submissions')
    ])

    if (coursesRes.status === 'fulfilled') {
      const data = coursesRes.value
      courses.value = Array.isArray(data) ? data : (data?.data || [])
    }

    if (quizzesRes.status === 'fulfilled') {
      const data = quizzesRes.value
      quizzes.value = Array.isArray(data) ? data : (data?.data || [])
    }

    if (subsRes.status === 'fulfilled') {
      const data = subsRes.value
      submissions.value = Array.isArray(data) ? data : (data?.data || [])
    }
  } catch (err) {
    console.error('Gagal memuat data submissions:', err)
  } finally {
    loading.value = false
  }
}

const filteredQuizzes = computed(() => {
  if (!selectedCourseId.value) return quizzes.value
  return quizzes.value.filter(q => q.meet?.course_id == selectedCourseId.value)
})

const filteredSubmissions = computed(() => {
  return submissions.value.filter(sub => {
    // Filter Course
    if (selectedCourseId.value && sub.quiz?.meet?.course_id != selectedCourseId.value) {
      return false
    }

    // Filter Quiz
    if (selectedQuizId.value && sub.quiz_id != selectedQuizId.value) {
      return false
    }

    // Filter Status
    if (statusFilter.value === 'submitted' && (sub.is_graded || sub.status === 'graded')) {
      return false
    }
    if (statusFilter.value === 'graded' && !sub.is_graded && sub.status !== 'graded') {
      return false
    }

    // Filter Search
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase()
      const studentName = (sub.student?.name || '').toLowerCase()
      const studentNis = (sub.student?.nis || sub.student?.nisn || '').toLowerCase()
      const quizTitle = (sub.quiz?.title || '').toLowerCase()
      if (!studentName.includes(q) && !studentNis.includes(q) && !quizTitle.includes(q)) {
        return false
      }
    }

    return true
  })
})

const openGradeModal = (sub: any) => {
  gradingSubmission.value = sub
  gradeScore.value = sub.score !== null ? Number(sub.score) : (sub.quiz?.max_score || 100)
  gradeFeedback.value = sub.teacher_feedback || ''
  gradeSuccessMsg.value = ''
  showGradeModal.value = true
}

const handleSaveGrade = async () => {
  if (!gradingSubmission.value) return
  savingGrade.value = true
  gradeSuccessMsg.value = ''
  try {
    const res = await api<any>(`/submissions/${gradingSubmission.value.id}/grade`, {
      method: 'PUT',
      body: {
        score: gradeScore.value,
        teacher_feedback: gradeFeedback.value.trim() || null
      }
    })

    // Update locally
    const idx = submissions.value.findIndex(s => s.id === gradingSubmission.value.id)
    if (idx !== -1) {
      submissions.value[idx].score = gradeScore.value
      submissions.value[idx].is_graded = true
      submissions.value[idx].status = 'graded'
      submissions.value[idx].teacher_feedback = gradeFeedback.value.trim() || null
    }

    gradeSuccessMsg.value = 'Nilai dan ulasan berhasil disimpan!'
    setTimeout(() => {
      showGradeModal.value = false
    }, 1200)
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal menyimpan nilai tugas.')
  } finally {
    savingGrade.value = false
  }
}

onMounted(fetchData)
</script>

<template>
  <div class="p-6 sm:p-8 space-y-6 max-w-7xl mx-auto">
    
    <!-- Header Page Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2">
      <div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">
          Penilaian & Pengumpulan Tugas Siswa
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">
          Pusat evaluasi berkas tugas mandiri dan hasil tes siswa di seluruh kelas binaan Anda.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          type="button"
          @click="fetchData"
          class="px-4 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition shadow-2xs flex items-center gap-1.5"
        >
          <span>↻ Segarkan Data</span>
        </button>
      </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-xs grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
      <!-- Search Input -->
      <div>
        <label class="block font-bold text-slate-600 mb-1">Cari Siswa / Tugas</label>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Nama siswa, NISN, judul..."
          class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-emerald-500 font-medium"
        />
      </div>

      <!-- Course Filter -->
      <div>
        <label class="block font-bold text-slate-600 mb-1">Pilih Kelas / Mapel</label>
        <select
          v-model="selectedCourseId"
          class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-emerald-500 font-medium"
        >
          <option value="">Semua Kelas & Mapel</option>
          <option v-for="c in courses" :key="c.id" :value="c.id">
            {{ c.subject?.name }} - {{ c.class?.class_name?.name || c.class?.name }}
          </option>
        </select>
      </div>

      <!-- Quiz Filter -->
      <div>
        <label class="block font-bold text-slate-600 mb-1">Pilih Tugas / Kuis</label>
        <select
          v-model="selectedQuizId"
          class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-emerald-500 font-medium"
        >
          <option value="">Semua Tugas & Kuis</option>
          <option v-for="q in filteredQuizzes" :key="q.id" :value="q.id">
            [{{ q.activity_category || q.quiz_type }}] {{ q.title }}
          </option>
        </select>
      </div>

      <!-- Status Filter -->
      <div>
        <label class="block font-bold text-slate-600 mb-1">Status Penilaian</label>
        <select
          v-model="statusFilter"
          class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-emerald-500 font-semibold"
        >
          <option value="all">Semua Status</option>
          <option value="submitted">⏳ Perlu Dinilai (Belum Diperiksa)</option>
          <option value="graded">✓ Sudah Dinilai</option>
        </select>
      </div>
    </div>

    <!-- Table of Submissions -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
      <!-- Loading Skeleton -->
      <div v-if="loading" class="p-6 space-y-4">
        <AppSkeleton v-for="i in 5" :key="i" height="h-14" class="rounded-xl" />
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredSubmissions.length === 0" class="p-12 text-center">
        <AppEmptyState
          title="Tidak ada pengumpulan tugas"
          description="Belum ada berkas tugas yang sesuai dengan filter pencarian yang Anda pilih."
        />
      </div>

      <!-- Submissions Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold">
            <tr>
              <th class="py-3.5 px-4">Siswa</th>
              <th class="py-3.5 px-4">Tugas / Pertemuan</th>
              <th class="py-3.5 px-4">Waktu Kumpul</th>
              <th class="py-3.5 px-4">Berkas Lampiran</th>
              <th class="py-3.5 px-4 text-center">Nilai</th>
              <th class="py-3.5 px-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="sub in filteredSubmissions"
              :key="sub.id"
              class="hover:bg-slate-50/70 transition"
            >
              <!-- Student Info -->
              <td class="py-3.5 px-4">
                <div class="font-extrabold text-slate-900">
                  {{ sub.student?.name || 'Siswa' }}
                </div>
                <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                  NIS: {{ sub.student?.nis || sub.student?.nisn || '-' }}
                </div>
              </td>

              <!-- Quiz Info -->
              <td class="py-3.5 px-4 max-w-xs">
                <div class="font-bold text-slate-800 truncate" :title="sub.quiz?.title">
                  {{ sub.quiz?.title || 'Tugas Mandiri' }}
                </div>
                <div class="text-[11px] text-slate-400 truncate mt-0.5">
                  {{ sub.quiz?.meet?.course?.subject?.name || '' }} • Pertemuan {{ sub.quiz?.meet?.meeting_number || '' }}
                </div>
              </td>

              <!-- Submission Time -->
              <td class="py-3.5 px-4 text-slate-600 whitespace-nowrap">
                {{ formatDate(sub.submitted_at) }}
              </td>

              <!-- File Download -->
              <td class="py-3.5 px-4">
                <a
                  v-if="sub.file_url"
                  :href="sub.file_url"
                  target="_blank"
                  download
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-[11px] border border-emerald-200 transition"
                >
                  <span>📄 Unduh Berkas</span>
                </a>
                <span v-else-if="sub.submission_text" class="text-slate-400 italic text-[11px]">
                  Teks Jawaban Terlampir
                </span>
                <span v-else class="text-slate-300 text-[11px]">Tidak ada berkas</span>
              </td>

              <!-- Score Badge -->
              <td class="py-3.5 px-4 text-center">
                <span
                  v-if="sub.is_graded || sub.status === 'graded'"
                  class="inline-block px-2.5 py-1 rounded-full font-black text-xs bg-emerald-100 text-emerald-800"
                >
                  {{ sub.score }} / {{ sub.quiz?.max_score || 100 }}
                </span>
                <span
                  v-else
                  class="inline-block px-2.5 py-1 rounded-full font-bold text-[11px] bg-amber-50 text-amber-700 border border-amber-200"
                >
                  Belum Dinilai
                </span>
              </td>

              <!-- Action Button -->
              <td class="py-3.5 px-4 text-right">
                <button
                  type="button"
                  @click="openGradeModal(sub)"
                  class="px-3 py-1.5 rounded-xl text-xs font-bold transition shadow-2xs cursor-pointer"
                  :class="sub.is_graded || sub.status === 'graded' ? 'bg-slate-100 hover:bg-slate-200 text-slate-700' : 'bg-emerald-600 hover:bg-emerald-700 text-white'"
                >
                  {{ sub.is_graded || sub.status === 'graded' ? 'Edit Nilai' : 'Beri Nilai' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- GRADING MODAL -->
    <div
      v-if="showGradeModal && gradingSubmission"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
    >
      <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <h3 class="text-base font-extrabold text-slate-900">
              Penilaian Tugas: {{ gradingSubmission.student?.name }}
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">
              {{ gradingSubmission.quiz?.title }} • Maks {{ gradingSubmission.quiz?.max_score || 100 }} Poin
            </p>
          </div>
          <button
            type="button"
            @click="showGradeModal = false"
            class="text-slate-400 hover:text-slate-600 text-lg font-bold"
          >
            ✕
          </button>
        </div>

        <!-- Submission Details -->
        <div class="bg-slate-50/80 rounded-xl p-4 border border-slate-200/60 space-y-2 text-xs">
          <div v-if="gradingSubmission.file_url" class="flex items-center justify-between">
            <span class="font-bold text-slate-700">Berkas Tugas Siswa:</span>
            <a
              :href="gradingSubmission.file_url"
              target="_blank"
              download
              class="text-emerald-700 hover:text-emerald-800 font-bold underline"
            >
              Unduh / Lihat Dokumen ↗
            </a>
          </div>
          <div v-if="gradingSubmission.submission_text">
            <span class="font-bold text-slate-700 block mb-0.5">Catatan Pengumpulan Siswa:</span>
            <p class="text-slate-600 whitespace-pre-line bg-white p-2.5 rounded-lg border border-slate-200/50">
              {{ gradingSubmission.submission_text }}
            </p>
          </div>
        </div>

        <!-- Grade Form -->
        <div class="space-y-3.5 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">
              Skor / Nilai (0 - {{ gradingSubmission.quiz?.max_score || 100 }}) <span class="text-rose-500">*</span>
            </label>
            <input
              v-model.number="gradeScore"
              type="number"
              min="0"
              :max="gradingSubmission.quiz?.max_score || 100"
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-hidden focus:ring-2 focus:ring-emerald-500 font-bold text-slate-900 text-base"
              required
            />
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">
              Catatan & Feedback untuk Siswa (Opsional)
            </label>
            <textarea
              v-model="gradeFeedback"
              rows="3"
              placeholder="Berikan apresiasi atau catatan perbaikan pada pengerjaan siswa..."
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-hidden focus:ring-2 focus:ring-emerald-500 text-xs"
            ></textarea>
          </div>

          <div v-if="gradeSuccessMsg" class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl font-bold text-center">
            ✓ {{ gradeSuccessMsg }}
          </div>
        </div>

        <!-- Modal Actions -->
        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
          <button
            type="button"
            @click="showGradeModal = false"
            class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-xs font-bold hover:bg-slate-50 transition"
          >
            Batal
          </button>
          <button
            type="button"
            :disabled="savingGrade"
            @click="handleSaveGrade"
            class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs disabled:opacity-50 flex items-center gap-2 cursor-pointer"
          >
            <span v-if="savingGrade" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
            <span>{{ savingGrade ? 'Menyimpan...' : 'Simpan Nilai Siswa' }}</span>
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
