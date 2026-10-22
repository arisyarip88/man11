<script setup>
import { computed, ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BackButton from '~/components/ui/BackButton.vue'

definePageMeta({
  layout: 'default'
})

const route = useRoute()
const router = useRouter()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const storageBase = config.public.storageBase || 'http://localhost:8000/storage'
const token = useCookie('auth_token')
const { user, getUserRole } = useAuth()
const canManageLearning = computed(() => ['admin', 'super_admin', 'teacher', 'guru'].includes(getUserRole()))
const isStudent = computed(() => ['student', 'siswa'].includes(getUserRole()))

const validTabs = ['materials', 'quizzes', 'discussions', 'attendance']
const activeTab = ref(validTabs.includes(String(route.query.tab)) ? String(route.query.tab) : 'materials') // 'materials', 'quizzes', 'discussions', 'attendance'

const selectTab = (tab) => {
  activeTab.value = tab
  router.replace({ query: { ...route.query, tab } })
}
const meet = ref(null)
const quizzes = ref([])
const discussions = ref([])
const loading = ref(true)

// Presensi Siswa State (Phase 3 & 7)
const checkingIn = ref(false)
const handleCheckIn = async (status = 'H', notes = '') => {
  checkingIn.value = true
  try {
    const res = await $fetch(`${apiBase}/meets/${route.params.id}/attendances/check-in`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: { status, notes: notes || (status === 'H' ? 'Presensi Mandiri Siswa' : 'Izin Siswa Mandiri') }
    })
    if (meet.value) {
      meet.value.my_attendance = res.data
    }
    await fetchAttendances()
    alert('Presensi berhasil dicatat!')
  } catch (err) {
    alert(err?.data?.message || 'Gagal melakukan presensi.')
  } finally {
    checkingIn.value = false
  }
}

const attendances = ref([])
const attendanceStats = ref({ total: 0, H: 0, S: 0, I: 0, A: 0 })
const attendanceLoading = ref(false)
const attendanceSaving = ref(false)
const attendanceSearch = ref('')
const attendanceSuccessMsg = ref('')

// Modals
const showMaterialModal = ref(false)
const showQuizModal = ref(false)
const showSubmitModal = ref(false)
const showGradeModal = ref(false)

const selectedQuiz = ref(null)
const selectedSubmission = ref(null)

const materialForm = reactive({
  title: '',
  type: 'file',
  content: '',
  external_url: '',
  file: null
})

const quizForm = reactive({
  title: '',
  instructions: '',
  quiz_type: 'assignment',
  activity_category: 'assignment',
  due_date: '',
  max_score: 100
})

const quizMode = ref('manual') // 'manual' | 'bank'
const questionBanks = ref([])
const bankDifficultyFilter = ref('')
const filteredQuestionBanks = computed(() => {
  if (!bankDifficultyFilter.value) return questionBanks.value
  return questionBanks.value.filter(b => b.difficulty === bankDifficultyFilter.value)
})
const selectedBankId = ref('')
const quizDuration = ref(60)
const isSyncingQuiz = ref(false)

const submitForm = reactive({
  submission_text: '',
  file: null
})

const gradeForm = reactive({
  score: 100,
  teacher_feedback: ''
})

const discussionMessage = ref('')
const replyingToId = ref(null)

const fetchMeetDetail = async () => {
  loading.value = true
  try {
    const res = await $fetch(`${apiBase}/meets/${route.params.id}`, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    meet.value = res
  } catch (err) {
    console.error('Fetch meet detail error:', err)
  } finally {
    loading.value = false
  }
}

const fetchQuizzes = async () => {
  try {
    const res = await $fetch(`${apiBase}/quizzes?meet_id=${route.params.id}`, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    quizzes.value = res.data || res || []
  } catch (err) {
    console.error('Fetch quizzes error:', err)
  }
}

const fetchDiscussions = async () => {
  try {
    const res = await $fetch(`${apiBase}/discussions?meet_id=${route.params.id}`, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    discussions.value = res.data || res || []
  } catch (err) {
    console.error('Fetch discussions error:', err)
  }
}

const fetchAttendances = async () => {
  attendanceLoading.value = true
  try {
    const res = await $fetch(`${apiBase}/meets/${route.params.id}/attendances`, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    if (res?.data) {
      attendances.value = res.data.attendances || []
      attendanceStats.value = res.data.stats || { total: 0, H: 0, S: 0, I: 0, A: 0 }
    }
  } catch (err) {
    console.error('Fetch attendances error:', err)
  } finally {
    attendanceLoading.value = false
  }
}

const markAllPresent = () => {
  attendances.value.forEach(item => {
    item.status = 'H'
  })
  recalcAttendanceStats()
}

const setAttendanceStatus = (item, status) => {
  item.status = status
  recalcAttendanceStats()
}

const recalcAttendanceStats = () => {
  const stats = { total: attendances.value.length, H: 0, S: 0, I: 0, A: 0 }
  attendances.value.forEach(item => {
    if (stats[item.status] !== undefined) stats[item.status]++
  })
  attendanceStats.value = stats
}

const saveAttendances = async () => {
  attendanceSaving.value = true
  attendanceSuccessMsg.value = ''
  try {
    const payload = {
      attendances: attendances.value.map(item => ({
        student_id: item.student_id,
        status: item.status,
        notes: item.notes || null
      }))
    }
    const res = await $fetch(`${apiBase}/meets/${route.params.id}/attendances/batch`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: payload
    })
    if (res?.data?.stats) {
      attendanceStats.value = { ...attendanceStats.value, ...res.data.stats }
    }
    attendanceSuccessMsg.value = 'Presensi siswa berhasil disimpan!'
    setTimeout(() => {
      attendanceSuccessMsg.value = ''
    }, 4000)
  } catch (err) {
    alert(err?.data?.message || 'Gagal menyimpan presensi pertemuan.')
  } finally {
    attendanceSaving.value = false
  }
}

const filteredAttendances = computed(() => {
  if (!attendanceSearch.value.trim()) return attendances.value
  const q = attendanceSearch.value.toLowerCase()
  return attendances.value.filter(a =>
    (a.name && a.name.toLowerCase().includes(q)) ||
    (a.nisn && a.nisn.toLowerCase().includes(q))
  )
})

const myAttendance = computed(() => {
  if (meet.value?.my_attendance) {
    return meet.value.my_attendance
  }
  const studentId = user.value?.student?.id
  if (!studentId) return null
  return attendances.value.find(a => a.student_id === studentId) || null
})

onMounted(async () => {
  await fetchMeetDetail()
  await fetchQuizzes()
  await fetchDiscussions()
  await fetchAttendances()
})

const handleMaterialFileChange = (e) => {
  materialForm.file = e.target.files[0] || null
}

const handleSubmitFileChange = (e) => {
  submitForm.file = e.target.files[0] || null
}

const openAddMaterialModal = () => {
  Object.assign(materialForm, { title: '', type: 'file', content: '', external_url: '', file: null })
  showMaterialModal.value = true
}

const openAddQuizModal = async () => {
  quizMode.value = 'manual'
  selectedBankId.value = ''
  quizDuration.value = 60
  Object.assign(quizForm, { title: '', instructions: '', quiz_type: 'assignment', due_date: '', max_score: 100 })
  showQuizModal.value = true

  // Fetch question banks if empty
  if (questionBanks.value.length === 0) {
    try {
      const res = await $fetch(`${apiBase}/question-banks?paginate=false`, {
        headers: { Authorization: `Bearer ${token.value}` }
      })
      questionBanks.value = res.data || (Array.isArray(res) ? res : [])
    } catch (e) {
      console.error('Gagal mengambil bank soal:', e)
    }
  }
}

const openSubmitModal = (quiz) => {
  selectedQuiz.value = quiz
  Object.assign(submitForm, { submission_text: '', file: null })
  showSubmitModal.value = true
}

const openGradeModal = (sub) => {
  selectedSubmission.value = sub
  Object.assign(gradeForm, { score: sub.score || 100, teacher_feedback: sub.teacher_feedback || '' })
  showGradeModal.value = true
}

const handleSaveMaterial = async () => {
  const formData = new FormData()
  formData.append('meet_id', route.params.id)
  formData.append('title', materialForm.title)
  formData.append('type', materialForm.type)
  if (materialForm.content) formData.append('content', materialForm.content)
  if (materialForm.external_url) formData.append('external_url', materialForm.external_url)
  if (materialForm.file) formData.append('file', materialForm.file)

  try {
    await $fetch(`${apiBase}/materials`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: formData
    })
    showMaterialModal.value = false
    await fetchMeetDetail()
  } catch (err) {
    alert(err.data?.message || 'Gagal menyimpan materi.')
  }
}

const handleSaveQuiz = async () => {
  isSyncingQuiz.value = true
  try {
    const isCbt = quizMode.value === 'bank'
    const payload = {
      ...quizForm,
      meet_id: route.params.id,
      quiz_type: isCbt ? 'multiple_choice' : quizForm.quiz_type,
      activity_category: quizForm.activity_category || (isCbt ? 'quiz' : 'assignment'),
      duration_minutes: isCbt ? quizDuration.value : null
    }

    const res = await $fetch(`${apiBase}/quizzes`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: payload
    })

    const createdQuizId = res.data?.id || res.id

    // Jika mode bank soal, langsung jalankan sinkronisasi butir soal
    if (isCbt && selectedBankId.value && createdQuizId) {
      await $fetch(`${apiBase}/quizzes/${createdQuizId}/import-bank-items`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token.value}` },
        body: { question_bank_id: selectedBankId.value }
      })
    }

    showQuizModal.value = false
    await fetchQuizzes()
    alert(isCbt ? 'Ujian CBT dari Bank Soal berhasil disinkronkan ke pertemuan!' : 'Tugas mandiri berhasil dibuat!')
  } catch (err) {
    alert(err.data?.message || 'Gagal menyimpan kuis / tugas.')
  } finally {
    isSyncingQuiz.value = false
  }
}

const handleSubmitTask = async () => {
  const formData = new FormData()
  formData.append('quiz_id', selectedQuiz.value.id)
  if (submitForm.submission_text) formData.append('submission_text', submitForm.submission_text)
  if (submitForm.file) formData.append('file', submitForm.file)

  try {
    await $fetch(`${apiBase}/quizzes/submit`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: formData
    })
    showSubmitModal.value = false
    await fetchQuizzes()
    alert('Tugas Anda berhasil dikumpulkan!')
  } catch (err) {
    alert(err.data?.message || 'Gagal mengumpulkan tugas.')
  }
}

const handleSaveGrade = async () => {
  try {
    await $fetch(`${apiBase}/submissions/${selectedSubmission.value.id}/grade`, {
      method: 'PUT',
      headers: { Authorization: `Bearer ${token.value}` },
      body: gradeForm
    })
    showGradeModal.value = false
    await fetchQuizzes()
  } catch (err) {
    alert(err.data?.message || 'Gagal menyimpan nilai.')
  }
}

const handleSendDiscussion = async (parentId = null) => {
  const text = discussionMessage.value.trim()
  if (!text) return

  try {
    await $fetch(`${apiBase}/discussions`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: {
        meet_id: route.params.id,
        parent_id: parentId,
        message: text
      }
    })
    discussionMessage.value = ''
    replyingToId.value = null
    await fetchDiscussions()
  } catch (err) {
    alert(err.data?.message || 'Gagal mengirim pesan diskusi')
  }
}

const getEmbedYoutubeUrl = (url) => {
  if (!url) return ''
  const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/
  const match = url.match(regExp)
  return (match && match[2].length === 11) ? `https://www.youtube.com/embed/${match[2]}` : url
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-sans text-slate-800 pb-12">
    <!-- Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <BackButton
            :fallback="meet ? `/elearning/courses/${meet.course_id}` : (isStudent ? '/student/dashboard?tab=courses' : '/teacher/classes')"
            label="Kembali ke Kelas Pembelajaran"
          />
        </div>
        <span class="text-xs font-bold text-emerald-800 bg-emerald-100 px-3 py-1 rounded-full">
          Pertemuan {{ meet?.meeting_number || '-' }}
        </span>
      </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
      <div v-if="loading" class="bg-white rounded-2xl p-12 text-center text-gray-400">
        Memuat sesi pertemuan...
      </div>

      <template v-else-if="meet">
        <!-- Banner Header Pertemuan -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-100 mb-6">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-bold text-emerald-600 uppercase tracking-wider">
                  {{ meet.course?.subject?.name }} ({{ meet.course?.class?.class_name?.name }})
                </span>
                <span v-if="meet.chapter" class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 text-[11px] font-bold border border-emerald-200">
                  Bab {{ meet.chapter.order || '' }}: {{ meet.chapter.title }}
                </span>
              </div>
              <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1">
                {{ meet.title }}
              </h1>
            </div>

            <div v-if="meet.type === 'online' && meet.meeting_link">
              <a 
                :href="meet.meeting_link" 
                target="_blank"
                class="px-5 py-2.5 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 transition shadow-xs flex items-center gap-2"
              >
                Gabung Virtual Meet (Zoom/GMeet)
              </a>
            </div>
          </div>

          <p class="text-sm text-slate-600 leading-relaxed mt-4">
            {{ meet.description || 'Tidak ada instruksi khusus.' }}
          </p>

          <!-- Tab Selector Header -->
          <div class="flex flex-wrap gap-2 pt-6 border-t border-slate-100 mt-6">
            <button 
              @click="selectTab('materials')"
              class="px-4 py-2 rounded-xl font-bold text-xs transition"
              :class="activeTab === 'materials' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            >
              Modul & Materi ({{ meet.materials?.length || 0 }})
            </button>
            <button 
              @click="selectTab('quizzes')"
              class="px-4 py-2 rounded-xl font-bold text-xs transition"
              :class="activeTab === 'quizzes' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            >
              Kuis & Tugas ({{ quizzes.length }})
            </button>
            <button 
              @click="selectTab('discussions')"
              class="px-4 py-2 rounded-xl font-bold text-xs transition"
              :class="activeTab === 'discussions' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            >
              Forum Diskusi ({{ discussions.length }})
            </button>
            <button 
              @click="selectTab('attendance')"
              class="px-4 py-2 rounded-xl font-bold text-xs transition flex items-center gap-1.5"
              :class="activeTab === 'attendance' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            >
              Presensi Kehadiran
              <span class="rounded-full px-1.5 py-0.5 text-[10px]" :class="activeTab === 'attendance' ? 'bg-white/20 text-white font-bold' : 'bg-emerald-100 text-emerald-800 font-bold'">
                {{ attendanceStats.total || attendances.length }}
              </span>
            </button>
          </div>
        </div>

        <!-- TAB 1: MATERI -->
        <div v-if="activeTab === 'materials'" class="space-y-4">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-bold text-slate-900">Materi Pembelajaran</h3>
            <button v-if="canManageLearning" @click="openAddMaterialModal" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition">
              + Unggah Materi Baru
            </button>
          </div>

          <div v-if="!meet.materials || meet.materials.length === 0" class="bg-white rounded-2xl p-8 text-center text-slate-400">
            Belum ada materi pada pertemuan ini.
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="mat in meet.materials" :key="mat.id" class="bg-white p-5 rounded-2xl border border-slate-100 shadow-xs space-y-3">
              <span class="px-2.5 py-1 text-[10px] font-bold rounded-lg uppercase" :class="mat.type === 'file' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700'">
                {{ mat.type }}
              </span>
              <h4 class="font-bold text-slate-900 text-base">{{ mat.title }}</h4>
              <p v-if="mat.content" class="text-xs text-slate-600 line-clamp-3">{{ mat.content }}</p>

              <div v-if="mat.type === 'video' && mat.external_url" class="aspect-video w-full rounded-xl overflow-hidden bg-black">
                <iframe :src="getEmbedYoutubeUrl(mat.external_url)" class="w-full h-full" frameborder="0" allowfullscreen></iframe>
              </div>

              <div v-if="mat.file_path" class="pt-2">
                <a :href="`${storageBase}/${mat.file_path}`" target="_blank" class="px-4 py-2 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-xl inline-block">
                  Unduh Dokumen PDF / Modul 📥
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 2: KUIS & TUGAS -->
        <div v-else-if="activeTab === 'quizzes'" class="space-y-4">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-bold text-slate-900">Daftar Kuis & Tugas Mandiri</h3>
            <button v-if="canManageLearning" @click="openAddQuizModal" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition">
              + Buat Tugas / Kuis Baru
            </button>
          </div>

          <div v-if="quizzes.length === 0" class="bg-white rounded-2xl p-8 text-center text-slate-400">
            Belum ada tugas atau kuis yang dibuat untuk pertemuan ini.
          </div>

          <div v-else class="space-y-4">
            <div v-for="q in quizzes" :key="q.id" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-4">
              <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div>
                  <div class="flex items-center gap-2">
                    <span 
                      class="px-2.5 py-1 text-[10px] font-bold uppercase rounded-lg"
                      :class="q.quiz_type === 'multiple_choice' ? 'bg-purple-100 text-purple-800' : 'bg-amber-100 text-amber-800'"
                    >
                      {{ q.quiz_type === 'multiple_choice' ? 'Ujian CBT (Bank Soal)' : (q.quiz_type || 'Tugas') }}
                    </span>
                    <span v-if="q.duration_minutes" class="text-[11px] text-slate-500 font-semibold">
                      ⏱ {{ q.duration_minutes }} Menit
                    </span>
                  </div>
                  <h4 class="text-lg font-bold text-slate-900 mt-1.5">{{ q.title }}</h4>
                  <p class="text-xs text-slate-500 mt-1">Batas Pengumpulan: <span class="font-semibold text-rose-600">{{ q.due_date }}</span> • Max Nilai: {{ q.max_score }}</p>
                </div>

                <!-- Action Button Depending on Role & Quiz Type -->
                <div class="flex items-center gap-2">
                  <!-- Siswa: CBT Exam -->
                  <NuxtLink 
                    v-if="!canManageLearning && q.quiz_type === 'multiple_choice'" 
                    :to="`/student/quizzes/${q.id}/exam`"
                    class="px-5 py-2.5 bg-brand-700 hover:bg-brand-800 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5"
                  >
                    <span>Mulai Ujian CBT</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                  </NuxtLink>

                  <!-- Siswa: Regular Assignment Submit -->
                  <button 
                    v-else-if="!canManageLearning"
                    @click="openSubmitModal(q)" 
                    class="px-4 py-2 bg-brand-700 text-white text-xs font-bold rounded-xl hover:bg-brand-800 transition cursor-pointer"
                  >
                    Kumpulkan Tugas
                  </button>

                  <!-- Guru: Grading & Koreksi CBT -->
                  <NuxtLink 
                    v-if="canManageLearning && q.quiz_type === 'multiple_choice'" 
                    :to="`/teacher/quizzes/${q.id}/grading`"
                    class="px-4 py-2 bg-brand-50 hover:bg-brand-100 text-brand-900 border border-brand-200 text-xs font-bold rounded-xl transition"
                  >
                    Koreksi & Nilai CBT
                  </NuxtLink>
                </div>
              </div>

              <p class="text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-100">
                {{ q.instructions || 'Tidak ada instruksi khusus.' }}
              </p>

              <!-- Submissions List (For Teacher / Review) -->
              <div v-if="canManageLearning && q.submissions && q.submissions.length > 0" class="pt-3 border-t border-slate-100 space-y-2">
                <h5 class="text-xs font-bold text-slate-700">Daftar Kiriman Tugas Siswa ({{ q.submissions.length }}):</h5>
                <div v-for="sub in q.submissions" :key="sub.id" class="flex justify-between items-center p-3 bg-slate-50 rounded-xl text-xs">
                  <div>
                    <span class="font-bold text-slate-900">{{ sub.student?.name || 'Siswa' }}</span>
                    <span class="ml-2 text-slate-400">({{ sub.submitted_at }})</span>
                    <div v-if="sub.score" class="text-emerald-600 font-bold mt-0.5">Nilai: {{ sub.score }} / {{ q.max_score }}</div>
                  </div>
                  <button @click="openGradeModal(sub)" class="px-3 py-1.5 bg-amber-100 text-amber-800 font-bold rounded-lg hover:bg-amber-200 transition">
                    Beri Nilai & Feedback
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 3: FORUM DISKUSI -->
        <div v-else-if="activeTab === 'discussions'" class="space-y-4">
          <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-4">
            <h3 class="text-lg font-bold text-slate-900">Forum Diskusi Interaktif</h3>

            <!-- New Discussion Input -->
            <div class="space-y-2">
              <textarea 
                v-model="discussionMessage"
                rows="3"
                placeholder="Tuliskan pertanyaan atau pendapat Anda di forum ini..."
                class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              ></textarea>
              <div class="flex justify-end">
                <button @click="handleSendDiscussion(null)" class="px-5 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition">
                  Kirim Pesan
                </button>
              </div>
            </div>

            <!-- Threaded Comments List -->
            <div v-if="discussions.length === 0" class="py-8 text-center text-xs text-slate-400">
              Belum ada diskusi pada pertemuan ini. Jadilah yang pertama bertanya!
            </div>

            <div v-else class="space-y-4 pt-4 border-t border-slate-100">
              <div v-for="disc in discussions" :key="disc.id" class="p-4 bg-slate-50 rounded-2xl space-y-2 border border-slate-100">
                <div class="flex justify-between items-center text-xs">
                  <div class="flex items-center gap-2">
                    <span class="font-bold text-slate-900">{{ disc.teacher?.name || disc.student?.name || 'Pengguna' }}</span>
                    <span class="px-2 py-0.5 text-[9px] font-bold rounded-full" :class="disc.teacher ? 'bg-brand-100 text-brand-900' : 'bg-slate-100 text-slate-700'">
                      {{ disc.teacher ? 'GURU' : 'SISWA' }}
                    </span>
                  </div>
                  <span class="text-slate-400 text-[10px]">{{ disc.created_at }}</span>
                </div>

                <p class="text-xs text-slate-700 leading-relaxed">{{ disc.message }}</p>

                <!-- Replies List -->
                <div v-if="disc.replies && disc.replies.length > 0" class="pl-4 border-l-2 border-emerald-300 space-y-2 pt-2">
                  <div v-for="reply in disc.replies" :key="reply.id" class="p-3 bg-white rounded-xl text-xs space-y-1">
                    <div class="flex items-center justify-between">
                      <span class="font-bold text-slate-900">{{ reply.teacher?.name || reply.student?.name || 'Pengguna' }}</span>
                      <span class="text-slate-400 text-[9px]">{{ reply.created_at }}</span>
                    </div>
                    <p class="text-slate-600">{{ reply.message }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 4: PRESENSI KEHADIRAN SISWA (PHASE 3) -->
        <div v-else-if="activeTab === 'attendance'" class="space-y-6">
          <!-- Student Banner (If logged in as student) -->
          <div v-if="!canManageLearning" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xs">
            <div v-if="myAttendance && myAttendance.status" class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
              <div>
                <p class="text-[11px] font-bold text-emerald-800 uppercase tracking-wider">Status Presensi Anda</p>
                <h4 class="text-lg font-extrabold text-slate-900 mt-0.5">{{ user?.name || user?.username }}</h4>
                <p v-if="myAttendance.notes" class="text-xs text-slate-500 mt-1">Catatan: {{ myAttendance.notes }}</p>
                <p v-if="myAttendance.recorded_at" class="text-[10px] text-slate-400 mt-0.5">Tercatat: {{ myAttendance.recorded_at }}</p>
              </div>
              <div>
                <span
                  class="px-4 py-2 rounded-xl text-xs font-black inline-block shadow-xs"
                  :class="{
                    'bg-emerald-600 text-white': myAttendance.status === 'H',
                    'bg-amber-500 text-white': myAttendance.status === 'S',
                    'bg-sky-500 text-white': myAttendance.status === 'I',
                    'bg-rose-500 text-white': myAttendance.status === 'A'
                  }"
                >
                  {{ myAttendance.status === 'H' ? '✓ HADIR' : myAttendance.status === 'S' ? '✢ SAKIT' : myAttendance.status === 'I' ? 'ℹ IZIN' : '✕ ALPA / TANPA KETERANGAN' }}
                </span>
              </div>
            </div>

            <!-- Jika Presensi Belum Dicatat Guru -->
            <div v-else class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
              <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Status Kehadiran Pertemuan</p>
                <h4 class="text-base font-extrabold text-slate-800 mt-0.5">Belum Dicatat oleh Guru</h4>
                <p class="text-xs text-slate-500 mt-1">Presensi kehadiran siswa dicatat dan dikonfirmasi langsung oleh guru pengampu di kelas.</p>
              </div>
              <div class="flex items-center gap-2">
                <span class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                  Menunggu Presensi Guru
                </span>
              </div>
            </div>
          </div>

          <!-- Quick Stats Grid -->
          <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-xs">
              <span class="text-[10px] font-bold uppercase tracking-wider text-slate-600">Total Siswa</span>
              <p class="text-2xl font-black text-slate-900 mt-1">{{ attendanceStats.total }}</p>
            </div>
            <div class="bg-emerald-50/80 rounded-2xl p-4 border border-emerald-100/80 shadow-xs">
              <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Hadir (H)</span>
              <p class="text-2xl font-black text-emerald-700 mt-1">{{ attendanceStats.H }}</p>
            </div>
            <div class="bg-amber-50/80 rounded-2xl p-4 border border-amber-100/80 shadow-xs">
              <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800">Sakit (S)</span>
              <p class="text-2xl font-black text-amber-700 mt-1">{{ attendanceStats.S }}</p>
            </div>
            <div class="bg-sky-50/80 rounded-2xl p-4 border border-sky-100/80 shadow-xs">
              <span class="text-[10px] font-bold uppercase tracking-wider text-sky-800">Izin (I)</span>
              <p class="text-2xl font-black text-sky-700 mt-1">{{ attendanceStats.I }}</p>
            </div>
            <div class="bg-rose-50/80 rounded-2xl p-4 border border-rose-100/80 shadow-xs col-span-2 sm:col-span-1">
              <span class="text-[10px] font-bold uppercase tracking-wider text-rose-800">Alpa (A)</span>
              <p class="text-2xl font-black text-rose-700 mt-1">{{ attendanceStats.A }}</p>
            </div>
          </div>

          <!-- Action & Filter Bar (Guru / Admin) -->
          <div v-if="canManageLearning" class="bg-white rounded-2xl p-4 border border-slate-100 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <div class="relative flex-1 max-w-md">
              <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <input
                v-model="attendanceSearch"
                type="text"
                placeholder="Cari nama atau NISN siswa..."
                class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              />
            </div>

            <div class="flex items-center gap-2">
              <button
                @click="markAllPresent"
                type="button"
                class="px-3.5 py-2 bg-emerald-50 text-emerald-800 border border-emerald-200/80 hover:bg-emerald-100 rounded-xl text-xs font-bold transition flex items-center gap-1.5"
              >
                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Tandai Semua Hadir
              </button>
              <button
                @click="saveAttendances"
                :disabled="attendanceSaving"
                type="button"
                class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5 disabled:opacity-50"
              >
                <svg v-if="attendanceSaving" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span>{{ attendanceSaving ? 'Menyimpan...' : 'Simpan Presensi' }}</span>
              </button>
            </div>
          </div>

          <!-- Alert Success -->
          <div v-if="attendanceSuccessMsg" class="rounded-2xl bg-emerald-50 border border-emerald-200 p-3.5 text-xs font-bold text-emerald-800 flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ attendanceSuccessMsg }}
          </div>

          <!-- Attendance Table -->
          <div class="bg-white rounded-3xl border border-slate-100 shadow-xs overflow-hidden">
            <div v-if="attendanceLoading" class="p-12 text-center text-slate-400 text-xs">
              Memuat data presensi rombel...
            </div>
            <div v-else-if="filteredAttendances.length === 0" class="p-12 text-center text-slate-400 text-xs">
              Tidak ada data siswa pada kelas ini.
            </div>
            <div v-else class="overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-100 text-slate-500 uppercase tracking-wider text-[11px]">
                    <th class="py-3.5 px-4 font-bold text-center w-12">No</th>
                    <th class="py-3.5 px-4 font-bold w-36">NISN</th>
                    <th class="py-3.5 px-4 font-bold">Nama Lengkap Siswa</th>
                    <th class="py-3.5 px-4 font-bold text-center w-60">Status Kehadiran</th>
                    <th class="py-3.5 px-4 font-bold w-64">Keterangan / Catatan</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="(student, idx) in filteredAttendances" :key="student.student_id" class="hover:bg-slate-50/70 transition">
                    <td class="py-3 px-4 text-center font-bold text-slate-400">{{ idx + 1 }}</td>
                    <td class="py-3 px-4 font-mono text-slate-500">{{ student.nisn || '-' }}</td>
                    <td class="py-3 px-4 font-bold text-slate-900">{{ student.name }}</td>
                    <td class="py-3 px-4">
                      <!-- Status Pills (H / S / I / A) -->
                      <div v-if="canManageLearning" class="flex items-center justify-center gap-1.5 bg-slate-100/80 p-1 rounded-xl">
                        <button
                          type="button"
                          @click="setAttendanceStatus(student, 'H')"
                          class="px-2.5 py-1 rounded-lg font-extrabold text-[11px] transition"
                          :class="student.status === 'H' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:bg-white'"
                          title="Hadir"
                        >
                          H
                        </button>
                        <button
                          type="button"
                          @click="setAttendanceStatus(student, 'S')"
                          class="px-2.5 py-1 rounded-lg font-extrabold text-[11px] transition"
                          :class="student.status === 'S' ? 'bg-amber-500 text-white shadow-xs' : 'text-slate-600 hover:bg-white'"
                          title="Sakit"
                        >
                          S
                        </button>
                        <button
                          type="button"
                          @click="setAttendanceStatus(student, 'I')"
                          class="px-2.5 py-1 rounded-lg font-extrabold text-[11px] transition"
                          :class="student.status === 'I' ? 'bg-sky-500 text-white shadow-xs' : 'text-slate-600 hover:bg-white'"
                          title="Izin"
                        >
                          I
                        </button>
                        <button
                          type="button"
                          @click="setAttendanceStatus(student, 'A')"
                          class="px-2.5 py-1 rounded-lg font-extrabold text-[11px] transition"
                          :class="student.status === 'A' ? 'bg-rose-500 text-white shadow-xs' : 'text-slate-600 hover:bg-white'"
                          title="Alpa"
                        >
                          A
                        </button>
                      </div>
                      <div v-else class="text-center font-bold">
                        <span
                          class="px-2.5 py-1 rounded-lg text-[10px]"
                          :class="{
                            'bg-emerald-100 text-emerald-800': student.status === 'H',
                            'bg-amber-100 text-amber-800': student.status === 'S',
                            'bg-sky-100 text-sky-800': student.status === 'I',
                            'bg-rose-100 text-rose-800': student.status === 'A'
                          }"
                        >
                          {{ student.status === 'H' ? 'Hadir' : student.status === 'S' ? 'Sakit' : student.status === 'I' ? 'Izin' : 'Alpa' }}
                        </span>
                      </div>
                    </td>
                    <td class="py-3 px-4">
                      <input
                        v-if="canManageLearning"
                        v-model="student.notes"
                        type="text"
                        placeholder="Misal: Sakit flu, izin lomba..."
                        class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-emerald-500"
                      />
                      <span v-else class="text-slate-500">{{ student.notes || '-' }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Footer Save Bar -->
            <div v-if="canManageLearning && filteredAttendances.length > 0" class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs">
              <span class="text-slate-500">
                Menampilkan <b>{{ filteredAttendances.length }}</b> dari total <b>{{ attendances.length }}</b> siswa
              </span>
              <button
                @click="saveAttendances"
                :disabled="attendanceSaving"
                type="button"
                class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5 disabled:opacity-50"
              >
                <span>{{ attendanceSaving ? 'Menyimpan...' : 'Simpan Presensi Pertemuan' }}</span>
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- MODAL FORM MATERIAL -->
    <div v-if="canManageLearning && showMaterialModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <h3 class="text-lg font-bold text-slate-900">Unggah Materi Baru</h3>
        <form @submit.prevent="handleSaveMaterial" class="space-y-3 text-xs">
          <input v-model="materialForm.title" type="text" placeholder="Judul Materi" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl" required>
          <select v-model="materialForm.type" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
            <option value="file">File PDF / Dokumen</option>
            <option value="video">Video YouTube</option>
          </select>
          <input v-if="materialForm.type === 'file'" type="file" @change="handleMaterialFileChange" class="w-full">
          <input v-if="materialForm.type === 'video'" v-model="materialForm.external_url" type="url" placeholder="https://youtube.com/..." class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
          <textarea v-model="materialForm.content" placeholder="Rangkuman teks..." class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl" rows="2"></textarea>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showMaterialModal = false" class="px-4 py-2 border rounded-xl">Batal</button>
            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white font-bold rounded-xl">Simpan</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL FORM QUIZ & CBT SYNC -->
    <div v-if="canManageLearning && showQuizModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center pb-2 border-b border-slate-100">
          <h3 class="text-lg font-bold text-slate-900">Buat Tugas / Ujian CBT Baru</h3>
          <button @click="showQuizModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <!-- Mode Toggle -->
        <div class="grid grid-cols-2 gap-2 bg-slate-100 p-1 rounded-xl text-xs font-bold">
          <button 
            type="button" 
            @click="quizMode = 'manual'"
            class="py-2 rounded-lg transition"
            :class="quizMode === 'manual' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
          >
            📁 Penugasan Manual
          </button>
          <button 
            type="button" 
            @click="quizMode = 'bank'"
            class="py-2 rounded-lg transition"
            :class="quizMode === 'bank' ? 'bg-purple-600 text-white shadow-xs' : 'text-slate-500 hover:text-purple-600'"
          >
            ⚡ Ambil dari Bank Soal (CBT)
          </button>
        </div>

        <form @submit.prevent="handleSaveQuiz" class="space-y-3.5 text-xs">
          <!-- Title -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Judul Tugas / Ujian</label>
            <input v-model="quizForm.title" type="text" placeholder="Misal: Penilaian Harian Bab 1 / Tugas Mandiri" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none" required>
          </div>

          <!-- Kategori Aktivitas Pembelajaran -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Kategori Aktivitas LMS</label>
            <select v-model="quizForm.activity_category" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none font-semibold text-slate-800">
              <option value="pretest">1. Pretest (Asesmen Diagnostik Awal)</option>
              <option value="quiz">4. Kuis Formatif (Pemahaman Konsep)</option>
              <option value="assignment">5. Tugas Mandiri (Pengumpulan File Siswa)</option>
              <option value="posttest">6. Posttest (Asesmen Sumatif Akhir)</option>
            </select>
          </div>

          <!-- If Bank Soal Selected -->
          <div v-if="quizMode === 'bank'" class="p-4 bg-purple-50/70 border border-purple-100 rounded-xl space-y-3">
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="block font-bold text-purple-900">Pilih Bank Soal Guru</label>
                <div class="flex items-center gap-1.5">
                  <span class="text-[10px] text-purple-800 font-semibold">Kesulitan:</span>
                  <select
                    v-model="bankDifficultyFilter"
                    class="px-2 py-0.5 bg-white border border-purple-200 rounded-lg text-[11px] font-bold text-slate-700"
                  >
                    <option value="">Semua</option>
                    <option value="easy">🟢 Mudah</option>
                    <option value="medium">🟡 Sedang</option>
                    <option value="hard">🔴 Sulit</option>
                  </select>
                </div>
              </div>
              <select 
                v-model="selectedBankId" 
                class="w-full p-2.5 bg-white border border-purple-200 rounded-xl text-purple-950 font-medium focus:ring-2 focus:ring-purple-500 focus:outline-none"
                required
              >
                <option value="" disabled>-- Pilih Bank Soal yang Tersedia --</option>
                <option v-for="b in filteredQuestionBanks" :key="b.id" :value="b.id">
                  [{{ b.difficulty === 'easy' ? '🟢 Mudah' : b.difficulty === 'medium' ? '🟡 Sedang' : b.difficulty === 'hard' ? '🔴 Sulit' : '⚪ Umum' }}]
                  {{ b.title }} ({{ b.grade_level ? 'Kelas ' + b.grade_level : '' }} • {{ b.items_count || (b.items ? b.items.length : 0) }} Butir Soal)
                </option>
              </select>
              <p v-if="filteredQuestionBanks.length === 0" class="text-[11px] text-amber-600 mt-1">
                Tidak ada Bank Soal yang sesuai dengan filter kesulitan yang dipilih.
              </p>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block font-bold text-purple-900 mb-1">Durasi Pengerjaan (Menit)</label>
                <input 
                  v-model.number="quizDuration" 
                  type="number" 
                  min="5" 
                  max="180" 
                  class="w-full p-2.5 bg-white border border-purple-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:outline-none" 
                  required
                >
              </div>
              <div>
                <label class="block font-bold text-purple-900 mb-1">Skor Maksimal</label>
                <input 
                  v-model.number="quizForm.max_score" 
                  type="number" 
                  min="1" 
                  class="w-full p-2.5 bg-white border border-purple-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:outline-none" 
                  required
                >
              </div>
            </div>
          </div>

          <!-- If Manual Selected -->
          <div v-if="quizMode === 'manual'" class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Tipe Penugasan</label>
              <select v-model="quizForm.quiz_type" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
                <option value="assignment">Tugas Mandiri</option>
                <option value="quiz">Kuis Pertemuan</option>
                <option value="exam">Ujian Tertulis</option>
              </select>
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Skor Maksimal</label>
              <input v-model.number="quizForm.max_score" type="number" min="1" max="100" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl" required>
            </div>
          </div>

          <!-- Due Date -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Batas Waktu Pengumpulan (Deadline)</label>
            <input v-model="quizForm.due_date" type="datetime-local" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl" required>
          </div>

          <!-- Instructions -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Petunjuk & Instruksi Pengerjaan</label>
            <textarea v-model="quizForm.instructions" placeholder="Petunjuk khusus bagi siswa saat mengerjakan..." class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl" rows="3"></textarea>
          </div>

          <!-- Buttons -->
          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button type="button" @click="showQuizModal = false" class="px-4 py-2 border rounded-xl text-slate-600 hover:bg-slate-50">Batal</button>
            <button 
              type="submit" 
              :disabled="isSyncingQuiz || (quizMode === 'bank' && !selectedBankId)"
              class="px-5 py-2 text-white font-bold rounded-xl transition flex items-center gap-1.5 disabled:opacity-50"
              :class="quizMode === 'bank' ? 'bg-purple-600 hover:bg-purple-700' : 'bg-emerald-600 hover:bg-emerald-700'"
            >
              <svg v-if="isSyncingQuiz" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>{{ isSyncingQuiz ? 'Menyinkronkan...' : (quizMode === 'bank' ? 'Sinkronkan CBT' : 'Simpan Tugas') }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL SUBMIT TASK (SISWA) -->
    <div v-if="showSubmitModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <h3 class="text-lg font-bold text-slate-900">Kumpulkan Tugas: {{ selectedQuiz?.title }}</h3>
        <form @submit.prevent="handleSubmitTask" class="space-y-3 text-xs">
          <textarea v-model="submitForm.submission_text" placeholder="Tuliskan jawaban atau keterangan pengumpulan..." class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl" rows="4"></textarea>
          <div>
            <label class="block font-bold mb-1">Lampirkan File PDF / Lampiran (Opsional)</label>
            <input type="file" @change="handleSubmitFileChange" class="w-full">
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showSubmitModal = false" class="px-4 py-2 border rounded-xl">Batal</button>
            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white font-bold rounded-xl">Kirim Jawaban</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL GRADE SUBMISSION (GURU) -->
    <div v-if="canManageLearning && showGradeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <h3 class="text-lg font-bold text-slate-900">Beri Nilai & Ulasan Feedback</h3>
        <form @submit.prevent="handleSaveGrade" class="space-y-3 text-xs">
          <div>
            <label class="block font-bold mb-1">Nilai (0 - 100)</label>
            <input v-model.number="gradeForm.score" type="number" min="0" max="100" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl" required>
          </div>
          <div>
            <label class="block font-bold mb-1">Catatan / Feedback Guru</label>
            <textarea v-model="gradeForm.teacher_feedback" placeholder="Ulasan Pengerjaan..." class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl" rows="3"></textarea>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showGradeModal = false" class="px-4 py-2 border rounded-xl">Batal</button>
            <button type="submit" class="px-4 py-2 bg-amber-600 text-white font-bold rounded-xl">Simpan Nilai</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
