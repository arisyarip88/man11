<script setup lang="ts">
import { computed, ref, reactive, onMounted, watch } from 'vue'
import SlideOver from '~/components/ui/SlideOver.vue'
import QuickTabs, { type TabItem } from '~/components/ui/QuickTabs.vue'
import QuickAttendanceGrid from '~/components/classroom/QuickAttendanceGrid.vue'

const props = defineProps<{
  courseId: number | string
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const storageBase = config.public.storageBase || 'http://localhost:8000/storage'
const token = useCookie<string | null>('auth_token')

const loading = ref(true)
const course = ref<any>(null)
const meetsList = ref<any[]>([])
const activeMeetId = ref<number | null>(null)
const classStudents = ref<any[]>([])

// Cockpit Tabs
const currentTab = ref('timeline') // timeline | attendance | cbt | gradebook | journal
const cockpitTabs: TabItem[] = [
  { id: 'timeline', label: 'Alur Pertemuan & Materi', icon: '📚' },
  { id: 'attendance', label: 'Presensi Cepat', icon: '📝' },
  { id: 'cbt', label: 'Ujian & CBT', icon: '🎯' },
  { id: 'gradebook', label: 'Buku Nilai (KKTP)', icon: '📊' },
  { id: 'journal', label: 'Jurnal Mengajar', icon: '📖' },
]

// SlideOver States
const slideOverOpen = ref(false)
const slideOverMode = ref<'newMeet' | 'newMaterial' | 'newQuiz' | 'newJournal'>('newMeet')

// SlideOver Forms
const newMeetForm = reactive({
  meeting_number: 1,
  title: '',
  description: '',
  start_time: '',
  end_time: '',
  type: 'offline',
  meeting_link: ''
})

const newMaterialForm = reactive({
  meet_id: '',
  title: '',
  type: 'file',
  content: '',
  external_url: '',
  file: null as File | null
})

const newQuizForm = reactive({
  meet_id: '',
  title: '',
  instructions: '',
  quiz_type: 'multiple_choice',
  duration_minutes: 60,
  max_score: 100,
  due_date: '',
  use_bank: true,
  question_bank_id: ''
})

const newJournalForm = reactive({
  course_id: props.courseId,
  date: new Date().toISOString().split('T')[0],
  title: '',
  learning_activity: '',
  challenges: '',
  student_notes: ''
})

const savingSlideOver = ref(false)
const questionBanks = ref<any[]>([])

// Gradebook State
const gradebookData = ref<any>(null)
const gradebookLoading = ref(false)
const gradebookSaving = ref(false)

// Journal State
const journalsList = ref<any[]>([])
const journalsLoading = ref(false)

// Document Preview Modal State
const previewModalOpen = ref(false)
const previewFileUrl = ref('')
const previewFileTitle = ref('')

// Notification Toast
const toastMsg = ref('')
const showToast = (msg: string) => {
  toastMsg.value = msg
  setTimeout(() => {
    toastMsg.value = ''
  }, 4000)
}

// -------------------------------------------------------------
// Data Fetching
// -------------------------------------------------------------
const fetchCourseData = async () => {
  loading.value = true
  try {
    const [resCourse, resMeets] = await Promise.all([
      $fetch<any>(`${apiBase}/courses/${props.courseId}`, {
        headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
      }),
      $fetch<any>(`${apiBase}/meets?course_id=${props.courseId}&paginate=false`, {
        headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
      })
    ])

    course.value = resCourse?.data || resCourse || null
    meetsList.value = resMeets?.data || resMeets || []

    if (meetsList.value.length > 0) {
      activeMeetId.value = meetsList.value[meetsList.value.length - 1].id
    }

    // Extract students from course.class.students
    if (course.value?.class?.students) {
      classStudents.value = course.value.class.students
    }
  } catch (err) {
    console.error('Fetch course data error:', err)
  } finally {
    loading.value = false
  }
}

const fetchQuestionBanks = async () => {
  try {
    const res: any = await $fetch(`${apiBase}/question-banks`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    questionBanks.value = res?.data || res || []
  } catch (e) {
    console.error('Fetch question banks error:', e)
  }
}

const fetchGradebook = async () => {
  gradebookLoading.value = true
  try {
    const res: any = await $fetch(`${apiBase}/courses/${props.courseId}/gradebook`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    gradebookData.value = res?.data || res || null
  } catch (e) {
    console.error('Fetch gradebook error:', e)
  } finally {
    gradebookLoading.value = false
  }
}

const fetchJournals = async () => {
  journalsLoading.value = true
  try {
    const res: any = await $fetch(`${apiBase}/teacher-journals?course_id=${props.courseId}`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    journalsList.value = res?.data || res || []
  } catch (e) {
    console.error('Fetch journals error:', e)
  } finally {
    journalsLoading.value = false
  }
}

watch(() => currentTab.value, (tab) => {
  if (tab === 'gradebook' && !gradebookData.value) {
    fetchGradebook()
  } else if (tab === 'journal' && journalsList.value.length === 0) {
    fetchJournals()
  }
})

watch(() => props.courseId, () => {
  fetchCourseData()
  fetchQuestionBanks()
}, { immediate: true })

// -------------------------------------------------------------
// SlideOver Handlers
// -------------------------------------------------------------
const openNewMeetDrawer = () => {
  slideOverMode.value = 'newMeet'
  newMeetForm.meeting_number = (meetsList.value.length || 0) + 1
  newMeetForm.title = ''
  newMeetForm.description = ''
  newMeetForm.start_time = ''
  newMeetForm.end_time = ''
  newMeetForm.type = 'offline'
  newMeetForm.meeting_link = ''
  slideOverOpen.value = true
}

const openNewMaterialDrawer = (meetId?: number) => {
  slideOverMode.value = 'newMaterial'
  newMaterialForm.meet_id = meetId ? String(meetId) : (activeMeetId.value ? String(activeMeetId.value) : '')
  newMaterialForm.title = ''
  newMaterialForm.type = 'file'
  newMaterialForm.content = ''
  newMaterialForm.external_url = ''
  newMaterialForm.file = null
  slideOverOpen.value = true
}

const openNewQuizDrawer = (meetId?: number) => {
  slideOverMode.value = 'newQuiz'
  newQuizForm.meet_id = meetId ? String(meetId) : (activeMeetId.value ? String(activeMeetId.value) : '')
  newQuizForm.title = ''
  newQuizForm.instructions = 'Kerjakan soal dengan teliti dan jujur.'
  newQuizForm.duration_minutes = 60
  newQuizForm.max_score = 100
  newQuizForm.use_bank = questionBanks.value.length > 0
  newQuizForm.question_bank_id = questionBanks.value[0]?.id || ''
  slideOverOpen.value = true
}

const openNewJournalDrawer = () => {
  slideOverMode.value = 'newJournal'
  newJournalForm.date = new Date().toISOString().split('T')[0]
  newJournalForm.title = `Pembelajaran ${course.value?.subject?.name || ''}`
  newJournalForm.learning_activity = ''
  newJournalForm.challenges = ''
  newJournalForm.student_notes = ''
  slideOverOpen.value = true
}

const handleSlideOverSubmit = async () => {
  savingSlideOver.value = true
  try {
    if (slideOverMode.value === 'newMeet') {
      await $fetch(`${apiBase}/meets`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
        body: {
          ...newMeetForm,
          course_id: props.courseId
        }
      })
      showToast('Sesi pertemuan baru berhasil ditambahkan!')
      slideOverOpen.value = false
      await fetchCourseData()
    } else if (slideOverMode.value === 'newMaterial') {
      const formData = new FormData()
      formData.append('meet_id', newMaterialForm.meet_id)
      formData.append('title', newMaterialForm.title)
      formData.append('type', newMaterialForm.type)
      if (newMaterialForm.type === 'text') formData.append('content', newMaterialForm.content)
      if (newMaterialForm.type === 'video' || newMaterialForm.type === 'link') formData.append('external_url', newMaterialForm.external_url)
      if (newMaterialForm.type === 'file' && newMaterialForm.file) formData.append('file', newMaterialForm.file)

      await $fetch(`${apiBase}/materials`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
        body: formData
      })
      showToast('Materi ajar berhasil diunggah!')
      slideOverOpen.value = false
      await fetchCourseData()
    } else if (slideOverMode.value === 'newQuiz') {
      const quizRes: any = await $fetch(`${apiBase}/quizzes`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
        body: {
          meet_id: newQuizForm.meet_id,
          title: newQuizForm.title,
          instructions: newQuizForm.instructions,
          quiz_type: newQuizForm.quiz_type,
          duration_minutes: newQuizForm.duration_minutes,
          max_score: newQuizForm.max_score,
          due_date: newQuizForm.due_date || null
        }
      })

      const quizId = quizRes?.data?.id || quizRes?.id
      if (quizId && newQuizForm.use_bank && newQuizForm.question_bank_id) {
        await $fetch(`${apiBase}/quizzes/${quizId}/import-bank-items`, {
          method: 'POST',
          headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
          body: { question_bank_id: newQuizForm.question_bank_id }
        })
      }
      showToast('Ujian CBT / Kuis berhasil dibuat & disinkronkan!')
      slideOverOpen.value = false
      await fetchCourseData()
    } else if (slideOverMode.value === 'newJournal') {
      await $fetch(`${apiBase}/teacher-journals`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
        body: {
          course_id: props.courseId,
          date: newJournalForm.date,
          title: newJournalForm.title,
          learning_activity: newJournalForm.learning_activity,
          challenges: newJournalForm.challenges || null,
          student_notes: newJournalForm.student_notes || null
        }
      })
      showToast('Jurnal harian mengajar berhasil disimpan!')
      slideOverOpen.value = false
      await fetchJournals()
    }
  } catch (err: any) {
    alert(err?.data?.message || err?.message || 'Gagal menyimpan data.')
  } finally {
    savingSlideOver.value = false
  }
}

// Inline Gradebook Save
const saveGradebookScores = async () => {
  if (!gradebookData.value || !gradebookData.value.rows) return
  gradebookSaving.value = true
  try {
    const flatGrades: any[] = []
    gradebookData.value.rows.forEach((row: any) => {
      Object.keys(row.grades || {}).forEach((compKey) => {
        const item = row.grades[compKey]
        flatGrades.push({
          student_id: row.student_id,
          score_component_id: Number(compKey),
          score: Number(item?.score || 0),
          feedback: item?.feedback || null
        })
      })
    })

    await $fetch(`${apiBase}/courses/${props.courseId}/gradebook/batch-save`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
      body: { grades: flatGrades }
    })
    showToast('Seluruh nilai Kurikulum Merdeka berhasil disimpan!')
    await fetchGradebook()
  } catch (e: any) {
    alert(e?.data?.message || 'Gagal menyimpan nilai buku nilai.')
  } finally {
    gradebookSaving.value = false
  }
}

// Active Meet Computed Details
const currentActiveMeet = computed(() => {
  return meetsList.value.find(m => m.id === activeMeetId.value) || meetsList.value[0] || null
})

const activeMeetAttendances = computed(() => {
  return currentActiveMeet.value?.attendances || []
})

const allCourseQuizzes = computed(() => {
  const list: any[] = []
  meetsList.value.forEach(m => {
    if (m.quizzes && Array.isArray(m.quizzes)) {
      m.quizzes.forEach((q: any) => list.push({ ...q, meet_number: m.meeting_number, meet_title: m.title }))
    }
  })
  return list
})

const openPreviewModal = (url: string, title: string) => {
  previewFileUrl.value = url.startsWith('http') ? url : `${storageBase}/${url}`
  previewFileTitle.value = title
  previewModalOpen.value = true
}
</script>

<template>
  <div class="bg-white border border-slate-200/90 rounded-3xl shadow-sm overflow-hidden flex flex-col">
    <!-- 1. STICKY COCKPIT CONTEXT HEADER -->
    <div class="px-6 py-5 bg-gradient-to-r from-slate-900 to-slate-800 text-white flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="min-w-0">
        <div class="flex items-center gap-2.5">
          <span class="px-2.5 py-0.5 rounded-lg bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 text-[10px] font-black uppercase tracking-wider">
            Cockpit Ruang Kelas
          </span>
          <span class="text-xs text-slate-300 font-medium">
            Semester Ganjil 2025/2026
          </span>
        </div>
        <h2 class="mt-1 text-xl sm:text-2xl font-black truncate flex items-center gap-2">
          <span>{{ course?.subject?.name || 'Mata Pelajaran' }}</span>
          <span class="text-slate-400 font-normal">|</span>
          <span class="text-emerald-400">{{ course?.class?.class_name?.name || course?.class?.className?.name || 'Kelas' }}</span>
        </h2>
        <p class="text-xs text-slate-300 mt-1 flex items-center gap-3">
          <span>👥 {{ classStudents.length || 0 }} Siswa Terdaftar</span>
          <span>•</span>
          <span>📅 {{ meetsList.length }} Sesi Pertemuan</span>
          <span>•</span>
          <span>👨‍🏫 {{ course?.teacher?.name || 'Guru Pengampu' }}</span>
        </p>
      </div>

      <div class="flex items-center gap-2 self-start md:self-center">
        <NuxtLink
          to="/teacher/board"
          class="px-3.5 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition flex items-center gap-1.5 border border-white/10"
          title="Buka Papan Tulis Digital EduBoard"
        >
          <span>🎨</span>
          <span class="hidden sm:inline">EduBoard</span>
        </NuxtLink>
        <button
          type="button"
          class="px-3.5 py-2 rounded-xl bg-white/10 hover:bg-rose-600/80 text-white text-xs font-bold transition flex items-center gap-1.5 border border-white/10"
          @click="emit('close')"
        >
          <span>✕</span>
          <span>Ganti Kelas</span>
        </button>
      </div>
    </div>

    <!-- 2. QUICK TABS BAR -->
    <div class="p-3 bg-slate-50 border-b border-slate-200/80 flex items-center justify-between gap-3 overflow-x-auto">
      <QuickTabs
        v-model="currentTab"
        :items="cockpitTabs"
      />

      <!-- Quick Action Buttons -->
      <div class="flex items-center gap-2 shrink-0">
        <button
          v-if="currentTab === 'timeline'"
          type="button"
          class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition flex items-center gap-1"
          @click="openNewMeetDrawer"
        >
          <span>+</span>
          <span>Pertemuan Baru</span>
        </button>
        <button
          v-if="currentTab === 'cbt'"
          type="button"
          class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition flex items-center gap-1"
          @click="openNewQuizDrawer()"
        >
          <span>+</span>
          <span>Buat Ujian CBT</span>
        </button>
        <button
          v-if="currentTab === 'journal'"
          type="button"
          class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition flex items-center gap-1"
          @click="openNewJournalDrawer"
        >
          <span>+</span>
          <span>Tulis Jurnal Hari Ini</span>
        </button>
      </div>
    </div>

    <!-- Global Toast Alert -->
    <div
      v-if="toastMsg"
      class="m-4 p-3 bg-emerald-600 text-white rounded-2xl text-xs font-bold flex items-center justify-between shadow-md transition"
    >
      <div class="flex items-center gap-2">
        <span>✅</span>
        <span>{{ toastMsg }}</span>
      </div>
      <button type="button" class="text-white/80 hover:text-white" @click="toastMsg = ''">✕</button>
    </div>

    <!-- 3. INTERACTIVE CONTENT WORKSPACE -->
    <div class="p-6">
      <div v-if="loading" class="p-12 text-center text-sm text-slate-400">
        Memuat instrumen ruang kelas...
      </div>

      <!-- ============================================================== -->
      <!-- TAB 1: ALUR PERTEMUAN & MATERI                                 -->
      <!-- ============================================================== -->
      <div v-else-if="currentTab === 'timeline'" class="space-y-4">
        <div v-if="meetsList.length === 0" class="p-12 border-2 border-dashed border-slate-200 rounded-3xl text-center">
          <p class="text-sm font-bold text-slate-700">Belum ada sesi pertemuan di kelas ini.</p>
          <p class="text-xs text-slate-400 mt-1">Buat sesi pertemuan pertama untuk membagikan materi dan absensi siswa.</p>
          <button
            type="button"
            class="mt-4 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs"
            @click="openNewMeetDrawer"
          >
            + Buat Pertemuan 1 Sekarang
          </button>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="meet in meetsList"
            :key="meet.id"
            class="border rounded-2xl p-5 transition bg-white shadow-xs"
            :class="activeMeetId === meet.id ? 'border-emerald-500 ring-2 ring-emerald-500/10' : 'border-slate-200 hover:border-slate-300'"
          >
            <!-- Meet Summary Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
              <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-black flex items-center justify-center">
                  {{ meet.meeting_number }}
                </span>
                <div>
                  <h4 class="text-sm font-black text-slate-900">{{ meet.title }}</h4>
                  <p class="text-[11px] text-slate-500 mt-0.5">
                    {{ meet.start_time ? meet.start_time.substring(0, 16) : 'Jadwal belum ditentukan' }} • 
                    <span class="uppercase font-bold" :class="meet.type === 'online' ? 'text-blue-600' : 'text-slate-600'">
                      {{ meet.type }}
                    </span>
                  </p>
                </div>
              </div>

              <div class="flex items-center gap-2">
                <button
                  type="button"
                  class="px-2.5 py-1.5 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-700 text-xs font-bold transition"
                  @click="openNewMaterialDrawer(meet.id)"
                >
                  + Materi
                </button>
                <button
                  type="button"
                  class="px-2.5 py-1.5 rounded-xl border border-indigo-200 bg-indigo-50/50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition"
                  @click="openNewQuizDrawer(meet.id)"
                >
                  + Kuis / CBT
                </button>
                <button
                  type="button"
                  class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-800 hover:bg-emerald-600 hover:text-white text-xs font-bold transition"
                  @click="activeMeetId = meet.id; currentTab = 'attendance'"
                >
                  📝 Isi Presensi
                </button>
              </div>
            </div>

            <!-- Description -->
            <p v-if="meet.description" class="mt-3 text-xs text-slate-600 leading-relaxed">
              {{ meet.description }}
            </p>

            <!-- Embedded Materials & Quizzes in this Meet -->
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
              <!-- Materials Box -->
              <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-200/60">
                <p class="text-[11px] font-bold text-slate-700 flex items-center justify-between">
                  <span>📄 Bahan Ajar & Modul ({{ meet.materials?.length || 0 }})</span>
                </p>
                <div v-if="!meet.materials || meet.materials.length === 0" class="text-[11px] text-slate-400 mt-2 italic">
                  Belum ada bahan ajar.
                </div>
                <ul v-else class="mt-2 space-y-1.5">
                  <li
                    v-for="mat in meet.materials"
                    :key="mat.id"
                    class="text-xs flex items-center justify-between gap-2 p-1.5 bg-white rounded-lg border border-slate-200/80 hover:bg-slate-50 transition"
                  >
                    <span class="font-medium text-slate-800 truncate">{{ mat.title }}</span>
                    <a
                      v-if="mat.file_path"
                      :href="`${storageBase}/${mat.file_path}`"
                      target="_blank"
                      class="text-[11px] font-bold text-emerald-700 hover:underline shrink-0"
                    >
                      Buka File ↗
                    </a>
                    <a
                      v-else-if="mat.external_url"
                      :href="mat.external_url"
                      target="_blank"
                      class="text-[11px] font-bold text-blue-700 hover:underline shrink-0"
                    >
                      Tonton ↗
                    </a>
                  </li>
                </ul>
              </div>

              <!-- Quizzes Box -->
              <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-200/60">
                <p class="text-[11px] font-bold text-slate-700 flex items-center justify-between">
                  <span>🎯 Kuis & Asesmen CBT ({{ meet.quizzes?.length || 0 }})</span>
                </p>
                <div v-if="!meet.quizzes || meet.quizzes.length === 0" class="text-[11px] text-slate-400 mt-2 italic">
                  Belum ada ujian/kuis pada sesi ini.
                </div>
                <ul v-else class="mt-2 space-y-1.5">
                  <li
                    v-for="qz in meet.quizzes"
                    :key="qz.id"
                    class="text-xs flex items-center justify-between gap-2 p-1.5 bg-white rounded-lg border border-slate-200/80"
                  >
                    <div class="min-w-0">
                      <p class="font-bold text-slate-800 truncate">{{ qz.title }}</p>
                      <p class="text-[10px] text-slate-500 font-mono">{{ qz.duration_minutes || 60 }} menit • Max: {{ qz.max_score }}</p>
                    </div>
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 shrink-0">
                      {{ qz.quiz_type }}
                    </span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ============================================================== -->
      <!-- TAB 2: PRESENSI CEPAT                                          -->
      <!-- ============================================================== -->
      <div v-else-if="currentTab === 'attendance'" class="space-y-4">
        <!-- Choose Meeting Selector -->
        <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-2xl border border-slate-200/70">
          <label class="text-xs font-bold text-slate-700 shrink-0">Pilih Sesi Pertemuan:</label>
          <select
            v-model="activeMeetId"
            class="px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
          >
            <option v-for="m in meetsList" :key="m.id" :value="m.id">
              Pertemuan {{ m.meeting_number }}: {{ m.title }}
            </option>
          </select>
        </div>

        <QuickAttendanceGrid
          v-if="activeMeetId"
          :meet-id="activeMeetId"
          :meet-title="currentActiveMeet?.title"
          :initial-attendances="activeMeetAttendances"
          :students="classStudents"
          @saved="fetchCourseData"
        />
        <div v-else class="p-8 text-center text-xs text-slate-400">
          Pilih pertemuan terlebih dahulu.
        </div>
      </div>

      <!-- ============================================================== -->
      <!-- TAB 3: UJIAN & CBT                                             -->
      <!-- ============================================================== -->
      <div v-else-if="currentTab === 'cbt'" class="space-y-4">
        <div v-if="allCourseQuizzes.length === 0" class="p-12 border-2 border-dashed border-slate-200 rounded-3xl text-center">
          <p class="text-sm font-bold text-slate-700">Belum ada ujian CBT pada kelas ini.</p>
          <p class="text-xs text-slate-400 mt-1">Buat kuis baru atau impor butir soal dari Bank Soal secara instan.</p>
          <button
            type="button"
            class="mt-4 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs"
            @click="openNewQuizDrawer()"
          >
            + Buat Ujian CBT Baru
          </button>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="quiz in allCourseQuizzes"
            :key="quiz.id"
            class="border border-slate-200 rounded-2xl bg-white p-5 shadow-xs flex flex-col justify-between hover:border-indigo-300 transition"
          >
            <div>
              <div class="flex items-center justify-between gap-2">
                <span class="px-2 py-0.5 rounded-lg text-[10px] font-black bg-indigo-50 text-indigo-800 border border-indigo-200">
                  Pertemuan {{ quiz.meet_number }}
                </span>
                <span class="text-[11px] font-semibold text-slate-400">
                  {{ quiz.duration_minutes || 60 }} Menit
                </span>
              </div>
              <h4 class="mt-2 text-sm font-black text-slate-900">{{ quiz.title }}</h4>
              <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ quiz.instructions || 'Tidak ada instruksi khusus.' }}</p>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
              <span class="text-slate-400 font-mono text-[11px]">Skor Maks: {{ quiz.max_score }}</span>
              <span class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-800 font-bold text-[10px]">
                {{ quiz.submissions_count || 0 }} Siswa Kumpul
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- ============================================================== -->
      <!-- TAB 4: BUKU NILAI (GRADEBOOK)                                   -->
      <!-- ============================================================== -->
      <div v-else-if="currentTab === 'gradebook'" class="space-y-4">
        <div v-if="gradebookLoading" class="p-12 text-center text-xs text-slate-400">
          Memuat buku nilai Kurikulum Merdeka...
        </div>
        <div v-else-if="gradebookData" class="space-y-4">
          <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <p class="text-xs font-bold text-slate-700">
                Standar Kriteria Ketercapaian Tujuan Pembelajaran (KKTP): <span class="text-emerald-700 font-black">≥ {{ gradebookData.kktp || 75 }}</span>
              </p>
              <p class="text-[11px] text-slate-400 mt-0.5">Komponen Nilai: Formatif TP, Sumatif Tengah Semester (STS), Sumatif Akhir Semester (SAS).</p>
            </div>
            <button
              type="button"
              :disabled="gradebookSaving"
              class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition flex items-center gap-1.5 disabled:opacity-50"
              @click="saveGradebookScores"
            >
              <span>{{ gradebookSaving ? 'Menyimpan...' : '💾 Simpan Perubahan Nilai' }}</span>
            </button>
          </div>

          <!-- Matriks Nilai Table -->
          <div class="border border-slate-200 rounded-2xl bg-white overflow-x-auto shadow-xs">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-50 text-slate-700 border-b border-slate-200">
                <tr>
                  <th class="p-3 font-bold w-12 text-center">No</th>
                  <th class="p-3 font-bold min-w-[140px]">Nama Siswa</th>
                  <th
                    v-for="comp in gradebookData.components"
                    :key="comp.id"
                    class="p-3 font-bold text-center min-w-[90px]"
                  >
                    <span>{{ comp.code }}</span>
                    <span class="block text-[9px] font-normal text-slate-400">({{ comp.type }})</span>
                  </th>
                  <th class="p-3 font-black text-center min-w-[90px] bg-slate-100/80">Nilai Akhir</th>
                  <th class="p-3 font-bold text-center w-24">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="(row, idx) in gradebookData.rows" :key="row.student_id" class="hover:bg-slate-50/60">
                  <td class="p-3 text-center text-slate-400 font-medium">{{ idx + 1 }}</td>
                  <td class="p-3 font-bold text-slate-900">
                    <p>{{ row.name }}</p>
                    <p class="text-[10px] text-slate-400 font-mono">NISN: {{ row.nisn }}</p>
                  </td>
                  <td
                    v-for="comp in gradebookData.components"
                    :key="comp.id"
                    class="p-2 text-center"
                  >
                    <input
                      v-if="row.grades && row.grades[comp.id]"
                      v-model.number="row.grades[comp.id].score"
                      type="number"
                      min="0"
                      max="100"
                      class="w-16 px-1.5 py-1 text-center bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    >
                    <span v-else class="text-slate-300">-</span>
                  </td>
                  <td class="p-3 text-center font-black text-slate-900 bg-slate-50/50">
                    {{ row.final_score }}
                  </td>
                  <td class="p-3 text-center">
                    <span
                      class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                      :class="row.is_passed ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                    >
                      {{ row.is_passed ? 'Tuntas' : 'Remedial' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ============================================================== -->
      <!-- TAB 5: JURNAL MENGAJAR                                         -->
      <!-- ============================================================== -->
      <div v-else-if="currentTab === 'journal'" class="space-y-4">
        <div v-if="journalsLoading" class="p-12 text-center text-xs text-slate-400">
          Memuat catatan jurnal mengajar...
        </div>
        <div v-else-if="journalsList.length === 0" class="p-12 border-2 border-dashed border-slate-200 rounded-3xl text-center">
          <p class="text-sm font-bold text-slate-700">Belum ada jurnal mengajar tercatat untuk kelas ini.</p>
          <p class="text-xs text-slate-400 mt-1">Catat rangkuman capaian materi dan catatan siswa hari ini.</p>
          <button
            type="button"
            class="mt-4 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs"
            @click="openNewJournalDrawer"
          >
            + Tulis Jurnal Mengajar
          </button>
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="jrn in journalsList"
            :key="jrn.id"
            class="border border-slate-200 rounded-2xl bg-white p-5 shadow-xs"
          >
            <div class="flex items-center justify-between gap-3 pb-2 border-b border-slate-100">
              <span class="text-xs font-bold text-slate-500 font-mono">📅 {{ jrn.date }}</span>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                :class="jrn.status === 'verified' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
              >
                {{ jrn.status === 'verified' ? '✓ Terverifikasi' : 'Menunggu Verifikasi' }}
              </span>
            </div>
            <h4 class="mt-2 text-sm font-black text-slate-900">{{ jrn.title }}</h4>
            <p class="mt-1 text-xs text-slate-600">{{ jrn.learning_activity }}</p>
            <p v-if="jrn.challenges" class="mt-2 text-[11px] text-amber-800 bg-amber-50 p-2 rounded-xl border border-amber-200">
              ⚠️ Kendala: {{ jrn.challenges }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- 4. SLIDE-OVER DRAWER FOR FORMS (ZERO URL REDIRECT) -->
    <SlideOver
      v-model="slideOverOpen"
      :title="
        slideOverMode === 'newMeet' ? 'Tambah Sesi Pertemuan Baru' :
        slideOverMode === 'newMaterial' ? 'Unggah Materi Ajar' :
        slideOverMode === 'newQuiz' ? 'Buat Ujian CBT / Kuis' : 'Catat Jurnal Mengajar'
      "
      :subtitle="`${course?.subject?.name || ''} - ${course?.class?.class_name?.name || course?.class?.className?.name || ''}`"
    >
      <!-- Form 1: New Meet -->
      <form v-if="slideOverMode === 'newMeet'" class="space-y-4" @submit.prevent="handleSlideOverSubmit">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Pertemuan Ke-</label>
          <input
            v-model.number="newMeetForm.meeting_number"
            type="number"
            required
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-bold"
          >
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Judul / Topik Pertemuan</label>
          <input
            v-model="newMeetForm.title"
            type="text"
            required
            placeholder="Contoh: Keanekaragaman Hayati & Ekosistem"
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          >
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Tipe Pelaksanaan</label>
          <select v-model="newMeetForm.type" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            <option value="offline">Tatap Muka (Offline di Kelas)</option>
            <option value="online">Daring (Online / Virtual)</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Deskripsi / Arahan Guru</label>
          <textarea
            v-model="newMeetForm.description"
            rows="3"
            placeholder="Instruksi untuk dipelajari siswa..."
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          />
        </div>
      </form>

      <!-- Form 2: New Material -->
      <form v-else-if="slideOverMode === 'newMaterial'" class="space-y-4" @submit.prevent="handleSlideOverSubmit">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Pertemuan</label>
          <select v-model="newMaterialForm.meet_id" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            <option v-for="m in meetsList" :key="m.id" :value="String(m.id)">
              Pertemuan {{ m.meeting_number }}: {{ m.title }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Judul Materi</label>
          <input
            v-model="newMaterialForm.title"
            type="text"
            required
            placeholder="Contoh: Modul 1 - Struktur Sel Hewan & Tumbuhan"
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          >
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Tipe Materi</label>
          <select v-model="newMaterialForm.type" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            <option value="file">Dokumen PDF / PPT / Word</option>
            <option value="video">Tautan Video YouTube</option>
            <option value="text">Artikel / Teks Bacaan</option>
          </select>
        </div>
        <div v-if="newMaterialForm.type === 'file'">
          <label class="block text-xs font-bold text-slate-700 mb-1">Pilih File</label>
          <input
            type="file"
            required
            @change="(e: any) => newMaterialForm.file = e.target.files[0]"
            class="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-800"
          >
        </div>
        <div v-if="newMaterialForm.type === 'video' || newMaterialForm.type === 'link'">
          <label class="block text-xs font-bold text-slate-700 mb-1">URL Tautan / Video YouTube</label>
          <input
            v-model="newMaterialForm.external_url"
            type="url"
            required
            placeholder="https://..."
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          >
        </div>
        <div v-if="newMaterialForm.type === 'text'">
          <label class="block text-xs font-bold text-slate-700 mb-1">Konten Bacaan</label>
          <textarea
            v-model="newMaterialForm.content"
            rows="5"
            placeholder="Tulis materi penjelasan di sini..."
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          />
        </div>
      </form>

      <!-- Form 3: New Quiz / CBT -->
      <form v-else-if="slideOverMode === 'newQuiz'" class="space-y-4" @submit.prevent="handleSlideOverSubmit">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Pertemuan</label>
          <select v-model="newQuizForm.meet_id" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            <option v-for="m in meetsList" :key="m.id" :value="String(m.id)">
              Pertemuan {{ m.meeting_number }}: {{ m.title }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Judul Ujian / Kuis</label>
          <input
            v-model="newQuizForm.title"
            type="text"
            required
            placeholder="Contoh: Asesmen Sumatif TP 1 - Ekosistem"
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          >
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Durasi Pengerjaan</label>
            <input
              v-model.number="newQuizForm.duration_minutes"
              type="number"
              min="5"
              max="240"
              required
              class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
            >
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Skor Maksimum</label>
            <input
              v-model.number="newQuizForm.max_score"
              type="number"
              required
              class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
            >
          </div>
        </div>

        <!-- Bank Soal Option -->
        <div class="p-3 bg-indigo-50/60 border border-indigo-200/80 rounded-2xl space-y-2">
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="newQuizForm.use_bank" type="checkbox" class="rounded text-indigo-600">
            <span class="text-xs font-bold text-indigo-900">Salin Butir Soal dari Bank Soal (Otomatis)</span>
          </label>
          <div v-if="newQuizForm.use_bank">
            <select v-model="newQuizForm.question_bank_id" class="w-full px-3 py-2 bg-white border border-indigo-200 rounded-xl text-xs font-medium">
              <option v-for="b in questionBanks" :key="b.id" :value="b.id">
                {{ b.title }} ({{ b.total_items || 0 }} Butir Soal)
              </option>
            </select>
          </div>
        </div>
      </form>

      <!-- Form 4: New Journal -->
      <form v-else-if="slideOverMode === 'newJournal'" class="space-y-4" @submit.prevent="handleSlideOverSubmit">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Mengajar</label>
          <input
            v-model="newJournalForm.date"
            type="date"
            required
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          >
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Topik / Materi yang Diajarkan</label>
          <input
            v-model="newJournalForm.title"
            type="text"
            required
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          >
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Rangkuman Aktivitas Pembelajaran</label>
          <textarea
            v-model="newJournalForm.learning_activity"
            rows="3"
            required
            placeholder="Contoh: Diskusi kelompok tentang siklus karbon..."
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          />
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Kendala / Siswa yang Butuh Perhatian (Opsional)</label>
          <textarea
            v-model="newJournalForm.challenges"
            rows="2"
            placeholder="Catatan jika ada siswa yang kurang fokus..."
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          />
        </div>
      </form>

      <template #footer>
        <button
          type="button"
          class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-100 transition"
          @click="slideOverOpen = false"
        >
          Batal
        </button>
        <button
          type="button"
          :disabled="savingSlideOver"
          class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition disabled:opacity-50"
          @click="handleSlideOverSubmit"
        >
          {{ savingSlideOver ? 'Menyimpan...' : 'Simpan Data' }}
        </button>
      </template>
    </SlideOver>
  </div>
</template>
