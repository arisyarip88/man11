<script setup lang="ts">
import { computed, ref, reactive, onMounted, watch } from 'vue'
import SlideOver from '~/components/ui/SlideOver.vue'
import QuickTabs, { type TabItem } from '~/components/ui/QuickTabs.vue'
import QuickAttendanceGrid from '~/components/classroom/QuickAttendanceGrid.vue'
import QuestionImageUploader from '~/components/quiz/QuestionImageUploader.vue'

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

// Cockpit Tabs (Zero Emoji)
const currentTab = ref('timeline') // timeline | attendance | cbt | gradebook | journal
const cockpitTabs: TabItem[] = [
  { id: 'timeline', label: 'Alur Belajar & Modul' },
  { id: 'attendance', label: 'Presensi Siswa' },
  { id: 'cbt', label: 'Ujian CBT' },
  { id: 'gradebook', label: 'Buku Nilai' },
  { id: 'journal', label: 'Jurnal Guru' },
]

// Active Stage Per Sub-bab (Meet)
// Values: 'all' | 'pretest' | 'material' | 'discussion' | 'quiz' | 'assignment' | 'posttest'
const meetActiveStage = reactive<Record<number, string>>({})

const setMeetStage = (meetId: number, stage: string) => {
  meetActiveStage[meetId] = stage
}

const getMeetStage = (meetId: number) => {
  return meetActiveStage[meetId] || 'all'
}

// SlideOver States
const slideOverOpen = ref(false)
const slideOverMode = ref<'newMeet' | 'newMaterial' | 'newQuiz' | 'newJournal' | 'newChapter' | 'newDiscussion'>('newMeet')

// SlideOver Forms
const newMeetForm = reactive({
  meeting_number: 1,
  chapter_id: '' as string | number,
  title: '',
  description: '',
  start_time: '',
  end_time: '',
  type: 'offline',
  meeting_link: ''
})

const newChapterForm = reactive({
  title: '',
  description: '',
  order: 1
})

const newMaterialForm = reactive({
  meet_id: '',
  title: '',
  type: 'file',
  content: '',
  external_url: '',
  file: null as File | null
})

export interface QuestionItemData {
  number: number
  id?: string | number | null
  type: string
  question: string
  image_url?: string | null
  image_meta?: any
  weight: number
  explanation?: string
  options?: Array<{ key: string, text: string, is_correct?: boolean }>
  correct_answer?: string
  data?: any
}

const QUESTION_TYPES = [
  { value: 'pg', label: 'Pilihan Ganda', desc: '1 Kunci Benar' },
  { value: 'pg_kompleks', label: 'PG Kompleks', desc: 'Multi Kunci Benar' },
  { value: 'isian', label: 'Isian Singkat', desc: 'Cocok Kata Kunci' },
  { value: 'menjodohkan', label: 'Menjodohkan', desc: 'Pasangan Kiri-Kanan' },
  { value: 'benar_salah', label: 'Benar / Salah', desc: 'Pernyataan B / S' },
  { value: 'esai', label: 'Esai / Uraian', desc: 'Penilaian Guru' }
]

const formatDateTimeInput = (d: Date | string | null | undefined): string => {
  if (!d) return ''
  const date = typeof d === 'string' ? new Date(d) : d
  if (isNaN(date.getTime())) return ''
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const applyTimePreset = (target: { available_from?: string, available_until?: string, due_date?: string }, preset: 'rabu' | '2days' | '7days' | 'none') => {
  const now = new Date()
  target.available_from = formatDateTimeInput(now)

  if (preset === 'none') {
    target.available_until = ''
    if ('due_date' in target) target.due_date = ''
    return
  }

  if (preset === '2days') {
    const end = new Date(now)
    end.setDate(end.getDate() + 2)
    end.setHours(23, 59, 0, 0)
    target.available_until = formatDateTimeInput(end)
    if ('due_date' in target) target.due_date = target.available_until
  } else if (preset === 'rabu') {
    const end = new Date(now)
    const currentDay = end.getDay() // 0 Sun, 1 Mon, 2 Tue, 3 Wed...
    const daysUntilWed = (3 - currentDay + 7) % 7 || 7
    end.setDate(end.getDate() + daysUntilWed)
    end.setHours(23, 0, 0, 0)
    target.available_until = formatDateTimeInput(end)
    if ('due_date' in target) target.due_date = target.available_until
  } else if (preset === '7days') {
    const end = new Date(now)
    end.setDate(end.getDate() + 7)
    end.setHours(23, 59, 0, 0)
    target.available_until = formatDateTimeInput(end)
    if ('due_date' in target) target.due_date = target.available_until
  }
}

const getQuizTimeBadge = (quiz: any) => {
  if (!quiz) return { status: 'open', label: 'Akses Terbuka', class: 'bg-slate-50 text-slate-700 border-slate-200' }
  const now = new Date()
  const from = quiz.available_from ? new Date(quiz.available_from) : null
  const until = quiz.available_until ? new Date(quiz.available_until) : (quiz.due_date ? new Date(quiz.due_date) : null)

  const formatShort = (d: Date) => {
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) + ' ' + 
      d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB'
  }

  if (until && !isNaN(until.getTime()) && now > until) {
    return {
      status: 'expired',
      label: `Tutup sejak ${formatShort(until)}`,
      class: 'bg-rose-50 text-rose-800 border-rose-200'
    }
  }

  if (from && !isNaN(from.getTime()) && now < from) {
    return {
      status: 'scheduled',
      label: `Buka ${formatShort(from)}`,
      class: 'bg-amber-50 text-amber-800 border-amber-200'
    }
  }

  if (until && !isNaN(until.getTime())) {
    return {
      status: 'active',
      label: `Buka s/d ${formatShort(until)}`,
      class: 'bg-emerald-50 text-emerald-800 border-emerald-200'
    }
  }

  return {
    status: 'open',
    label: 'Akses Terbuka',
    class: 'bg-slate-50 text-slate-700 border-slate-200'
  }
}

const createBlankQuestion = (type = 'pg', number = 1): QuestionItemData => {
  const base: QuestionItemData = {
    number,
    type,
    question: '',
    image_url: null,
    image_meta: null,
    weight: 10,
    explanation: '',
    options: [
      { key: 'A', text: '', is_correct: true },
      { key: 'B', text: '', is_correct: false },
      { key: 'C', text: '', is_correct: false },
      { key: 'D', text: '', is_correct: false }
    ],
    correct_answer: 'A',
    data: null
  }

  if (type === 'pg_kompleks') {
    base.options = [
      { key: 'A', text: '', is_correct: true },
      { key: 'B', text: '', is_correct: false },
      { key: 'C', text: '', is_correct: true },
      { key: 'D', text: '', is_correct: false }
    ]
  } else if (type === 'isian') {
    base.options = []
    base.data = { accepted_answers: [''] }
  } else if (type === 'menjodohkan') {
    base.options = []
    base.data = {
      pairs: [
        { id: '1', left: '', right: '' },
        { id: '2', left: '', right: '' }
      ]
    }
  } else if (type === 'benar_salah') {
    base.options = []
    base.data = {
      statements: [
        { id: '1', text: '', answer: true },
        { id: '2', text: '', answer: false }
      ]
    }
  } else if (type === 'esai') {
    base.options = []
  }

  return base
}

const changeQuestionType = (q: any, newType: string) => {
  q.type = newType
  if (newType === 'pg') {
    if (!q.options || q.options.length < 2) {
      q.options = [
        { key: 'A', text: '', is_correct: true },
        { key: 'B', text: '', is_correct: false },
        { key: 'C', text: '', is_correct: false },
        { key: 'D', text: '', is_correct: false }
      ]
    }
    const correctOpt = q.options.find((o: any) => o.is_correct)
    q.correct_answer = correctOpt ? correctOpt.key : (q.options[0]?.key || 'A')
  } else if (newType === 'pg_kompleks') {
    if (!q.options || q.options.length < 2) {
      q.options = [
        { key: 'A', text: '', is_correct: true },
        { key: 'B', text: '', is_correct: false },
        { key: 'C', text: '', is_correct: true },
        { key: 'D', text: '', is_correct: false }
      ]
    }
  } else if (newType === 'isian') {
    if (!q.data || !Array.isArray(q.data.accepted_answers) || q.data.accepted_answers.length === 0) {
      q.data = { accepted_answers: [''] }
    }
  } else if (newType === 'menjodohkan') {
    if (!q.data || !Array.isArray(q.data.pairs) || q.data.pairs.length === 0) {
      q.data = {
        pairs: [
          { id: '1', left: '', right: '' },
          { id: '2', left: '', right: '' }
        ]
      }
    }
  } else if (newType === 'benar_salah') {
    if (!q.data || !Array.isArray(q.data.statements) || q.data.statements.length === 0) {
      q.data = {
        statements: [
          { id: '1', text: '', answer: true },
          { id: '2', text: '', answer: false }
        ]
      }
    }
  }
}

// Helpers for question components
const addAcceptedAnswer = (q: any) => {
  if (!q.data) q.data = {}
  if (!Array.isArray(q.data.accepted_answers)) q.data.accepted_answers = []
  q.data.accepted_answers.push('')
}

const removeAcceptedAnswer = (q: any, idx: number) => {
  if (q.data?.accepted_answers && q.data.accepted_answers.length > 1) {
    q.data.accepted_answers.splice(idx, 1)
  }
}

const addPair = (q: any) => {
  if (!q.data) q.data = {}
  if (!Array.isArray(q.data.pairs)) q.data.pairs = []
  q.data.pairs.push({ id: String(Date.now()), left: '', right: '' })
}

const removePair = (q: any, idx: number) => {
  if (q.data?.pairs && q.data.pairs.length > 1) {
    q.data.pairs.splice(idx, 1)
  }
}

const addStatement = (q: any) => {
  if (!q.data) q.data = {}
  if (!Array.isArray(q.data.statements)) q.data.statements = []
  q.data.statements.push({ id: String(Date.now()), text: '', answer: true })
}

const removeStatement = (q: any, idx: number) => {
  if (q.data?.statements && q.data.statements.length > 1) {
    q.data.statements.splice(idx, 1)
  }
}

const addQuestionOption = (q: any) => {
  if (!q.options) q.options = []
  const nextKey = String.fromCharCode(65 + q.options.length)
  q.options.push({ key: nextKey, text: '', is_correct: false })
}

const removeQuestionOption = (q: any, optIndex: number) => {
  if (!q || !q.options || q.options.length <= 2) return
  q.options.splice(optIndex, 1)
  q.options.forEach((opt: any, idx: number) => {
    opt.key = String.fromCharCode(65 + idx)
  })
  if (q.type === 'pg' && !q.options.some((o: any) => o.key === q.correct_answer)) {
    q.correct_answer = q.options[0]?.key || 'A'
    q.options[0].is_correct = true
  }
}

const newQuizForm = reactive({
  meet_id: '',
  title: '',
  instructions: '',
  quiz_type: 'multiple_choice',
  activity_category: 'quiz' as 'pretest' | 'quiz' | 'assignment' | 'posttest',
  duration_minutes: 60,
  max_score: 100,
  available_from: '',
  available_until: '',
  due_date: '',
  question_source: 'manual' as 'manual' | 'bank' | 'later',
  question_bank_id: '' as string | number,
  manual_questions: [] as QuestionItemData[]
})

const addDraftQuestion = () => {
  newQuizForm.manual_questions.push(createBlankQuestion('pg', newQuizForm.manual_questions.length + 1))
}

const removeDraftQuestion = (index: number) => {
  newQuizForm.manual_questions.splice(index, 1)
  newQuizForm.manual_questions.forEach((q, idx) => {
    q.number = idx + 1
  })
}

// Question Manager Modal State
const isQuestionManagerOpen = ref(false)
const activeManagingQuiz = ref<any>(null)
const managingQuestions = ref<QuestionItemData[]>([])
const managingSaving = ref(false)
const selectedBankToImport = ref<number | string>('')
const importingBank = ref(false)
const managingSchedule = reactive({
  available_from: '',
  available_until: '',
  duration_minutes: 60
})

const openQuestionManager = (quiz: any) => {
  activeManagingQuiz.value = quiz
  managingQuestions.value = JSON.parse(JSON.stringify(quiz.questions_data || []))
  selectedBankToImport.value = ''
  managingSchedule.available_from = formatDateTimeInput(quiz.available_from)
  managingSchedule.available_until = formatDateTimeInput(quiz.available_until || quiz.due_date)
  managingSchedule.duration_minutes = quiz.duration_minutes || 60
  isQuestionManagerOpen.value = true
}

const addManagingQuestion = () => {
  managingQuestions.value.push(createBlankQuestion('pg', managingQuestions.value.length + 1))
}

const removeManagingQuestion = (index: number) => {
  managingQuestions.value.splice(index, 1)
  managingQuestions.value.forEach((q, idx) => {
    q.number = idx + 1
  })
}

const saveManagingQuestions = async () => {
  if (!activeManagingQuiz.value) return
  managingSaving.value = true
  try {
    const res: any = await $fetch(`${apiBase}/quizzes/${activeManagingQuiz.value.id}/questions`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
      body: { questions: managingQuestions.value }
    })
    
    // Update quiz metadata and schedule
    await $fetch(`${apiBase}/quizzes/${activeManagingQuiz.value.id}`, {
      method: 'PUT',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
      body: {
        meet_id: activeManagingQuiz.value.meet_id,
        title: activeManagingQuiz.value.title,
        instructions: activeManagingQuiz.value.instructions,
        quiz_type: activeManagingQuiz.value.quiz_type,
        activity_category: activeManagingQuiz.value.activity_category,
        duration_minutes: managingSchedule.duration_minutes,
        available_from: managingSchedule.available_from || null,
        available_until: managingSchedule.available_until || null,
        due_date: managingSchedule.available_until || activeManagingQuiz.value.due_date || null
      }
    })

    activeManagingQuiz.value.available_from = managingSchedule.available_from || null
    activeManagingQuiz.value.available_until = managingSchedule.available_until || null
    activeManagingQuiz.value.duration_minutes = managingSchedule.duration_minutes
    activeManagingQuiz.value.questions_data = res.data?.questions_data || managingQuestions.value
    activeManagingQuiz.value.max_score = res.data?.max_score || activeManagingQuiz.value.max_score
    showToast(`Berhasil menyimpan ${managingQuestions.value.length} butir soal dan jadwal kuis!`)
    await fetchCourseData()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal menyimpan butir soal.')
  } finally {
    managingSaving.value = false
  }
}

const importBankToActiveQuiz = async () => {
  if (!activeManagingQuiz.value || !selectedBankToImport.value) return
  importingBank.value = true
  try {
    const res: any = await $fetch(`${apiBase}/quizzes/${activeManagingQuiz.value.id}/import-bank-items`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
      body: { question_bank_id: Number(selectedBankToImport.value) }
    })
    
    managingQuestions.value = res.data?.quiz?.questions_data || []
    activeManagingQuiz.value.questions_data = managingQuestions.value
    showToast(res.message || 'Butir soal bank berhasil diimpor!')
    selectedBankToImport.value = ''
    await fetchCourseData()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal mengimpor paket bank soal.')
  } finally {
    importingBank.value = false
  }
}

const newDiscussionForm = reactive({
  meet_id: '',
  message: '',
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
const bankDifficultyFilter = ref('')
const filteredQuestionBanks = computed(() => {
  if (!bankDifficultyFilter.value) return questionBanks.value
  return questionBanks.value.filter(b => b.difficulty === bankDifficultyFilter.value)
})

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

// Chapters & Timeline Search
const chaptersList = ref<any[]>([])
const unassignedMeetsList = ref<any[]>([])
const chaptersLoading = ref(false)
const collapsedChapters = ref<Record<number, boolean>>({})
const toggleChapterCollapse = (chapId: number) => {
  collapsedChapters.value[chapId] = !collapsedChapters.value[chapId]
}

const timelineSearch = ref('')
const filteredMeets = computed(() => {
  if (!timelineSearch.value.trim()) return meetsList.value
  const q = timelineSearch.value.toLowerCase()
  return meetsList.value.filter(m => 
    m.title?.toLowerCase().includes(q) ||
    m.description?.toLowerCase().includes(q) ||
    m.chapter?.title?.toLowerCase().includes(q) ||
    m.materials?.some((mat: any) => mat.title?.toLowerCase().includes(q))
  )
})

const meetsByChapter = computed(() => {
  const groups: { chapter: any | null; meets: any[] }[] = []
  const filtered = filteredMeets.value

  if (chaptersList.value.length > 0) {
    chaptersList.value.forEach(chap => {
      const chMeets = filtered.filter(m => m.chapter_id === chap.id)
      groups.push({ chapter: chap, meets: chMeets })
    })

    const unassigned = filtered.filter(m => !m.chapter_id)
    if (unassigned.length > 0) {
      groups.push({ chapter: null, meets: unassigned })
    }
  } else {
    groups.push({ chapter: null, meets: filtered })
  }

  return groups
})

// Notification Toast
const toastMsg = ref('')
const showToast = (msg: string) => {
  toastMsg.value = msg
  setTimeout(() => {
    toastMsg.value = ''
  }, 4000)
}

// -------------------------------------------------------------
// Sequential Learning Path Categorizers
// -------------------------------------------------------------
const getPretests = (meet: any) => {
  return (meet?.quizzes || []).filter((q: any) => q.activity_category === 'pretest')
}

const getMaterials = (meet: any) => {
  return meet?.materials || []
}

const getDiscussions = (meet: any) => {
  return meet?.discussions || []
}

const getQuizzes = (meet: any) => {
  return (meet?.quizzes || []).filter((q: any) => 
    q.activity_category === 'quiz' || 
    (!q.activity_category && q.quiz_type !== 'assignment')
  )
}

const getAssignments = (meet: any) => {
  return (meet?.quizzes || []).filter((q: any) => 
    q.activity_category === 'assignment' || 
    q.quiz_type === 'assignment'
  )
}

const getPosttests = (meet: any) => {
  return (meet?.quizzes || []).filter((q: any) => q.activity_category === 'posttest')
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
      $fetch<any>(`${apiBase}/meets?course_id=${props.courseId}&paginate=false&with_details=true`, {
        headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
      })
    ])

    course.value = resCourse?.data || resCourse || null
    meetsList.value = resMeets?.data || resMeets || []

    if (meetsList.value.length > 0 && !activeMeetId.value) {
      activeMeetId.value = meetsList.value[meetsList.value.length - 1].id
    }

    if (course.value?.class?.students) {
      classStudents.value = course.value.class.students
    }

    await fetchChapters()
  } catch (err) {
    console.error('Fetch course data error:', err)
  } finally {
    loading.value = false
  }
}

// Duplikat Pertemuan / Sub-bab
const duplicatingMeetId = ref<number | null>(null)
const duplicateMeet = async (meet: any) => {
  if (!confirm(`Duplikat Sub-bab ${meet.meeting_number} ("${meet.title}") beserta seluruh modul, kuis, dan aktivitas belajar ke sesi baru?`)) return

  duplicatingMeetId.value = meet.id
  try {
    const res: any = await $fetch(`${apiBase}/meets/${meet.id}/duplicate`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    showToast(res.message || `Sub-bab berhasil diduplikasi menjadi Sub-bab ${res.data?.meeting_number || ''}!`)
    await fetchCourseData()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal menduplikat sub-bab.')
  } finally {
    duplicatingMeetId.value = null
  }
}

const fetchQuestionBanks = async () => {
  try {
    const res: any = await $fetch(`${apiBase}/question-banks?all=true`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    const raw = res?.data?.data || res?.data || res || []
    questionBanks.value = Array.isArray(raw) ? raw : []
  } catch (e) {
    console.error('Fetch question banks error:', e)
  }
}

const fetchChapters = async () => {
  chaptersLoading.value = true
  try {
    const res: any = await $fetch(`${apiBase}/courses/${props.courseId}/chapters`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    chaptersList.value = res?.chapters || []
    unassignedMeetsList.value = res?.unassigned_meets || []
  } catch (e) {
    console.error('Fetch chapters error:', e)
  } finally {
    chaptersLoading.value = false
  }
}

const openNewChapterDrawer = () => {
  slideOverMode.value = 'newChapter'
  newChapterForm.title = ''
  newChapterForm.description = ''
  newChapterForm.order = (chaptersList.value.length || 0) + 1
  slideOverOpen.value = true
}

const deleteChapter = async (chap: any) => {
  if (!confirm(`Hapus ${chap.title}? Sub-bab di dalamnya akan kembali berstatus tanpa bab.`)) return
  try {
    await $fetch(`${apiBase}/chapters/${chap.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    showToast('Bab berhasil dihapus!')
    await fetchChapters()
    await fetchCourseData()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal menghapus bab.')
  }
}

const assignMeetToChapter = async (meetId: number, chapterId: string | number | null) => {
  try {
    const meetObj = meetsList.value.find(m => m.id === meetId)
    if (!meetObj) return
    await $fetch(`${apiBase}/meets/${meetId}`, {
      method: 'PUT',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
      body: {
        course_id: props.courseId,
        meeting_number: meetObj.meeting_number,
        title: meetObj.title,
        description: meetObj.description,
        type: meetObj.type || 'offline',
        start_time: meetObj.start_time,
        end_time: meetObj.end_time,
        chapter_id: chapterId ? Number(chapterId) : null
      }
    })
    showToast('Sub-bab berhasil dialokasikan ke bab!')
    await fetchChapters()
    await fetchCourseData()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal memindahkan sub-bab.')
  }
}

const deleteMaterial = async (mat: any) => {
  if (!confirm(`Hapus bahan ajar "${mat.title}"?`)) return
  try {
    await $fetch(`${apiBase}/materials/${mat.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    showToast('Bahan ajar berhasil dihapus.')
    await fetchCourseData()
  } catch (e: any) {
    alert(e?.data?.message || 'Gagal menghapus materi.')
  }
}

const deleteQuiz = async (quiz: any) => {
  if (!confirm(`Hapus aktivitas "${quiz.title}"?`)) return
  try {
    await $fetch(`${apiBase}/quizzes/${quiz.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    showToast('Aktivitas berhasil dihapus.')
    await fetchCourseData()
  } catch (e: any) {
    alert(e?.data?.message || 'Gagal menghapus aktivitas.')
  }
}

const deleteMeet = async (meet: any) => {
  if (!confirm(`Hapus sub-bab "${meet.title}" beserta seluruh isinya?`)) return
  try {
    await $fetch(`${apiBase}/meets/${meet.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    showToast('Sub-bab berhasil dihapus.')
    await fetchCourseData()
  } catch (e: any) {
    alert(e?.data?.message || 'Gagal menghapus sub-bab.')
  }
}

const activeAddMenuMeetId = ref<number | null>(null)
const toggleAddMenu = (meetId: number) => {
  activeAddMenuMeetId.value = activeAddMenuMeetId.value === meetId ? null : meetId
}

const getMeetActivities = (meet: any) => {
  const items: any[] = []

  // 1. Pretests
  ;(meet?.quizzes || [])
    .filter((q: any) => q.activity_category === 'pretest')
    .forEach((q: any) => {
      const qCount = Array.isArray(q.questions_data) ? q.questions_data.length : 0
      items.push({
        id: q.id,
        raw: q,
        itemType: 'pretest',
        label: 'Pretest',
        title: q.title,
        color: 'rose',
        timeBadge: getQuizTimeBadge(q),
        info: `${qCount > 0 ? qCount + ' Soal CBT' : 'Belum Ada Soal'} • ${q.duration_minutes} menit • Skor Maks ${q.max_score}`
      })
    })

  // 2. Modul / Materials
  ;(meet?.materials || []).forEach((m: any) => {
    items.push({
      id: m.id,
      raw: m,
      itemType: 'material',
      label: 'Modul',
      title: m.title,
      color: 'emerald',
      info: m.file_path ? 'Dokumen File / PDF' : m.external_url ? 'Tautan Video / Eksternal' : 'Teks Materi'
    })
  })

  // 3. Forum Diskusi
  ;(meet?.discussions || []).forEach((d: any) => {
    items.push({
      id: d.id,
      raw: d,
      itemType: 'discussion',
      label: 'Diskusi',
      title: d.message,
      color: 'sky',
      info: `${d.teacher?.name || 'Guru'} • ${d.replies?.length || 0} tanggapan`
    })
  })

  // 4. Quizzes
  ;(meet?.quizzes || [])
    .filter((q: any) => q.activity_category === 'quiz' || (!q.activity_category && q.quiz_type !== 'assignment'))
    .forEach((q: any) => {
      const qCount = Array.isArray(q.questions_data) ? q.questions_data.length : 0
      items.push({
        id: q.id,
        raw: q,
        itemType: 'quiz',
        label: 'Quiz Formatif',
        title: q.title,
        color: 'indigo',
        timeBadge: getQuizTimeBadge(q),
        info: `${qCount > 0 ? qCount + ' Soal CBT' : 'Belum Ada Soal'} • ${q.duration_minutes} menit • Skor Maks ${q.max_score}`
      })
    })

  // 5. Assignments (Tugas Mandiri)
  ;(meet?.quizzes || [])
    .filter((q: any) => q.activity_category === 'assignment' || (!q.activity_category && q.quiz_type === 'assignment'))
    .forEach((q: any) => {
      items.push({
        id: q.id,
        raw: q,
        itemType: 'assignment',
        label: 'Tugas Mandiri',
        title: q.title,
        color: 'amber',
        timeBadge: getQuizTimeBadge(q),
        info: `Tenggat: ${q.available_until ? q.available_until.substring(0, 16).replace('T', ' ') : (q.due_date ? q.due_date.substring(0, 10) : 'Tanpa batas')} • Skor Maks ${q.max_score}`
      })
    })

  // 6. Posttests
  ;(meet?.quizzes || [])
    .filter((q: any) => q.activity_category === 'posttest')
    .forEach((q: any) => {
      const qCount = Array.isArray(q.questions_data) ? q.questions_data.length : 0
      items.push({
        id: q.id,
        raw: q,
        itemType: 'posttest',
        label: 'Posttest Sumatif',
        title: q.title,
        color: 'purple',
        timeBadge: getQuizTimeBadge(q),
        info: `${qCount > 0 ? qCount + ' Soal CBT' : 'Belum Ada Soal'} • ${q.duration_minutes} menit • Skor Maks ${q.max_score}`
      })
    })

  return items
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

const saveGradebookScores = async () => {
  if (!gradebookData.value?.rows) return
  gradebookSaving.value = true
  try {
    const payloadGrades: any[] = []
    gradebookData.value.rows.forEach((r: any) => {
      if (r.grades) {
        Object.keys(r.grades).forEach(compKey => {
          const item = r.grades[compKey]
          if (item && item.score !== undefined && item.score !== null) {
            payloadGrades.push({
              student_id: r.student_id,
              component_id: item.component_id || Number(compKey),
              score: Number(item.score)
            })
          }
        })
      }
    })

    await $fetch(`${apiBase}/courses/${props.courseId}/gradebook/batch-save`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
      body: { grades: payloadGrades }
    })
    showToast('Nilai buku nilai berhasil diperbarui!')
    await fetchGradebook()
  } catch (e: any) {
    alert(e?.data?.message || 'Gagal menyimpan nilai buku nilai.')
  } finally {
    gradebookSaving.value = false
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

// -------------------------------------------------------------
// SlideOver Openers
// -------------------------------------------------------------
const openNewMeetDrawer = (chapterId?: number | string) => {
  slideOverMode.value = 'newMeet'
  newMeetForm.meeting_number = (meetsList.value.length || 0) + 1
  newMeetForm.chapter_id = chapterId || chaptersList.value[0]?.id || ''
  newMeetForm.title = ''
  newMeetForm.description = ''
  newMeetForm.start_time = ''
  newMeetForm.end_time = ''
  newMeetForm.type = 'offline'
  newMeetForm.meeting_link = ''
  slideOverOpen.value = true
}

const openNewMaterialDrawer = (meetId?: number | string) => {
  slideOverMode.value = 'newMaterial'
  newMaterialForm.meet_id = meetId ? String(meetId) : (activeMeetId.value ? String(activeMeetId.value) : (meetsList.value[0]?.id ? String(meetsList.value[0].id) : ''))
  newMaterialForm.title = ''
  newMaterialForm.type = 'file'
  newMaterialForm.content = ''
  newMaterialForm.external_url = ''
  newMaterialForm.file = null
  slideOverOpen.value = true
}

const openNewQuizDrawer = (meetId?: number | string, category: 'pretest' | 'quiz' | 'assignment' | 'posttest' = 'quiz') => {
  slideOverMode.value = 'newQuiz'
  newQuizForm.meet_id = meetId ? String(meetId) : (activeMeetId.value ? String(activeMeetId.value) : (meetsList.value[0]?.id ? String(meetsList.value[0].id) : ''))
  newQuizForm.activity_category = category
  newQuizForm.manual_questions = []

  // Preset jendela waktu akses default: buka saat ini s/d 2 hari ke depan 23:59 WIB
  applyTimePreset(newQuizForm, '2days')

  if (category === 'pretest') {
    newQuizForm.title = 'Pretest: Asesmen Diagnostik Awal'
    newQuizForm.instructions = 'Kerjakan asesmen diagnostik untuk mengukur pemahaman awal sebelum memulai topik.'
    newQuizForm.duration_minutes = 15
    newQuizForm.quiz_type = 'multiple_choice'
    newQuizForm.question_source = 'manual'
  } else if (category === 'posttest') {
    newQuizForm.title = 'Posttest: Asesmen Sumatif Capaian'
    newQuizForm.instructions = 'Kerjakan asesmen sumatif ini sebagai evaluasi ketercapaian sub-bab.'
    newQuizForm.duration_minutes = 30
    newQuizForm.quiz_type = 'multiple_choice'
    newQuizForm.question_source = 'manual'
  } else if (category === 'assignment') {
    newQuizForm.title = 'Tugas Mandiri (Pengumpulan File)'
    newQuizForm.instructions = 'Pelajari instruksi tugas berikut dengan seksama dan kumpulkan file tugas Anda (PDF, DOC, atau DOCX) sebelum tenggat waktu berakhir.'
    newQuizForm.duration_minutes = 120
    newQuizForm.quiz_type = 'assignment'
    newQuizForm.question_source = 'later'
  } else {
    newQuizForm.title = 'Quiz Formatif Pemahaman'
    newQuizForm.instructions = 'Latihan kuis formatif untuk menguji pemahaman konsep materi.'
    newQuizForm.duration_minutes = 20
    newQuizForm.quiz_type = 'multiple_choice'
    newQuizForm.question_source = 'manual'
  }

  // Prepopulate 1 draft question if manual multiple choice
  if (newQuizForm.quiz_type !== 'assignment') {
    addDraftQuestion()
  }

  newQuizForm.max_score = 100
  newQuizForm.question_bank_id = questionBanks.value[0]?.id || ''
  slideOverOpen.value = true
}

const openNewDiscussionDrawer = (meetId: number | string) => {
  slideOverMode.value = 'newDiscussion'
  newDiscussionForm.meet_id = String(meetId)
  newDiscussionForm.message = ''
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
      showToast('Sub-bab / Pertemuan berhasil ditambahkan!')
      slideOverOpen.value = false
      await fetchCourseData()
    } else if (slideOverMode.value === 'newChapter') {
      await $fetch(`${apiBase}/courses/${props.courseId}/chapters`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
        body: newChapterForm
      })
      showToast('Bab baru berhasil dibuat!')
      slideOverOpen.value = false
      await fetchChapters()
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
      showToast('Modul bahan ajar berhasil ditambahkan!')
      slideOverOpen.value = false
      await fetchCourseData()
    } else if (slideOverMode.value === 'newQuiz') {
      const payload: any = {
        meet_id: newQuizForm.meet_id,
        title: newQuizForm.title,
        instructions: newQuizForm.instructions,
        quiz_type: newQuizForm.quiz_type,
        activity_category: newQuizForm.activity_category,
        duration_minutes: newQuizForm.duration_minutes,
        max_score: newQuizForm.max_score,
        available_from: newQuizForm.available_from || null,
        available_until: newQuizForm.available_until || null,
        due_date: newQuizForm.available_until || newQuizForm.due_date || null,
      }

      if (newQuizForm.question_source === 'manual' && newQuizForm.manual_questions.length > 0) {
        // Filter valid questions
        const validQuestions = newQuizForm.manual_questions.filter(q => q.question.trim().length > 0)
        if (validQuestions.length > 0) {
          payload.questions_data = validQuestions
        }
      } else if (newQuizForm.question_source === 'bank' && newQuizForm.question_bank_id) {
        payload.question_bank_id = newQuizForm.question_bank_id
      }

      const quizRes: any = await $fetch(`${apiBase}/quizzes`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
        body: payload
      })

      const quizId = quizRes?.data?.id || quizRes?.id
      if (quizId && newQuizForm.question_source === 'bank' && newQuizForm.question_bank_id) {
        // Fallback import check if needed
        if (!quizRes?.data?.questions_data || quizRes?.data?.questions_data.length === 0) {
          await $fetch(`${apiBase}/quizzes/${quizId}/import-bank-items`, {
            method: 'POST',
            headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
            body: { question_bank_id: newQuizForm.question_bank_id }
          }).catch(console.error)
        }
      }
      showToast(`Aktivitas (${newQuizForm.activity_category.toUpperCase()}) berhasil dibuat!`)
      slideOverOpen.value = false
      await fetchCourseData()
    } else if (slideOverMode.value === 'newDiscussion') {
      await $fetch(`${apiBase}/discussions`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
        body: {
          meet_id: newDiscussionForm.meet_id,
          message: newDiscussionForm.message
        }
      })
      showToast('Topik forum diskusi berhasil dibuka!')
      slideOverOpen.value = false
      await fetchCourseData()
    } else if (slideOverMode === 'newJournal') {
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
      showToast('Jurnal mengajar berhasil dicatat!')
      slideOverOpen.value = false
      await fetchJournals()
    }
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal menyimpan data.')
  } finally {
    savingSlideOver.value = false
  }
}

watch(currentTab, (tab) => {
  if (tab === 'gradebook' && !gradebookData.value) {
    fetchGradebook()
  } else if (tab === 'journal' && journalsList.value.length === 0) {
    fetchJournals()
  }
})

watch(() => props.courseId, () => {
  fetchCourseData()
  fetchQuestionBanks()
  fetchChapters()
}, { immediate: true })
</script>

<template>
  <div class="bg-white border border-slate-200/90 rounded-3xl shadow-xs overflow-hidden flex flex-col">
    <!-- 1. STICKY COCKPIT CONTEXT HEADER (ZERO EMOJI) -->
    <div class="px-6 py-5 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white flex flex-col md:flex-row md:items-center justify-between gap-4">
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
          <span class="text-slate-400 font-normal">/</span>
          <span class="text-emerald-400">{{ course?.class?.class_name?.name || course?.class?.className?.name || 'Kelas' }}</span>
        </h2>
        <div class="text-xs text-slate-300 mt-1.5 flex flex-wrap items-center gap-4">
          <span class="flex items-center gap-1.5 font-medium">
            <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            {{ classStudents.length || 0 }} Siswa Terdaftar
          </span>
          <span class="text-slate-500">&bull;</span>
          <span class="flex items-center gap-1.5 font-medium">
            <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            {{ meetsList.length }} Sub-bab / Sesi
          </span>
          <span class="text-slate-500">&bull;</span>
          <span class="flex items-center gap-1.5 font-medium">
            <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            {{ course?.teacher?.name || 'Guru Pengampu' }}
          </span>
        </div>
      </div>

      <div class="flex items-center gap-2 self-start md:self-center">
        <NuxtLink
          :to="`/teacher/attendance?course_id=${props.courseId}`"
          class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm"
          title="Buka Layar Presensi & Proyektor QR Kelas Ini"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
          </svg>
          <span class="hidden sm:inline">Presensi</span>
        </NuxtLink>
        <NuxtLink
          to="/teacher/board"
          class="px-3.5 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition flex items-center gap-1.5 border border-white/10"
          title="Buka Papan Tulis Digital EduBoard"
        >
          <svg class="w-4 h-4 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <span class="hidden sm:inline">EduBoard</span>
        </NuxtLink>
        <button
          type="button"
          class="px-3.5 py-2 rounded-xl bg-white/10 hover:bg-rose-600/80 text-white text-xs font-bold transition flex items-center gap-1.5 border border-white/10"
          @click="emit('close')"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <span>Ganti Kelas</span>
        </button>
      </div>
    </div>

    <!-- 2. QUICK TABS BAR (ZERO EMOJI) -->
    <div class="p-3 bg-slate-50 border-b border-slate-200/80 flex items-center justify-between gap-3 overflow-x-auto">
      <QuickTabs
        v-model="currentTab"
        :items="cockpitTabs"
      >
        <template #icon="{ item }">
          <svg v-if="item.id === 'timeline'" class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
          <svg v-else-if="item.id === 'attendance'" class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
          </svg>
          <svg v-else-if="item.id === 'cbt'" class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
          </svg>
          <svg v-else-if="item.id === 'gradebook'" class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          <svg v-else-if="item.id === 'journal'" class="w-4 h-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </template>
      </QuickTabs>

      <!-- Quick Action Buttons -->
      <div class="flex items-center gap-2 shrink-0">
        <button
          v-if="currentTab === 'timeline'"
          type="button"
          class="px-3 py-1.5 rounded-xl border border-indigo-200 bg-indigo-50/70 hover:bg-indigo-100 text-indigo-800 text-xs font-bold shadow-2xs transition flex items-center gap-1.5"
          @click="openNewChapterDrawer"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
          <span>Tambah Bab</span>
        </button>
        <button
          v-if="currentTab === 'timeline'"
          type="button"
          class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-2xs transition flex items-center gap-1.5"
          @click="openNewMeetDrawer()"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          <span>Tambah Sub-bab</span>
        </button>
        <button
          v-if="currentTab === 'cbt'"
          type="button"
          class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-2xs transition flex items-center gap-1.5"
          @click="openNewQuizDrawer()"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          <span>Buat Ujian CBT</span>
        </button>
        <button
          v-if="currentTab === 'journal'"
          type="button"
          class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-2xs transition flex items-center gap-1.5"
          @click="openNewJournalDrawer"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
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
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span>{{ toastMsg }}</span>
      </div>
      <button type="button" class="text-white/80 hover:text-white" @click="toastMsg = ''">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- 3. INTERACTIVE CONTENT WORKSPACE -->
    <div class="p-6">
      <div v-if="loading" class="p-12 text-center text-sm text-slate-400">
        Memuat instrumen ruang kelas...
      </div>

      <!-- ============================================================== -->
      <!-- TAB 1: SILABUS & ALUR BELAJAR (BAB -> SUB-BAB -> 6 TAHAP)        -->
      <!-- ============================================================== -->
      <div v-else-if="currentTab === 'timeline'" class="space-y-4">
        <!-- Search & Filter Bar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 p-3 bg-slate-50/70 rounded-2xl border border-slate-200/80">
          <div class="relative flex-1">
            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </span>
            <input
              v-model="timelineSearch"
              type="text"
              placeholder="Cari judul bab, sub-bab materi, atau kuis..."
              class="w-full pl-9 pr-3 py-2 text-xs rounded-xl bg-white border border-slate-200 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
            />
          </div>
          <div class="flex items-center gap-2 text-xs font-bold text-slate-500 px-1">
            <span>{{ chaptersList.length }} Bab</span>
            <span>&bull;</span>
            <span>{{ filteredMeets.length }} Sub-bab</span>
          </div>
        </div>

        <div v-if="filteredMeets.length === 0" class="p-12 border-2 border-dashed border-slate-200 rounded-3xl text-center">
          <p class="text-sm font-bold text-slate-700">
            {{ timelineSearch ? 'Tidak ada sub-bab atau materi yang cocok dengan pencarian.' : 'Belum ada silabus / sub-bab di kelas ini.' }}
          </p>
          <p class="text-xs text-slate-400 mt-1">
            {{ timelineSearch ? 'Coba kata kunci pencarian yang lain.' : 'Buat Bab & Sub-bab pertama untuk menyusun alur belajar lengkap siswa.' }}
          </p>
          <div v-if="!timelineSearch" class="mt-4 flex items-center justify-center gap-2.5">
            <button
              type="button"
              class="px-4 py-2 rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-800 text-xs font-bold hover:bg-indigo-100 transition shadow-2xs"
              @click="openNewChapterDrawer"
            >
              + Buat Bab 1
            </button>
            <button
              type="button"
              class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-2xs"
              @click="openNewMeetDrawer()"
            >
              + Buat Sub-bab Baru
            </button>
          </div>
        </div>

        <!-- Groups by Chapter Accordion -->
        <div v-else class="space-y-6">
          <div
            v-for="group in meetsByChapter"
            :key="group.chapter?.id || 'unassigned'"
            class="space-y-3"
          >
            <!-- Chapter Header Accordion -->
            <div
              v-if="group.chapter"
              class="flex items-center justify-between p-3.5 bg-gradient-to-r from-slate-100 via-indigo-50/30 to-slate-50 border border-indigo-100/80 rounded-2xl cursor-pointer hover:border-indigo-300 transition"
              @click="toggleChapterCollapse(group.chapter.id)"
            >
              <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-600 text-white text-xs font-black shadow-2xs">
                  {{ group.chapter.order }}
                </span>
                <div>
                  <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                    <span>{{ group.chapter.title }}</span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 border border-indigo-200/60">
                      {{ group.meets.length }} Sub-bab
                    </span>
                  </h3>
                  <p v-if="group.chapter.description" class="text-[11px] text-slate-500 mt-0.5">
                    {{ group.chapter.description }}
                  </p>
                </div>
              </div>

              <div class="flex items-center gap-2" @click.stop>
                <button
                  type="button"
                  class="px-2.5 py-1 text-xs font-bold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-xl transition flex items-center gap-1"
                  @click="openNewMeetDrawer(group.chapter.id)"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  <span>+ Sub-bab</span>
                </button>
                <button
                  type="button"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
                  title="Hapus Bab ini"
                  @click="deleteChapter(group.chapter)"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
                <button
                  type="button"
                  class="px-2.5 py-1 rounded-xl text-slate-500 bg-white border border-slate-200 hover:bg-slate-50 transition text-xs font-bold shadow-2xs"
                  @click="toggleChapterCollapse(group.chapter.id)"
                >
                  <span>{{ collapsedChapters[group.chapter.id] ? 'Buka Bab' : 'Tutup Bab' }}</span>
                </button>
              </div>
            </div>

            <!-- Unassigned Meets Header -->
            <div
              v-else-if="chaptersList.length > 0"
              class="p-3 bg-slate-100/80 border border-slate-200 rounded-2xl flex items-center justify-between text-xs font-bold text-slate-700"
            >
              <span class="flex items-center gap-2">
                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                </svg>
                <span>Sub-bab Umum / Belum Dialokasikan ke Bab ({{ group.meets.length }})</span>
              </span>
            </div>

            <!-- Sub-bab / Meets List Cards -->
            <div
              v-if="!group.chapter || !collapsedChapters[group.chapter.id]"
              class="space-y-4"
              :class="group.chapter ? 'pl-0 sm:pl-3' : ''"
            >
              <div
                v-if="group.meets.length === 0"
                class="p-5 text-center text-xs text-slate-500 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200"
              >
                <span>Belum ada sub-bab di bab ini.</span>
                <button
                  type="button"
                  class="ml-2 font-bold text-indigo-700 hover:underline"
                  @click="openNewMeetDrawer(group.chapter?.id)"
                >
                  + Tambah Sub-bab Sekarang
                </button>
              </div>

              <!-- SUB-BAB CARD (WITH COMPLETE 6-STEP SEQUENTIAL LEARNING PATH) -->
              <div
                v-for="meet in group.meets"
                :key="meet.id"
                class="border rounded-2xl p-5 transition bg-white shadow-xs border-slate-200 hover:border-slate-300"
              >
                <!-- 1. SUB-BAB HEADER -->
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                  <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 text-slate-800 text-xs font-black flex items-center justify-center shrink-0">
                      {{ meet.meeting_number }}
                    </span>
                    <div>
                      <div class="flex items-center gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Sub-bab {{ meet.meeting_number }}</span>
                        <span class="text-slate-300">&bull;</span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="meet.type === 'online' ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-slate-100 text-slate-700 border border-slate-200'">
                          {{ meet.type === 'online' ? 'Daring (Online)' : 'Tatap Muka' }}
                        </span>
                      </div>
                      <h4 class="text-base font-black text-slate-900 mt-0.5">{{ meet.title }}</h4>
                      <p v-if="meet.description" class="text-xs text-slate-500 mt-1 line-clamp-2">
                        {{ meet.description }}
                      </p>
                    </div>
                  </div>

                  <!-- Sub-bab Right Actions -->
                  <div class="flex items-center gap-2 self-end sm:self-center">
                    <!-- + Tambah Aktivitas Dropdown Button -->
                    <div class="relative">
                      <button
                        type="button"
                        class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-2xs"
                        @click="toggleAddMenu(meet.id)"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Tambah Aktivitas</span>
                        <svg class="w-3 h-3 ml-0.5 transition" :class="activeAddMenuMeetId === meet.id ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>

                      <!-- Dropdown Menu -->
                      <div
                        v-if="activeAddMenuMeetId === meet.id"
                        class="absolute right-0 mt-1.5 w-56 rounded-2xl bg-white border border-slate-200 shadow-xl py-1.5 z-30"
                      >
                        <button
                          type="button"
                          class="w-full px-3.5 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-emerald-50 hover:text-emerald-900 flex items-center gap-2.5 transition"
                          @click="openNewMaterialDrawer(meet.id); activeAddMenuMeetId = null"
                        >
                          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                          <span>Modul / Bahan Ajar</span>
                        </button>
                        <button
                          type="button"
                          class="w-full px-3.5 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-amber-50 hover:text-amber-900 flex items-center gap-2.5 transition"
                          @click="openNewQuizDrawer(meet.id, 'assignment'); activeAddMenuMeetId = null"
                        >
                          <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                          <span>Tugas Mandiri (Kumpul File)</span>
                        </button>
                        <button
                          type="button"
                          class="w-full px-3.5 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-900 flex items-center gap-2.5 transition"
                          @click="openNewQuizDrawer(meet.id, 'quiz'); activeAddMenuMeetId = null"
                        >
                          <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                          <span>Quiz Formatif</span>
                        </button>
                        <button
                          type="button"
                          class="w-full px-3.5 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-sky-50 hover:text-sky-900 flex items-center gap-2.5 transition"
                          @click="openNewDiscussionDrawer(meet.id); activeAddMenuMeetId = null"
                        >
                          <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                          <span>Forum Diskusi</span>
                        </button>
                        <div class="my-1 border-t border-slate-100"></div>
                        <button
                          type="button"
                          class="w-full px-3.5 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-rose-50 hover:text-rose-900 flex items-center gap-2.5 transition"
                          @click="openNewQuizDrawer(meet.id, 'pretest'); activeAddMenuMeetId = null"
                        >
                          <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                          <span>Pretest (Diagnostik)</span>
                        </button>
                        <button
                          type="button"
                          class="w-full px-3.5 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-purple-50 hover:text-purple-900 flex items-center gap-2.5 transition"
                          @click="openNewQuizDrawer(meet.id, 'posttest'); activeAddMenuMeetId = null"
                        >
                          <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                          <span>Posttest (Sumatif)</span>
                        </button>
                      </div>
                    </div>

                    <!-- Quick Meet Options (Duplicate & Delete) -->
                    <button
                      type="button"
                      class="p-1.5 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100 transition"
                      title="Duplikat sub-bab ini"
                      @click="duplicateMeet(meet)"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                      </svg>
                    </button>
                    <button
                      type="button"
                      class="p-1.5 rounded-xl border border-slate-200 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
                      title="Hapus sub-bab ini"
                      @click="deleteMeet(meet)"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>



                <!-- 3. UNIFIED ACTIVITIES STREAM (MINIMALIST & CLEAN) -->
                <!-- Empty State (Minimalist) -->
                <div
                  v-if="getMeetActivities(meet).length === 0"
                  class="mt-3.5 py-3.5 px-4 bg-slate-50/70 border border-dashed border-slate-200 rounded-xl flex items-center justify-between text-xs text-slate-500"
                >
                  <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Belum ada aktivitas di sub-bab ini.
                  </span>
                  <button
                    type="button"
                    class="font-bold text-indigo-600 hover:text-indigo-800 hover:underline"
                    @click="toggleAddMenu(meet.id)"
                  >
                    + Tambah Aktivitas Pertama
                  </button>
                </div>

                <!-- Unified Activities List -->
                <div v-else class="mt-3.5 space-y-2">
                  <div
                    v-for="(act, actIdx) in getMeetActivities(meet)"
                    :key="act.id + '-' + act.itemType"
                    class="p-2.5 sm:p-3 bg-white rounded-xl border border-slate-200/80 hover:border-slate-300 flex items-center justify-between gap-3 text-xs transition shadow-2xs group"
                  >
                    <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                      <span class="w-5 h-5 rounded-md bg-slate-100 text-slate-600 text-[10px] font-bold flex items-center justify-center shrink-0">
                        {{ actIdx + 1 }}
                      </span>
                      <span
                        class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider shrink-0"
                        :class="{
                          'bg-emerald-50 text-emerald-700 border border-emerald-200': act.itemType === 'material',
                          'bg-amber-50 text-amber-700 border border-amber-200': act.itemType === 'assignment',
                          'bg-indigo-50 text-indigo-700 border border-indigo-200': act.itemType === 'quiz',
                          'bg-sky-50 text-sky-700 border border-sky-200': act.itemType === 'discussion',
                          'bg-rose-50 text-rose-700 border border-rose-200': act.itemType === 'pretest',
                          'bg-purple-50 text-purple-700 border border-purple-200': act.itemType === 'posttest',
                        }"
                      >
                        {{ act.label }}
                      </span>
                      <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                          <p class="font-bold text-slate-900 truncate">{{ act.title }}</p>
                          <span
                            v-if="act.timeBadge"
                            class="px-1.5 py-0.2 rounded text-[9px] font-bold border shrink-0"
                            :class="act.timeBadge.class"
                          >
                            {{ act.timeBadge.label }}
                          </span>
                        </div>
                        <p class="text-[11px] text-slate-500 truncate">{{ act.info }}</p>
                      </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <!-- Material actions -->
                      <template v-if="act.itemType === 'material'">
                        <a
                          v-if="act.raw.file_path"
                          :href="`${storageBase}/${act.raw.file_path}`"
                          target="_blank"
                          class="px-2.5 py-1 text-[11px] font-bold text-emerald-700 hover:bg-emerald-50 rounded-lg transition"
                        >
                          Buka File &nearr;
                        </a>
                        <a
                          v-else-if="act.raw.external_url"
                          :href="act.raw.external_url"
                          target="_blank"
                          class="px-2.5 py-1 text-[11px] font-bold text-blue-700 hover:bg-blue-50 rounded-lg transition"
                        >
                          Buka Link &nearr;
                        </a>
                        <button
                          type="button"
                          class="p-1 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition"
                          title="Hapus"
                          @click="deleteMaterial(act.raw)"
                        >
                          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                      </template>

                      <!-- Quiz / Pretest / Posttest actions -->
                      <template v-else-if="act.itemType === 'quiz' || act.itemType === 'pretest' || act.itemType === 'posttest'">
                        <button
                          type="button"
                          class="px-2.5 py-1 text-[11px] font-bold text-indigo-700 bg-indigo-50/70 hover:bg-indigo-100 rounded-lg transition flex items-center gap-1"
                          @click="openQuestionManager(act.raw)"
                        >
                          <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                          </svg>
                          <span>Kelola Soal</span>
                        </button>
                        <NuxtLink
                          :to="`/teacher/quizzes/${act.id}/grading`"
                          class="px-2.5 py-1 text-[11px] font-bold text-slate-700 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition"
                        >
                          Hasil & Nilai &nearr;
                        </NuxtLink>
                        <button
                          type="button"
                          class="p-1 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition"
                          title="Hapus"
                          @click="deleteQuiz(act.raw)"
                        >
                          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                      </template>

                      <!-- Assignment actions -->
                      <template v-else-if="act.itemType === 'assignment'">
                        <NuxtLink
                          :to="`/teacher/quizzes/${act.id}/grading`"
                          class="px-2.5 py-1 text-[11px] font-bold text-amber-700 hover:bg-amber-50 rounded-lg transition"
                        >
                          Periksa Tugas &nearr;
                        </NuxtLink>
                        <button
                          type="button"
                          class="p-1 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition"
                          title="Hapus"
                          @click="deleteQuiz(act.raw)"
                        >
                          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                      </template>

                      <!-- Discussion actions -->
                      <template v-else-if="act.itemType === 'discussion'">
                        <button
                          type="button"
                          class="px-2.5 py-1 text-[11px] font-bold text-sky-700 hover:bg-sky-50 rounded-lg transition"
                          @click="openNewDiscussionDrawer(meet.id)"
                        >
                          Buka Topik
                        </button>
                      </template>
                    </div>
                  </div>
                </div>

                <!-- Footer Card: Alokasi Bab Dropdown -->
                <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
                  <div class="flex items-center gap-2">
                    <span class="text-[11px] font-bold text-slate-500">Alokasi Bab:</span>
                    <select
                      :value="meet.chapter_id || ''"
                      class="text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1 text-slate-700 hover:bg-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500 transition"
                      @change="(e: any) => assignMeetToChapter(meet.id, e.target.value)"
                    >
                      <option value="">-- Tanpa Bab (Umum) --</option>
                      <option v-for="c in chaptersList" :key="c.id" :value="c.id">
                        Bab {{ c.order }}: {{ c.title }}
                      </option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ============================================================== -->
      <!-- TAB 2: PRESENSI TATAP MUKA                                     -->
      <!-- ============================================================== -->
      <div v-else-if="currentTab === 'attendance'" class="space-y-4">
        <!-- Highlight Banner: Dedicated QR & Batch Attendance -->
        <div class="p-4 bg-gradient-to-r from-emerald-900 via-teal-900 to-slate-900 text-white rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm border border-emerald-800">
          <div class="flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
              </svg>
            </div>
            <div>
              <h4 class="text-sm font-black text-white">Layar Presensi KBM & Proyektor QR 15-Menit</h4>
              <p class="text-xs text-emerald-100 mt-0.5">Buka proyektor QR dinamis yang berganti tiap 15 menit dan aksi cepat 1-klik Hadirkan Semua Siswa.</p>
            </div>
          </div>
          <NuxtLink
            :to="`/teacher/attendance?course_id=${props.courseId}`"
            class="px-4 py-2.5 rounded-xl bg-white hover:bg-emerald-50 text-emerald-900 text-xs font-black transition shrink-0 flex items-center justify-center gap-1.5 shadow-sm"
          >
            <span>Buka Proyektor QR</span>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
          </NuxtLink>
        </div>

        <!-- Choose Meeting Selector -->
        <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-2xl border border-slate-200/70">
          <label class="text-xs font-bold text-slate-700 shrink-0">Presensi Manual Sub-bab:</label>
          <select
            v-model="activeMeetId"
            class="px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
          >
            <option v-for="m in meetsList" :key="m.id" :value="m.id">
              Sub-bab {{ m.meeting_number }}: {{ m.title }}
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
          Pilih sub-bab pertemuan terlebih dahulu.
        </div>
      </div>

      <!-- ============================================================== -->
      <!-- TAB 3: UJIAN & CBT                                             -->
      <!-- ============================================================== -->
      <div v-else-if="currentTab === 'cbt'" class="space-y-4">
        <div v-if="allCourseQuizzes.length === 0" class="p-12 border-2 border-dashed border-slate-200 rounded-3xl text-center">
          <p class="text-sm font-bold text-slate-700">Belum ada ujian CBT pada kelas ini.</p>
          <p class="text-xs text-slate-400 mt-1">Buat asesmen kuis baru atau impor butir soal dari Bank Soal secara instan.</p>
          <button
            type="button"
            class="mt-4 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-2xs"
            @click="openNewQuizDrawer()"
          >
            + Buat Ujian CBT Baru
          </button>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="quiz in allCourseQuizzes"
            :key="quiz.id"
            class="border border-slate-200 rounded-2xl bg-white p-5 shadow-2xs flex flex-col justify-between hover:border-indigo-300 transition"
          >
            <div>
              <div class="flex items-center justify-between gap-2">
                <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase" :class="quiz.activity_category === 'pretest' ? 'bg-rose-50 text-rose-800 border border-rose-200' : quiz.activity_category === 'posttest' ? 'bg-purple-50 text-purple-800 border border-purple-200' : quiz.activity_category === 'assignment' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-indigo-50 text-indigo-800 border border-indigo-200'">
                  {{ quiz.activity_category || quiz.quiz_type }}
                </span>
                <span class="text-[11px] font-semibold text-slate-400">
                  {{ quiz.duration_minutes || 60 }} Menit
                </span>
              </div>
              <h4 class="mt-2 text-sm font-black text-slate-900">{{ quiz.title }}</h4>
              <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ quiz.instructions || 'Tidak ada instruksi khusus.' }}</p>

              <!-- Time Window & Question status badge -->
              <div class="mt-3 flex items-center gap-2 flex-wrap">
                <span
                  class="px-2 py-0.5 rounded-md text-[10px] font-bold border shrink-0"
                  :class="getQuizTimeBadge(quiz).class"
                >
                  {{ getQuizTimeBadge(quiz).label }}
                </span>
                <span
                  class="px-2 py-0.5 rounded-md text-[10px] font-bold"
                  :class="quiz.questions_data && quiz.questions_data.length > 0 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200'"
                >
                  {{ quiz.questions_data && quiz.questions_data.length > 0 ? `${quiz.questions_data.length} Butir Soal CBT` : 'Belum Ada Soal' }}
                </span>
                <span class="text-slate-400 font-mono text-[10px]">Skor: {{ quiz.max_score }}</span>
              </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between gap-2 text-xs">
              <button
                type="button"
                class="px-2.5 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-800 text-[11px] font-bold transition flex items-center gap-1"
                @click="openQuestionManager(quiz)"
              >
                <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                <span>Kelola Soal</span>
              </button>

              <NuxtLink :to="`/teacher/quizzes/${quiz.id}/grading`" class="px-2.5 py-1.5 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold text-[11px] transition">
                {{ quiz.submissions_count || 0 }} Siswa Kumpul &nearr;
              </NuxtLink>
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
                Standar Kriteria Ketercapaian Tujuan Pembelajaran (KKTP): <span class="text-emerald-700 font-black">&ge; {{ gradebookData.kktp || 75 }}</span>
              </p>
              <p class="text-[11px] text-slate-400 mt-0.5">Komponen Nilai: Formatif TP, Sumatif Tengah Semester (STS), Sumatif Akhir Semester (SAS).</p>
            </div>
            <button
              type="button"
              :disabled="gradebookSaving"
              class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-2xs transition flex items-center gap-1.5 disabled:opacity-50"
              @click="saveGradebookScores"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
              </svg>
              <span>{{ gradebookSaving ? 'Menyimpan...' : 'Simpan Nilai' }}</span>
            </button>
          </div>

          <!-- Matriks Nilai Table -->
          <div class="border border-slate-200 rounded-2xl bg-white overflow-x-auto shadow-2xs">
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
            class="mt-4 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-2xs"
            @click="openNewJournalDrawer"
          >
            + Tulis Jurnal Mengajar
          </button>
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="jrn in journalsList"
            :key="jrn.id"
            class="border border-slate-200 rounded-2xl bg-white p-5 shadow-2xs"
          >
            <div class="flex items-center justify-between gap-3 pb-2 border-b border-slate-100">
              <span class="text-xs font-bold text-slate-500 font-mono">{{ jrn.date }}</span>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                :class="jrn.status === 'verified' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
              >
                {{ jrn.status === 'verified' ? 'Terverifikasi' : 'Menunggu Verifikasi' }}
              </span>
            </div>
            <h4 class="mt-2 text-sm font-black text-slate-900">{{ jrn.title }}</h4>
            <p class="mt-1 text-xs text-slate-600">{{ jrn.learning_activity }}</p>
            <p v-if="jrn.challenges" class="mt-2 text-[11px] text-amber-800 bg-amber-50 p-2 rounded-xl border border-amber-200">
              Kendala: {{ jrn.challenges }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- 4. SLIDE-OVER DRAWER FOR FORMS -->
    <SlideOver
      v-model="slideOverOpen"
      :title="
        slideOverMode === 'newMeet' ? 'Tambah Sub-bab Baru' :
        slideOverMode === 'newChapter' ? 'Buat Bab / Topik Baru' :
        slideOverMode === 'newMaterial' ? 'Unggah Modul / Video Pembelajaran' :
        slideOverMode === 'newDiscussion' ? 'Buka Topik Forum Diskusi' :
        slideOverMode === 'newQuiz' ? `Buat Aktivitas Belajar (${newQuizForm.activity_category.toUpperCase()})` : 'Catat Jurnal Mengajar'
      "
      :subtitle="`${course?.subject?.name || ''} - ${course?.class?.class_name?.name || course?.class?.className?.name || ''}`"
    >
      <!-- Form 1: New Meet (Sub-bab) -->
      <form v-if="slideOverMode === 'newMeet'" class="space-y-4" @submit.prevent="handleSlideOverSubmit">
        <div v-if="chaptersList.length > 0">
          <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Bab / Topik (Opsional)</label>
          <select v-model="newMeetForm.chapter_id" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            <option value="">-- Tanpa Bab (Umum) --</option>
            <option v-for="c in chaptersList" :key="c.id" :value="c.id">
              Bab {{ c.order }}: {{ c.title }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Sub-bab / Pertemuan Ke-</label>
          <input
            v-model.number="newMeetForm.meeting_number"
            type="number"
            required
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-bold"
          >
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Judul Sub-bab</label>
          <input
            v-model="newMeetForm.title"
            type="text"
            required
            placeholder="Contoh: Trigonometri Dasar & Sudut Istimewa"
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
          <label class="block text-xs font-bold text-slate-700 mb-1">Deskripsi / Capaian Pembelajaran</label>
          <textarea
            v-model="newMeetForm.description"
            rows="3"
            placeholder="Arahan pembelajaran untuk siswa..."
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          />
        </div>
        <div class="pt-3 border-t border-slate-100 flex justify-end">
          <button
            type="submit"
            :disabled="savingSlideOver"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition disabled:opacity-50"
          >
            {{ savingSlideOver ? 'Menyimpan...' : 'Simpan Sub-bab' }}
          </button>
        </div>
      </form>

      <!-- Form 2: New Chapter -->
      <form v-else-if="slideOverMode === 'newChapter'" class="space-y-4" @submit.prevent="handleSlideOverSubmit">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Urutan Bab (Ke-)</label>
          <input
            v-model.number="newChapterForm.order"
            type="number"
            min="1"
            required
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-bold"
          >
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Judul Bab</label>
          <input
            v-model="newChapterForm.title"
            type="text"
            required
            placeholder="Contoh: Bab 1 - Trigonometri"
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          >
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Deskripsi Bab</label>
          <textarea
            v-model="newChapterForm.description"
            rows="3"
            placeholder="Ringkasan topik atau capaian pembelajaran bab ini..."
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          />
        </div>
        <div class="pt-3 border-t border-slate-100 flex justify-end">
          <button
            type="submit"
            :disabled="savingSlideOver"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition disabled:opacity-50"
          >
            {{ savingSlideOver ? 'Menyimpan...' : 'Simpan Bab Baru' }}
          </button>
        </div>
      </form>

      <!-- Form 3: New Material -->
      <form v-else-if="slideOverMode === 'newMaterial'" class="space-y-4" @submit.prevent="handleSlideOverSubmit">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Sub-bab</label>
          <select v-model="newMaterialForm.meet_id" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            <option v-for="m in meetsList" :key="m.id" :value="String(m.id)">
              Sub-bab {{ m.meeting_number }}: {{ m.title }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Judul Materi / Modul</label>
          <input
            v-model="newMaterialForm.title"
            type="text"
            required
            placeholder="Contoh: Modul 1 - Rasio Trigonometri Segitiga Siku-Siku"
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          >
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Tipe Materi</label>
          <select v-model="newMaterialForm.type" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            <option value="file">Dokumen PDF / PPT / Word</option>
            <option value="video">Tautan Video YouTube Pembelajaran</option>
            <option value="text">Artikel / Teks Ringkasan Materi</option>
          </select>
        </div>
        <div v-if="newMaterialForm.type === 'file'">
          <label class="block text-xs font-bold text-slate-700 mb-1">Pilih File (PDF, PPT, DOCX, MP4)</label>
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
            placeholder="https://www.youtube.com/watch?v=..."
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          >
        </div>
        <div v-if="newMaterialForm.type === 'text'">
          <label class="block text-xs font-bold text-slate-700 mb-1">Konten Bacaan</label>
          <textarea
            v-model="newMaterialForm.content"
            rows="5"
            placeholder="Tulis ringkasan atau penjelasan materi di sini..."
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          />
        </div>
        <div class="pt-3 border-t border-slate-100 flex justify-end">
          <button
            type="submit"
            :disabled="savingSlideOver"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition disabled:opacity-50"
          >
            {{ savingSlideOver ? 'Mengunggah...' : 'Simpan Modul' }}
          </button>
        </div>
      </form>

      <!-- Form 4: New Discussion Topic -->
      <form v-else-if="slideOverMode === 'newDiscussion'" class="space-y-4" @submit.prevent="handleSlideOverSubmit">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Sub-bab</label>
          <select v-model="newDiscussionForm.meet_id" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            <option v-for="m in meetsList" :key="m.id" :value="String(m.id)">
              Sub-bab {{ m.meeting_number }}: {{ m.title }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Pertanyaan Pemantik / Topik Diskusi</label>
          <textarea
            v-model="newDiscussionForm.message"
            rows="4"
            required
            placeholder="Contoh: Mengapa sudut 30°, 45°, dan 60° disebut sebagai sudut istimewa? Silakan tuliskan pendapat kalian..."
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          />
        </div>
        <div class="pt-3 border-t border-slate-100 flex justify-end">
          <button
            type="submit"
            :disabled="savingSlideOver"
            class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold transition disabled:opacity-50"
          >
            {{ savingSlideOver ? 'Mengirim...' : 'Buka Topik Diskusi' }}
          </button>
        </div>
      </form>

      <!-- Form 5: New Quiz / CBT (With Activity Category Selector) -->
      <form v-else-if="slideOverMode === 'newQuiz'" class="space-y-4" @submit.prevent="handleSlideOverSubmit">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Sub-bab</label>
          <select v-model="newQuizForm.meet_id" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs">
            <option v-for="m in meetsList" :key="m.id" :value="String(m.id)">
              Sub-bab {{ m.meeting_number }}: {{ m.title }}
            </option>
          </select>
        </div>

        <!-- Activity Category Selector -->
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5">Kategori Alur Pembelajaran</label>
          <div class="grid grid-cols-2 gap-2">
            <button
              type="button"
              class="p-2.5 rounded-xl border text-xs font-bold text-left transition"
              :class="newQuizForm.activity_category === 'pretest' ? 'bg-rose-50 border-rose-400 text-rose-900 ring-1 ring-rose-400' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-white'"
              @click="newQuizForm.activity_category = 'pretest'; newQuizForm.quiz_type = 'multiple_choice'; newQuizForm.duration_minutes = 15"
            >
              <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                <span class="font-black">Pretest</span>
              </div>
              <p class="text-[10px] text-slate-500 mt-0.5 font-normal">Diagnostik kemampuan awal</p>
            </button>
            <button
              type="button"
              class="p-2.5 rounded-xl border text-xs font-bold text-left transition"
              :class="newQuizForm.activity_category === 'quiz' ? 'bg-indigo-50 border-indigo-400 text-indigo-900 ring-1 ring-indigo-400' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-white'"
              @click="newQuizForm.activity_category = 'quiz'; newQuizForm.quiz_type = 'multiple_choice'; newQuizForm.duration_minutes = 20"
            >
              <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                <span class="font-black">Quiz Formatif</span>
              </div>
              <p class="text-[10px] text-slate-500 mt-0.5 font-normal">Latihan pemahaman topik</p>
            </button>
            <button
              type="button"
              class="p-2.5 rounded-xl border text-xs font-bold text-left transition"
              :class="newQuizForm.activity_category === 'assignment' ? 'bg-amber-50 border-amber-400 text-amber-900 ring-1 ring-amber-400' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-white'"
              @click="newQuizForm.activity_category = 'assignment'; newQuizForm.quiz_type = 'assignment'; newQuizForm.duration_minutes = 120"
            >
              <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span class="font-black">Tugas Mandiri</span>
              </div>
              <p class="text-[10px] text-slate-500 mt-0.5 font-normal">Pengumpulan file tugas siswa</p>
            </button>
            <button
              type="button"
              class="p-2.5 rounded-xl border text-xs font-bold text-left transition"
              :class="newQuizForm.activity_category === 'posttest' ? 'bg-purple-50 border-purple-400 text-purple-900 ring-1 ring-purple-400' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-white'"
              @click="newQuizForm.activity_category = 'posttest'; newQuizForm.quiz_type = 'multiple_choice'; newQuizForm.duration_minutes = 30"
            >
              <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                <span class="font-black">Posttest</span>
              </div>
              <p class="text-[10px] text-slate-500 mt-0.5 font-normal">Asesmen sumatif sub-bab</p>
            </button>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Judul Aktivitas</label>
          <input
            v-model="newQuizForm.title"
            type="text"
            required
            placeholder="Contoh: Kuis Formatif 1 - Nilai Perbandingan Sudut"
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-medium"
          >
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Petunjuk Pengerjaan</label>
          <textarea
            v-model="newQuizForm.instructions"
            rows="2"
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Durasi Pengerjaan (Menit)</label>
            <input
              v-model.number="newQuizForm.duration_minutes"
              type="number"
              min="5"
              max="240"
              required
              class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono font-bold"
            >
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Skor Maksimum</label>
            <input
              v-model.number="newQuizForm.max_score"
              type="number"
              required
              class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono font-bold"
            >
          </div>
        </div>

        <!-- PENGATURAN JENDELA WAKTU AKSES (TIME WINDOW) -->
        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
          <div class="flex items-center justify-between">
            <label class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
              <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Jendela Waktu Akses Belajar (Time Window)
            </label>
            <span class="text-[10px] text-slate-400 font-medium">Batas buka & tutup</span>
          </div>

          <!-- Quick Presets -->
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-[10px] text-slate-500 font-bold mr-1">Preset Cepat:</span>
            <button
              type="button"
              class="px-2 py-0.5 rounded-lg border text-[10px] font-bold transition bg-white hover:bg-slate-100 border-slate-200 text-slate-700"
              @click="applyTimePreset(newQuizForm, '2days')"
            >
              +2 Hari (23:59)
            </button>
            <button
              type="button"
              class="px-2 py-0.5 rounded-lg border text-[10px] font-bold transition bg-white hover:bg-slate-100 border-slate-200 text-slate-700"
              @click="applyTimePreset(newQuizForm, 'rabu')"
            >
              Rabu 23:00 WIB
            </button>
            <button
              type="button"
              class="px-2 py-0.5 rounded-lg border text-[10px] font-bold transition bg-white hover:bg-slate-100 border-slate-200 text-slate-700"
              @click="applyTimePreset(newQuizForm, '7days')"
            >
              +7 Hari (1 Minggu)
            </button>
            <button
              type="button"
              class="px-2 py-0.5 rounded-lg border text-[10px] font-bold transition bg-white hover:bg-slate-100 border-slate-200 text-slate-700"
              @click="applyTimePreset(newQuizForm, 'none')"
            >
              Tanpa Batas Tutup
            </button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
            <div>
              <label class="block text-[11px] font-bold text-slate-600 mb-1">Waktu Mulai Buka (Available From)</label>
              <input
                v-model="newQuizForm.available_from"
                type="datetime-local"
                class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-xl text-xs font-mono font-medium focus:ring-1 focus:ring-indigo-500"
              >
              <span class="text-[10px] text-slate-400 mt-0.5 block">Siswa belum dapat memulai sebelum waktu ini</span>
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-600 mb-1">Waktu Selesai Tutup (Available Until)</label>
              <input
                v-model="newQuizForm.available_until"
                type="datetime-local"
                class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-xl text-xs font-mono font-medium focus:ring-1 focus:ring-indigo-500"
              >
              <span class="text-[10px] text-slate-400 mt-0.5 block">Setelah waktu ini sesi kuis ditutup bagi siswa</span>
            </div>
          </div>

          <div class="p-2.5 rounded-xl bg-indigo-50/70 border border-indigo-100 flex items-start gap-2 text-[11px] text-indigo-900 leading-relaxed">
            <svg class="w-4 h-4 text-indigo-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>
              <b>Info Akses Siswa:</b> Saat batas waktu selesai, siswa tidak dapat mengakses/memulai kuis lagi, namun <b>modul bahan ajar sub-bab tetap dapat dibaca untuk review</b>.
            </span>
          </div>
        </div>

        <!-- Sumber Butir Soal Selector (Jika bukan tugas file) -->
        <div v-if="newQuizForm.quiz_type !== 'assignment'" class="space-y-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">Pilihan Sumber Butir Soal</label>
            <div class="grid grid-cols-3 gap-2">
              <button
                type="button"
                class="p-2.5 rounded-xl border text-xs font-bold text-center transition flex flex-col items-center justify-center gap-1 min-tap-target"
                :class="newQuizForm.question_source === 'manual' ? 'bg-indigo-50 border-indigo-400 text-indigo-900 ring-1 ring-indigo-400 font-black' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-white'"
                @click="newQuizForm.question_source = 'manual'; if (newQuizForm.manual_questions.length === 0) addDraftQuestion()"
              >
                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                <span>Tulis Manual</span>
                <span class="text-[9px] font-normal text-slate-500">Campur multi-model</span>
              </button>
              <button
                type="button"
                class="p-2.5 rounded-xl border text-xs font-bold text-center transition flex flex-col items-center justify-center gap-1 min-tap-target"
                :class="newQuizForm.question_source === 'bank' ? 'bg-indigo-50 border-indigo-400 text-indigo-900 ring-1 ring-indigo-400 font-black' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-white'"
                @click="newQuizForm.question_source = 'bank'"
              >
                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span>Dari Bank Soal</span>
                <span class="text-[9px] font-normal text-slate-500">Paket publik / milik guru</span>
              </button>
              <button
                type="button"
                class="p-2.5 rounded-xl border text-xs font-bold text-center transition flex flex-col items-center justify-center gap-1 min-tap-target"
                :class="newQuizForm.question_source === 'later' ? 'bg-indigo-50 border-indigo-400 text-indigo-900 ring-1 ring-indigo-400 font-black' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-white'"
                @click="newQuizForm.question_source = 'later'"
              >
                <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Soal Nanti</span>
                <span class="text-[9px] font-normal text-slate-500">Kerangka dulu</span>
              </button>
            </div>
          </div>

          <!-- OPSI 1: TULIS SOAL MANUAL LANGSUNG (MULTI-MODEL SUPPORT) -->
          <div v-if="newQuizForm.question_source === 'manual'" class="p-3 bg-indigo-50/50 border border-indigo-200/70 rounded-2xl space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-indigo-950 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                Daftar Butir Soal Campuran ({{ newQuizForm.manual_questions.length }} Butir)
              </span>
              <button
                type="button"
                class="px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold transition flex items-center gap-1 shadow-2xs"
                @click="addDraftQuestion"
              >
                <span>+ Tambah Soal</span>
              </button>
            </div>

            <!-- Draft question cards -->
            <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
              <div
                v-for="(q, qIdx) in newQuizForm.manual_questions"
                :key="qIdx"
                class="bg-white border border-slate-200 rounded-xl p-3 space-y-2.5 shadow-2xs"
              >
                <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-2">
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-black text-slate-800">Soal #{{ qIdx + 1 }}</span>
                    <!-- Model Soal Switcher -->
                    <div class="flex items-center gap-1 flex-wrap">
                      <button
                        v-for="t in QUESTION_TYPES"
                        :key="t.value"
                        type="button"
                        class="px-1.5 py-0.5 rounded text-[10px] font-bold transition"
                        :class="q.type === t.value ? 'bg-indigo-600 text-white font-black' : 'bg-slate-100 hover:bg-slate-200 text-slate-600'"
                        @click="changeQuestionType(q, t.value)"
                      >
                        {{ t.label }}
                      </button>
                    </div>
                  </div>

                  <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1 text-[11px]">
                      <span class="text-slate-400 font-semibold">Bobot:</span>
                      <input
                        v-model.number="q.weight"
                        type="number"
                        min="1"
                        max="100"
                        class="w-12 px-1.5 py-0.5 border border-slate-200 rounded text-center text-[11px] font-mono font-bold"
                      >
                    </div>
                    <button
                      v-if="newQuizForm.manual_questions.length > 1"
                      type="button"
                      class="text-rose-500 hover:text-rose-700 text-[11px] font-bold p-1 rounded hover:bg-rose-50 transition"
                      title="Hapus butir soal"
                      @click="removeDraftQuestion(qIdx)"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>

                <div>
                  <textarea
                    v-model="q.question"
                    rows="2"
                    placeholder="Tuliskan pertanyaan / stimulus soal di sini..."
                    required
                    class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-medium focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                  />
                  <!-- Lampiran Gambar Butir Soal (WebP Kompresi & Clipboard Ctrl+V) -->
                  <div class="mt-2">
                    <QuestionImageUploader
                      v-model="q.image_url"
                      v-model:image-meta="q.image_meta"
                    />
                  </div>
                </div>

                <!-- TIPE 1: PILIHAN GANDA (1 Kunci) -->
                <div v-if="q.type === 'pg'" class="space-y-1.5">
                  <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pilihan Jawaban (Pilih Lingkaran untuk Kunci):</span>
                    <button
                      type="button"
                      class="text-[10px] font-bold text-indigo-600 hover:underline"
                      @click="addQuestionOption(q)"
                    >
                      + Opsi
                    </button>
                  </div>
                  <div
                    v-for="(opt, optIdx) in q.options"
                    :key="optIdx"
                    class="flex items-center gap-2 p-1 rounded-lg transition"
                    :class="q.correct_answer === opt.key ? 'bg-emerald-50/70 border border-emerald-200' : 'bg-slate-50 border border-slate-200/80'"
                  >
                    <label class="flex items-center gap-1.5 cursor-pointer shrink-0 pl-1">
                      <input
                        type="radio"
                        :name="`draft-correct-${qIdx}`"
                        :value="opt.key"
                        v-model="q.correct_answer"
                        class="text-emerald-600 focus:ring-emerald-500"
                      >
                      <span class="w-5 h-5 rounded-md font-mono text-[11px] font-black flex items-center justify-center" :class="q.correct_answer === opt.key ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700'">
                        {{ opt.key }}
                      </span>
                    </label>
                    <input
                      v-model="opt.text"
                      type="text"
                      required
                      :placeholder="`Teks pilihan ${opt.key}...`"
                      class="flex-1 px-2 py-1 bg-white border border-slate-200 rounded text-xs font-medium focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    >
                    <button
                      v-if="q.options && q.options.length > 2"
                      type="button"
                      class="p-1 text-slate-400 hover:text-rose-600 transition"
                      @click="removeQuestionOption(q, optIdx)"
                    >
                      &times;
                    </button>
                  </div>
                </div>

                <!-- TIPE 2: PG KOMPLEKS (Banyak Kunci) -->
                <div v-else-if="q.type === 'pg_kompleks'" class="space-y-1.5">
                  <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pilihan Jawaban (Centang Kotak untuk Semua Kunci Benar):</span>
                    <button
                      type="button"
                      class="text-[10px] font-bold text-indigo-600 hover:underline"
                      @click="addQuestionOption(q)"
                    >
                      + Opsi
                    </button>
                  </div>
                  <div
                    v-for="(opt, optIdx) in q.options"
                    :key="optIdx"
                    class="flex items-center gap-2 p-1 rounded-lg transition"
                    :class="opt.is_correct ? 'bg-indigo-50/70 border border-indigo-200' : 'bg-slate-50 border border-slate-200/80'"
                  >
                    <label class="flex items-center gap-1.5 cursor-pointer shrink-0 pl-1">
                      <input
                        type="checkbox"
                        v-model="opt.is_correct"
                        class="rounded text-indigo-600 focus:ring-indigo-500"
                      >
                      <span class="w-5 h-5 rounded-md font-mono text-[11px] font-black flex items-center justify-center" :class="opt.is_correct ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-700'">
                        {{ opt.key }}
                      </span>
                    </label>
                    <input
                      v-model="opt.text"
                      type="text"
                      required
                      :placeholder="`Teks opsi ${opt.key}...`"
                      class="flex-1 px-2 py-1 bg-white border border-slate-200 rounded text-xs font-medium focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    >
                    <button
                      v-if="q.options && q.options.length > 2"
                      type="button"
                      class="p-1 text-slate-400 hover:text-rose-600 transition"
                      @click="removeQuestionOption(q, optIdx)"
                    >
                      &times;
                    </button>
                  </div>
                </div>

                <!-- TIPE 3: ISIAN SINGKAT -->
                <div v-else-if="q.type === 'isian'" class="space-y-2">
                  <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Kunci Jawaban Diterima (Pencocokan Teks Bebas):</span>
                    <button
                      type="button"
                      class="text-[10px] font-bold text-indigo-600 hover:underline"
                      @click="addAcceptedAnswer(q)"
                    >
                      + Tambah Alternatif
                    </button>
                  </div>
                  <div class="space-y-1.5">
                    <div
                      v-for="(_, ansIdx) in (q.data?.accepted_answers || [])"
                      :key="ansIdx"
                      class="flex items-center gap-2"
                    >
                      <input
                        v-model="q.data.accepted_answers[ansIdx]"
                        type="text"
                        required
                        :placeholder="`Jawaban kunci ${ansIdx + 1} (contoh: kloroplas)...`"
                        class="flex-1 px-2.5 py-1 bg-emerald-50/40 border border-emerald-200 rounded-lg text-xs font-medium text-emerald-950 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                      >
                      <button
                        v-if="q.data.accepted_answers.length > 1"
                        type="button"
                        class="p-1 text-slate-400 hover:text-rose-600 transition"
                        @click="removeAcceptedAnswer(q, ansIdx)"
                      >
                        &times;
                      </button>
                    </div>
                  </div>
                  <p class="text-[10px] text-slate-400 italic">Siswa dianggap benar jika mengetik salah satu kata di atas (tidak sensitif huruf besar/kecil).</p>
                </div>

                <!-- TIPE 4: MENJODOHKAN -->
                <div v-else-if="q.type === 'menjodohkan'" class="space-y-2">
                  <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pasangan Cocok (Premis Kiri ↔ Jawaban Kanan):</span>
                    <button
                      type="button"
                      class="text-[10px] font-bold text-indigo-600 hover:underline"
                      @click="addPair(q)"
                    >
                      + Tambah Pasangan
                    </button>
                  </div>
                  <div class="space-y-1.5">
                    <div
                      v-for="(p, pIdx) in (q.data?.pairs || [])"
                      :key="pIdx"
                      class="flex items-center gap-2"
                    >
                      <input
                        v-model="p.left"
                        type="text"
                        required
                        placeholder="Premis / Istilah Kiri..."
                        class="flex-1 px-2 py-1 bg-slate-50 border border-slate-200 rounded text-xs font-medium"
                      >
                      <span class="text-xs text-slate-400 font-bold">↔</span>
                      <input
                        v-model="p.right"
                        type="text"
                        required
                        placeholder="Pasangan Kanan (Kunci)..."
                        class="flex-1 px-2 py-1 bg-emerald-50/40 border border-emerald-200 rounded text-xs font-medium text-emerald-950"
                      >
                      <button
                        v-if="q.data.pairs.length > 1"
                        type="button"
                        class="p-1 text-slate-400 hover:text-rose-600 transition"
                        @click="removePair(q, pIdx)"
                      >
                        &times;
                      </button>
                    </div>
                  </div>
                  <p class="text-[10px] text-slate-400 italic">Daftar pasangan kanan akan diacak otomatis oleh sistem saat siswa mengerjakan.</p>
                </div>

                <!-- TIPE 5: BENAR / SALAH -->
                <div v-else-if="q.type === 'benar_salah'" class="space-y-2">
                  <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Daftar Pernyataan & Kunci Nilai:</span>
                    <button
                      type="button"
                      class="text-[10px] font-bold text-indigo-600 hover:underline"
                      @click="addStatement(q)"
                    >
                      + Tambah Pernyataan
                    </button>
                  </div>
                  <div class="space-y-1.5">
                    <div
                      v-for="(st, stIdx) in (q.data?.statements || [])"
                      :key="stIdx"
                      class="flex items-center gap-2 p-1.5 rounded-lg border border-slate-200 bg-slate-50/50"
                    >
                      <input
                        v-model="st.text"
                        type="text"
                        required
                        placeholder="Isi pernyataan evaluasi..."
                        class="flex-1 px-2 py-1 bg-white border border-slate-200 rounded text-xs font-medium"
                      >
                      <div class="flex items-center gap-1 shrink-0">
                        <button
                          type="button"
                          class="px-2 py-0.5 rounded text-[10px] font-black transition"
                          :class="st.answer === true ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700'"
                          @click="st.answer = true"
                        >
                          BENAR
                        </button>
                        <button
                          type="button"
                          class="px-2 py-0.5 rounded text-[10px] font-black transition"
                          :class="st.answer === false ? 'bg-rose-600 text-white' : 'bg-slate-200 text-slate-700'"
                          @click="st.answer = false"
                        >
                          SALAH
                        </button>
                      </div>
                      <button
                        v-if="q.data.statements.length > 1"
                        type="button"
                        class="p-1 text-slate-400 hover:text-rose-600 transition"
                        @click="removeStatement(q, stIdx)"
                      >
                        &times;
                      </button>
                    </div>
                  </div>
                </div>

                <!-- TIPE 6: ESAI -->
                <div v-else-if="q.type === 'esai'" class="p-3 bg-amber-50/60 border border-amber-200 rounded-xl text-xs text-amber-900">
                  <p class="font-bold">Pertanyaan Esai / Uraian Terbuka</p>
                  <p class="text-[11px] text-amber-800/90 mt-0.5">Siswa akan menjawab di lembar teks terbuka. Penilaian akan dilakukan oleh guru secara manual di halaman hasil & nilai.</p>
                </div>
              </div>
            </div>

            <button
              type="button"
              class="w-full py-2 border border-dashed border-indigo-300 hover:border-indigo-500 rounded-xl text-xs font-bold text-indigo-700 bg-white hover:bg-indigo-50/50 transition flex items-center justify-center gap-1.5"
              @click="addDraftQuestion"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              <span>+ Tambah Butir Soal Baru</span>
            </button>
          </div>

          <!-- OPSI 2: AMBIL DARI BANK SOAL -->
          <div v-else-if="newQuizForm.question_source === 'bank'" class="p-3 bg-emerald-50/50 border border-emerald-200/80 rounded-2xl space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-emerald-950 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                Pilih Paket Bank Soal Tersedia
              </span>
              <select
                v-model="bankDifficultyFilter"
                class="px-2 py-1 bg-white border border-emerald-200 rounded-lg text-[11px] font-bold text-slate-700"
              >
                <option value="">Semua Kesulitan</option>
                <option value="easy">Mudah</option>
                <option value="medium">Sedang</option>
                <option value="hard">Sulit</option>
              </select>
            </div>

            <!-- List bank soal cards -->
            <div v-if="filteredQuestionBanks.length === 0" class="p-4 bg-white border border-dashed border-emerald-200 rounded-xl text-center text-xs text-slate-500">
              Belum ada paket bank soal yang sesuai filter. Anda dapat beralih ke <b>Tulis Manual</b> untuk membuat soal langsung.
            </div>
            <div v-else class="space-y-2 max-h-72 overflow-y-auto pr-1">
              <label
                v-for="b in filteredQuestionBanks"
                :key="b.id"
                class="flex items-start gap-3 p-3 bg-white border rounded-xl cursor-pointer transition"
                :class="newQuizForm.question_bank_id === b.id ? 'border-emerald-500 ring-1 ring-emerald-500 shadow-2xs' : 'border-slate-200 hover:border-emerald-200'"
              >
                <input
                  type="radio"
                  name="selected_bank"
                  :value="b.id"
                  v-model="newQuizForm.question_bank_id"
                  class="mt-1 text-emerald-600 focus:ring-emerald-500"
                >
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase" :class="b.difficulty === 'hard' ? 'bg-rose-100 text-rose-800' : b.difficulty === 'medium' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'">
                      {{ b.difficulty || 'UMUM' }}
                    </span>
                    <span v-if="b.is_public" class="px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 text-[9px] font-bold">
                      Publik Madrasah
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Kelas {{ b.grade_level || '11' }}</span>
                  </div>
                  <p class="font-bold text-xs text-slate-900 mt-1">{{ b.title }}</p>
                  <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-1">{{ b.description || 'Paket soal standar evaluasi.' }}</p>
                  <p class="text-[10px] text-emerald-700 font-extrabold mt-1">
                    {{ b.total_items || b.items_count || (b.items ? b.items.length : 0) }} Butir Soal Terpasang
                  </p>
                </div>
              </label>
            </div>
          </div>

          <!-- OPSI 3: SOAL NANTI -->
          <div v-else class="p-3.5 bg-amber-50/70 border border-amber-200 rounded-2xl flex items-start gap-2.5 text-xs text-amber-900">
            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
              <p class="font-bold">Kuis Kerangka Dibuat Terlebih Dahulu</p>
              <p class="text-[11px] text-amber-800/90 mt-0.5">
                Kuis akan langsung tersimpan di sub-bab. Anda dapat mengisi butir soal kapan saja melalui tombol <b>Kelola Soal</b> pada kartu aktivitas kelas.
              </p>
            </div>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex justify-end">
          <button
            type="submit"
            :disabled="savingSlideOver"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition disabled:opacity-50"
          >
            {{ savingSlideOver ? 'Menyimpan...' : 'Simpan & Publikasikan' }}
          </button>
        </div>
      </form>

      <!-- Form 6: New Journal -->
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
            placeholder="Contoh: Praktik menghitung tinggi gedung dengan perbandingan sudut..."
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          />
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Kendala / Siswa yang Butuh Perhatian (Opsional)</label>
          <textarea
            v-model="newJournalForm.challenges"
            rows="2"
            placeholder="Catatan kendala di kelas..."
            class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs"
          />
        </div>
        <div class="pt-3 border-t border-slate-100 flex justify-end">
          <button
            type="submit"
            :disabled="savingSlideOver"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition disabled:opacity-50"
          >
            {{ savingSlideOver ? 'Menyimpan...' : 'Simpan Jurnal' }}
          </button>
        </div>
      </form>
    </SlideOver>

    <!-- 5. MODAL KELOLA BUTIR SOAL KUIS (INTERAKTIF LANGSUNG DI KELAS) -->
    <div
      v-if="isQuestionManagerOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
      @click.self="isQuestionManagerOpen = false"
    >
      <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <!-- Modal Header -->
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between gap-4 border-b border-slate-800">
          <div>
            <div class="flex items-center gap-2">
              <span class="px-2 py-0.5 rounded-md bg-indigo-500/20 text-indigo-300 border border-indigo-400/30 text-[10px] font-black uppercase tracking-wider">
                Editor Soal Kuis
              </span>
              <span class="text-xs text-slate-400 font-mono">
                ID #{{ activeManagingQuiz?.id }}
              </span>
            </div>
            <h3 class="text-base font-black text-white mt-0.5 truncate">
              {{ activeManagingQuiz?.title || 'Kelola Butir Soal' }}
            </h3>
            <div class="flex items-center gap-3 text-xs text-slate-300 mt-1">
              <span class="font-bold text-emerald-400">{{ managingQuestions.length }} Butir Soal Aktif</span>
              <span>&bull;</span>
              <span class="text-slate-400">Total Bobot: <b class="text-white">{{ managingQuestions.reduce((a: number, b: any) => a + (Number(b.weight) || 10), 0) }} Poin</b></span>
            </div>
          </div>

          <button
            type="button"
            class="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl transition"
            @click="isQuestionManagerOpen = false"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div class="p-6 overflow-y-auto space-y-5 flex-1">
          <!-- 1. PENGATURAN JENDELA WAKTU & JADWAL KUIS -->
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Jendela Waktu Akses Belajar Siswa (Time Window)
              </span>
              <span class="text-[10px] text-slate-400 font-medium">Batas buka & tutup ujian CBT</span>
            </div>

            <!-- Quick presets -->
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="text-[10px] text-slate-500 font-bold mr-1">Preset Cepat:</span>
              <button
                type="button"
                class="px-2 py-0.5 rounded-lg border text-[10px] font-bold transition bg-white hover:bg-slate-100 border-slate-200 text-slate-700"
                @click="applyTimePreset(managingSchedule, '2days')"
              >
                +2 Hari (23:59)
              </button>
              <button
                type="button"
                class="px-2 py-0.5 rounded-lg border text-[10px] font-bold transition bg-white hover:bg-slate-100 border-slate-200 text-slate-700"
                @click="applyTimePreset(managingSchedule, 'rabu')"
              >
                Rabu 23:00 WIB
              </button>
              <button
                type="button"
                class="px-2 py-0.5 rounded-lg border text-[10px] font-bold transition bg-white hover:bg-slate-100 border-slate-200 text-slate-700"
                @click="applyTimePreset(managingSchedule, '7days')"
              >
                +7 Hari (1 Minggu)
              </button>
              <button
                type="button"
                class="px-2 py-0.5 rounded-lg border text-[10px] font-bold transition bg-white hover:bg-slate-100 border-slate-200 text-slate-700"
                @click="applyTimePreset(managingSchedule, 'none')"
              >
                Tanpa Batas Tutup
              </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
              <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Durasi Pengerjaan</label>
                <div class="flex items-center gap-1">
                  <input
                    v-model.number="managingSchedule.duration_minutes"
                    type="number"
                    min="5"
                    max="240"
                    class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-xl text-xs font-mono font-bold focus:ring-1 focus:ring-indigo-500"
                  >
                  <span class="text-xs text-slate-400 font-semibold shrink-0">Menit</span>
                </div>
              </div>
              <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Waktu Mulai Buka</label>
                <input
                  v-model="managingSchedule.available_from"
                  type="datetime-local"
                  class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-xl text-xs font-mono font-medium focus:ring-1 focus:ring-indigo-500"
                >
              </div>
              <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Waktu Selesai Tutup</label>
                <input
                  v-model="managingSchedule.available_until"
                  type="datetime-local"
                  class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-xl text-xs font-mono font-medium focus:ring-1 focus:ring-indigo-500"
                >
              </div>
            </div>

            <p class="text-[11px] text-slate-500 italic">
              * Siswa tidak dapat mengakses/memulai ujian setelah waktu tutup berakhir. Modul dan bahan ajar tetap dapat direview siswa.
            </p>
          </div>

          <!-- 2. Fast Bank Soal Importer Card -->
          <div class="p-4 bg-emerald-50/60 border border-emerald-200 rounded-2xl space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-emerald-950 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                Impor Tambahan Soal dari Bank Soal Madrasah
              </span>
              <span class="text-[10px] text-emerald-700 font-medium">Salin otomatis butir soal</span>
            </div>
            <div class="flex flex-col sm:flex-row items-center gap-2 pt-1">
              <select
                v-model="selectedBankToImport"
                class="w-full sm:flex-1 px-3 py-2 bg-white border border-emerald-200 rounded-xl text-xs font-medium text-slate-800"
              >
                <option value="" disabled>-- Pilih Paket Bank Soal untuk Diimpor --</option>
                <option v-for="b in questionBanks" :key="b.id" :value="b.id">
                  [{{ b.difficulty ? b.difficulty.toUpperCase() : 'UMUM' }}] {{ b.title }} ({{ b.total_items || b.items_count || (b.items ? b.items.length : 0) }} Butir)
                </option>
              </select>
              <button
                type="button"
                :disabled="!selectedBankToImport || importingBank"
                class="w-full sm:w-auto px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl text-xs font-bold transition disabled:opacity-50 shrink-0"
                @click="importBankToActiveQuiz"
              >
                {{ importingBank ? 'Mengimpor...' : 'Terapkan Soal Bank' }}
              </button>
            </div>
          </div>

          <!-- Empty Questions State -->
          <div
            v-if="managingQuestions.length === 0"
            class="py-12 border-2 border-dashed border-slate-200 rounded-3xl text-center space-y-3"
          >
            <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-500 mx-auto flex items-center justify-center">
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-bold text-slate-700">Kuis ini belum memiliki butir soal.</p>
              <p class="text-xs text-slate-400 mt-0.5">Siswa tidak dapat mengerjakan kuis sebelum ada butir soal yang diisi.</p>
            </div>
            <div class="flex items-center justify-center gap-2 pt-2">
              <button
                type="button"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition shadow-2xs"
                @click="addManagingQuestion"
              >
                + Buat Butir Soal Pertama
              </button>
            </div>
          </div>

          <!-- 3. Questions List (Multi-Type Support) -->
          <div v-else class="space-y-4">
            <div
              v-for="(q, qIdx) in managingQuestions"
              :key="qIdx"
              class="border border-slate-200 rounded-2xl p-4 bg-white shadow-2xs space-y-3"
            >
              <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2.5">
                <div class="flex items-center gap-2">
                  <span class="w-7 h-7 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-900 font-black text-xs flex items-center justify-center">
                    {{ qIdx + 1 }}
                  </span>
                  
                  <!-- Model Soal Switcher -->
                  <div class="flex items-center gap-1 flex-wrap">
                    <button
                      v-for="t in QUESTION_TYPES"
                      :key="t.value"
                      type="button"
                      class="px-2 py-0.5 rounded text-[10px] font-bold transition"
                      :class="q.type === t.value ? 'bg-indigo-600 text-white font-black shadow-2xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-600'"
                      @click="changeQuestionType(q, t.value)"
                    >
                      {{ t.label }}
                    </button>
                  </div>
                </div>

                <div class="flex items-center gap-3">
                  <div class="flex items-center gap-1.5 text-xs">
                    <span class="text-slate-400 font-semibold">Bobot:</span>
                    <input
                      v-model.number="q.weight"
                      type="number"
                      min="1"
                      max="100"
                      class="w-14 px-2 py-1 border border-slate-200 rounded-lg text-center text-xs font-mono font-bold"
                    >
                    <span class="text-slate-400 text-[10px]">poin</span>
                  </div>
                  <button
                    type="button"
                    class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                    title="Hapus butir soal ini"
                    @click="removeManagingQuestion(qIdx)"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Question text -->
              <div>
                <label class="block text-[11px] font-bold text-slate-500 mb-1">Pertanyaan / Stimulus Soal</label>
                <textarea
                  v-model="q.question"
                  rows="2"
                  placeholder="Tuliskan teks pertanyaan soal..."
                  class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-medium focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                />
                <!-- Lampiran Gambar Butir Soal (WebP Kompresi & Clipboard Ctrl+V) -->
                <div class="mt-2">
                  <QuestionImageUploader
                    v-model="q.image_url"
                    v-model:image-meta="q.image_meta"
                  />
                </div>
              </div>

              <!-- TIPE 1: PILIHAN GANDA (1 KUNCI) -->
              <div v-if="q.type === 'pg'" class="space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pilihan Jawaban (Klik Lingkaran untuk Kunci Jawaban):</span>
                  <button
                    type="button"
                    class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 hover:underline"
                    @click="addQuestionOption(q)"
                  >
                    + Tambah Opsi
                  </button>
                </div>
                <div class="space-y-1.5">
                  <div
                    v-for="(opt, optIdx) in q.options"
                    :key="optIdx"
                    class="flex items-center gap-2 p-1.5 rounded-xl transition"
                    :class="q.correct_answer === opt.key ? 'bg-emerald-50 border border-emerald-300' : 'bg-slate-50 border border-slate-200'"
                  >
                    <label class="flex items-center gap-1.5 cursor-pointer shrink-0 pl-1">
                      <input
                        type="radio"
                        :name="`managing-correct-${qIdx}`"
                        :value="opt.key"
                        v-model="q.correct_answer"
                        class="text-emerald-600 focus:ring-emerald-500"
                      >
                      <span
                        class="w-6 h-6 rounded-lg font-mono text-xs font-black flex items-center justify-center transition"
                        :class="q.correct_answer === opt.key ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-slate-200 text-slate-700'"
                      >
                        {{ opt.key }}
                      </span>
                    </label>
                    <input
                      v-model="opt.text"
                      type="text"
                      :placeholder="`Teks pilihan ${opt.key}...`"
                      class="flex-1 px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-medium focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    >
                    <button
                      v-if="q.options && q.options.length > 2"
                      type="button"
                      class="p-1 text-slate-400 hover:text-rose-600 transition"
                      title="Hapus pilihan ini"
                      @click="removeQuestionOption(q, optIdx)"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>

              <!-- TIPE 2: PG KOMPLEKS (BANYAK KUNCI BENAR) -->
              <div v-else-if="q.type === 'pg_kompleks'" class="space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pilihan Jawaban (Centang Kotak untuk Semua Jawaban Benar):</span>
                  <button
                    type="button"
                    class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 hover:underline"
                    @click="addQuestionOption(q)"
                  >
                    + Tambah Opsi
                  </button>
                </div>
                <div class="space-y-1.5">
                  <div
                    v-for="(opt, optIdx) in q.options"
                    :key="optIdx"
                    class="flex items-center gap-2 p-1.5 rounded-xl transition"
                    :class="opt.is_correct ? 'bg-indigo-50 border border-indigo-300' : 'bg-slate-50 border border-slate-200'"
                  >
                    <label class="flex items-center gap-1.5 cursor-pointer shrink-0 pl-1">
                      <input
                        type="checkbox"
                        v-model="opt.is_correct"
                        class="rounded text-indigo-600 focus:ring-indigo-500"
                      >
                      <span
                        class="w-6 h-6 rounded-lg font-mono text-xs font-black flex items-center justify-center transition"
                        :class="opt.is_correct ? 'bg-indigo-600 text-white shadow-2xs' : 'bg-slate-200 text-slate-700'"
                      >
                        {{ opt.key }}
                      </span>
                    </label>
                    <input
                      v-model="opt.text"
                      type="text"
                      :placeholder="`Teks opsi ${opt.key}...`"
                      class="flex-1 px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-medium focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    >
                    <button
                      v-if="q.options && q.options.length > 2"
                      type="button"
                      class="p-1 text-slate-400 hover:text-rose-600 transition"
                      title="Hapus pilihan ini"
                      @click="removeQuestionOption(q, optIdx)"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>

              <!-- TIPE 3: ISIAN SINGKAT -->
              <div v-else-if="q.type === 'isian'" class="space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Kunci Jawaban Diterima (Pencocokan Teks Bebas):</span>
                  <button
                    type="button"
                    class="text-[10px] font-bold text-indigo-600 hover:underline"
                    @click="addAcceptedAnswer(q)"
                  >
                    + Tambah Alternatif Kunci
                  </button>
                </div>
                <div class="space-y-1.5">
                  <div
                    v-for="(_, ansIdx) in (q.data?.accepted_answers || [])"
                    :key="ansIdx"
                    class="flex items-center gap-2"
                  >
                    <input
                      v-model="q.data.accepted_answers[ansIdx]"
                      type="text"
                      required
                      :placeholder="`Jawaban kunci ${ansIdx + 1} (contoh: kloroplas)...`"
                      class="flex-1 px-2.5 py-1.5 bg-emerald-50/40 border border-emerald-200 rounded-lg text-xs font-medium text-emerald-950 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    >
                    <button
                      v-if="q.data.accepted_answers.length > 1"
                      type="button"
                      class="p-1 text-slate-400 hover:text-rose-600 transition"
                      @click="removeAcceptedAnswer(q, ansIdx)"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                </div>
                <p class="text-[10px] text-slate-400 italic">Siswa dianggap benar jika mengetik salah satu kata di atas (tidak sensitif huruf besar/kecil).</p>
              </div>

              <!-- TIPE 4: MENJODOHKAN -->
              <div v-else-if="q.type === 'menjodohkan'" class="space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pasangan Cocok (Premis Kiri ↔ Jawaban Kanan):</span>
                  <button
                    type="button"
                    class="text-[10px] font-bold text-indigo-600 hover:underline"
                    @click="addPair(q)"
                  >
                    + Tambah Pasangan
                  </button>
                </div>
                <div class="space-y-1.5">
                  <div
                    v-for="(p, pIdx) in (q.data?.pairs || [])"
                    :key="pIdx"
                    class="flex items-center gap-2"
                  >
                    <input
                      v-model="p.left"
                      type="text"
                      required
                      placeholder="Premis / Istilah Kiri..."
                      class="flex-1 px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium"
                    >
                    <span class="text-xs text-slate-400 font-bold">↔</span>
                    <input
                      v-model="p.right"
                      type="text"
                      required
                      placeholder="Pasangan Kanan (Kunci)..."
                      class="flex-1 px-2.5 py-1.5 bg-emerald-50/40 border border-emerald-200 rounded-lg text-xs font-medium text-emerald-950"
                    >
                    <button
                      v-if="q.data.pairs.length > 1"
                      type="button"
                      class="p-1 text-slate-400 hover:text-rose-600 transition"
                      @click="removePair(q, pIdx)"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                </div>
                <p class="text-[10px] text-slate-400 italic">Daftar pasangan kanan akan diacak otomatis oleh sistem saat siswa mengerjakan.</p>
              </div>

              <!-- TIPE 5: BENAR / SALAH -->
              <div v-else-if="q.type === 'benar_salah'" class="space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Daftar Pernyataan & Kunci Nilai:</span>
                  <button
                    type="button"
                    class="text-[10px] font-bold text-indigo-600 hover:underline"
                    @click="addStatement(q)"
                  >
                    + Tambah Pernyataan
                  </button>
                </div>
                <div class="space-y-1.5">
                  <div
                    v-for="(st, stIdx) in (q.data?.statements || [])"
                    :key="stIdx"
                    class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 bg-slate-50/50"
                  >
                    <input
                      v-model="st.text"
                      type="text"
                      required
                      placeholder="Isi pernyataan evaluasi..."
                      class="flex-1 px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-medium"
                    >
                    <div class="flex items-center gap-1 shrink-0">
                      <button
                        type="button"
                        class="px-2.5 py-1 rounded-lg text-[10px] font-black transition"
                        :class="st.answer === true ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-slate-200 text-slate-700'"
                        @click="st.answer = true"
                      >
                        BENAR
                      </button>
                      <button
                        type="button"
                        class="px-2.5 py-1 rounded-lg text-[10px] font-black transition"
                        :class="st.answer === false ? 'bg-rose-600 text-white shadow-2xs' : 'bg-slate-200 text-slate-700'"
                        @click="st.answer = false"
                      >
                        SALAH
                      </button>
                    </div>
                    <button
                      v-if="q.data.statements.length > 1"
                      type="button"
                      class="p-1 text-slate-400 hover:text-rose-600 transition"
                      @click="removeStatement(q, stIdx)"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>

              <!-- TIPE 6: ESAI -->
              <div v-else-if="q.type === 'esai'" class="p-3 bg-amber-50/60 border border-amber-200 rounded-xl text-xs text-amber-900">
                <p class="font-bold">Pertanyaan Esai / Uraian Terbuka</p>
                <p class="text-[11px] text-amber-800/90 mt-0.5">Siswa akan menjawab di lembar teks terbuka. Penilaian akan dilakukan oleh guru secara manual di halaman hasil & nilai.</p>
              </div>

              <!-- Explanation (Optional) -->
              <div>
                <label class="block text-[10px] font-semibold text-slate-400 mb-0.5">Pembahasan / Penjelasan Kunci Jawaban (Opsional):</label>
                <input
                  v-model="q.explanation"
                  type="text"
                  placeholder="Penjelasan kenapa kunci jawaban ini benar..."
                  class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-[11px] text-slate-700"
                >
              </div>
            </div>

            <!-- Add new question button -->
            <button
              type="button"
              class="w-full py-3 border-2 border-dashed border-indigo-200 hover:border-indigo-400 rounded-2xl text-xs font-bold text-indigo-700 bg-indigo-50/30 hover:bg-indigo-50 transition flex items-center justify-center gap-1.5"
              @click="addManagingQuestion"
            >
              <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              <span>+ Tambah Butir Soal Baru</span>
            </button>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between gap-3">
          <button
            type="button"
            class="px-4 py-2 border border-slate-300 text-slate-700 hover:bg-white rounded-xl text-xs font-bold transition"
            @click="isQuestionManagerOpen = false"
          >
            Tutup
          </button>
          <button
            type="button"
            :disabled="managingSaving"
            class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black transition flex items-center gap-1.5 shadow-sm disabled:opacity-50"
            @click="saveManagingQuestions"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ managingSaving ? 'Menyimpan...' : `Simpan Perubahan (${managingQuestions.length} Soal)` }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
