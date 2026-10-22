<script setup lang="ts">
import { computed, ref, reactive, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import AppButton from '~/components/ui/AppButton.vue'
import AppBadge from '~/components/ui/AppBadge.vue'
import AppSkeleton from '~/components/ui/AppSkeleton.vue'
import AppEmptyState from '~/components/ui/AppEmptyState.vue'

definePageMeta({
  layout: 'student',
  middleware: 'auth'
})

const route = useRoute()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const storageBase = config.public.storageBase || 'http://localhost:8000/storage'
const token = useCookie<string | null>('auth_token')
const { user, getUserRole } = useAuth()

// Sidebar open/collapse synced with student layout header hamburger
const isSidebarOpen = useState('isStudentSidebarOpen', () => true)

const isStudent = computed(() => ['student', 'siswa'].includes(getUserRole()))
const isTeacher = computed(() => ['teacher', 'guru', 'admin', 'super_admin'].includes(getUserRole()))

const loading = ref(true)
const course = ref<any>(null)
const meets = ref<any[]>([])

// Filter & Search states (matching dashboard style)
const sidebarSearch = ref('')
const mainSearch = ref('')
const activeMeetId = ref<number | null>(null)

// Dropdown state: which meets are expanded on the right content area
const openDropdowns = reactive<Record<number, boolean>>({})

// Toggle individual meet dropdown
const toggleDropdown = (meetId: number) => {
  openDropdowns[meetId] = !openDropdowns[meetId]
}

// Expand all / collapse all
const expandAllDropdowns = () => {
  meets.value.forEach(m => {
    openDropdowns[m.id] = true
  })
}

const collapseAllDropdowns = () => {
  meets.value.forEach(m => {
    openDropdowns[m.id] = false
  })
}

// Interactive Discussion state
const discussionInputs = reactive<Record<number, string>>({})
const sendingDiscussion = ref<number | null>(null)

// Assignment Submission Modal state
const showAssignmentModal = ref(false)
const selectedAssignment = ref<any>(null)
const assignmentNotes = ref('')
const assignmentFile = ref<File | null>(null)
const fileErrorMsg = ref('')
const submittingAssignment = ref(false)
const assignmentSuccessMsg = ref('')
const isDraggingFile = ref(false)

// Helpers
const subjectName = computed(() => course.value?.subject?.name || 'Mata Pelajaran')
const className = computed(() => {
  const c = course.value?.class
  return c?.class_name?.name || c?.className?.name || c?.name || 'Kelas XI-MIPA-5'
})
const teacherName = computed(() => course.value?.teacher?.name || 'Guru Pengampu')

const formatTimeNice = (dtStr?: string) => {
  if (!dtStr) return '-'
  try {
    const d = new Date(dtStr)
    return d.toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit'
    }) + ' WIB'
  } catch {
    return dtStr
  }
}

// Evaluasi Jendela Waktu Akses
const getTimeWindowStatus = (item: any) => {
  const now = new Date().getTime()
  const start = item?.start_time ? new Date(item.start_time).getTime() : (item?.available_from ? new Date(item.available_from).getTime() : null)
  const end = item?.end_time ? new Date(item.end_time).getTime() : (item?.available_until ? new Date(item.available_until).getTime() : (item?.due_date ? new Date(item.due_date).getTime() : null))

  if (start && now < start) {
    return {
      isOpen: false,
      isUpcoming: true,
      isClosed: false,
      badge: 'Belum Dibuka',
      variant: 'neutral' as const,
      info: `Dibuka ${new Date(start).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })} WIB`
    }
  }

  if (end && now > end) {
    return {
      isOpen: false,
      isUpcoming: false,
      isClosed: true,
      badge: 'Akses Ditutup',
      variant: 'danger' as const,
      info: `Berakhir ${new Date(end).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })} WIB`
    }
  }

  if (end) {
    return {
      isOpen: true,
      isUpcoming: false,
      isClosed: false,
      badge: 'Aktif',
      variant: 'success' as const,
      info: `Tersedia s/d ${new Date(end).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })} WIB`
    }
  }

  return {
    isOpen: true,
    isUpcoming: false,
    isClosed: false,
    badge: 'Akses Terbuka',
    variant: 'neutral' as const,
    info: 'Akses tanpa batas waktu'
  }
}

// Filtered meets for sidebar search
const sidebarFilteredMeets = computed(() => {
  if (!sidebarSearch.value.trim()) return meets.value
  const q = sidebarSearch.value.toLowerCase().trim()
  return meets.value.filter(m =>
    m.title?.toLowerCase().includes(q) ||
    `pertemuan ${m.meeting_number}`.includes(q)
  )
})

// Filtered meets for main content search
const filteredMainMeets = computed(() => {
  if (!mainSearch.value.trim()) return meets.value
  const q = mainSearch.value.toLowerCase().trim()
  return meets.value.filter(m =>
    m.title?.toLowerCase().includes(q) ||
    m.description?.toLowerCase().includes(q) ||
    `pertemuan ${m.meeting_number}`.includes(q)
  )
})

// -------------------------------------------------------------------
// 6 Kategori Aktivitas Belajar Siswa (Sinkronisasi Dashboard Siswa)
// -------------------------------------------------------------------
const getMeetPretests = (meet: any) => {
  return (meet?.quizzes || []).filter((q: any) => q.activity_category === 'pretest')
}

const getMeetMaterials = (meet: any) => {
  return meet?.materials || []
}

const getMeetDiscussions = (meet: any) => {
  return meet?.discussions || []
}

const getMeetQuizzes = (meet: any) => {
  return (meet?.quizzes || []).filter((q: any) =>
    q.activity_category === 'quiz' ||
    (!q.activity_category && (q.quiz_type === 'multiple_choice' || q.quiz_type === 'quiz'))
  )
}

const getMeetAssignments = (meet: any) => {
  return (meet?.quizzes || []).filter((q: any) =>
    q.activity_category === 'assignment' ||
    (!q.activity_category && (q.quiz_type === 'assignment' || q.quiz_type === 'essay'))
  )
}

const getMeetPosttests = (meet: any) => {
  return (meet?.quizzes || []).filter((q: any) => q.activity_category === 'posttest')
}

// Hitung total konten aktivitas nyata yang ada di database
const countMeetActivities = (meet: any) => {
  const pre = getMeetPretests(meet).length
  const mat = getMeetMaterials(meet).length
  const quiz = getMeetQuizzes(meet).length
  const asg = getMeetAssignments(meet).length
  const post = getMeetPosttests(meet).length
  const disc = getMeetDiscussions(meet).length
  return pre + mat + quiz + asg + post + disc
}

const getQuizSubmission = (quiz: any) => {
  if (!quiz?.submissions || quiz.submissions.length === 0) return null
  return quiz.submissions[0]
}

// Fetch Course and Meets Data
const fetchCourseData = async () => {
  loading.value = true
  try {
    const courseId = route.params.id
    const [cRes, mRes] = await Promise.all([
      $fetch<any>(`${apiBase}/courses/${courseId}`, {
        headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
      }),
      $fetch<any>(`${apiBase}/meets?course_id=${courseId}&paginate=false&with_details=true`, {
        headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
      })
    ])

    course.value = cRes
    const listMeets = mRes?.data || mRes || []
    meets.value = listMeets

    // Check query params if directed to specific meet (e.g. ?meet=2)
    const targetMeetNum = route.query.meet ? parseInt(route.query.meet as string) : null
    listMeets.forEach((m: any, idx: number) => {
      if (targetMeetNum !== null) {
        const isTarget = m.meeting_number === targetMeetNum
        openDropdowns[m.id] = isTarget
        expandedSidebarMeets[m.id] = isTarget
        if (isTarget) {
          activeMeetId.value = m.id
        }
      } else {
        // By default open the first meeting
        openDropdowns[m.id] = idx === 0
        expandedSidebarMeets[m.id] = idx === 0
      }
    })

    if (!activeMeetId.value && listMeets.length > 0) {
      activeMeetId.value = listMeets[0].id
      expandedSidebarMeets[listMeets[0].id] = true
    }

    // Check query params if directed to specific activity (e.g. ?activity=discussion)
    const targetActivity = route.query.activity as string
    if (targetActivity && activeMeetId.value) {
      activeActivityKey.value = targetActivity
      nextTick(() => {
        const el = document.getElementById(`meet-${activeMeetId.value}-${targetActivity}`)
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
      })
    }
  } catch (err) {
    console.error('Gagal memuat data kursus:', err)
  } finally {
    loading.value = false
  }
}

// -------------------------------------------------------------------
const {
  isActivityDone,
  isMaterialDownloaded,
  markMaterialDownloaded,
  isQuizCompleted
} = useStudentActivityProgress()

// Sidebar Activity Dropdown State & Methods (6 Menu Default LMS)
// -------------------------------------------------------------------
const expandedSidebarMeets = reactive<Record<number, boolean>>({})
const activeActivityKey = ref<string | null>(null)

const toggleSidebarMeet = (meet: any) => {
  activeMeetId.value = meet.id
  expandedSidebarMeets[meet.id] = !expandedSidebarMeets[meet.id]
}

const getActivityMenuItems = (meet: any) => [
  { key: 'pretest', label: 'Pretest', isDone: isActivityDone(meet, 'pretest') },
  { key: 'material', label: 'Modul & Bahan Ajar', isDone: isActivityDone(meet, 'material') },
  { key: 'discussion', label: 'Ruang Diskusi', isDone: isActivityDone(meet, 'discussion') },
  { key: 'quiz', label: 'Kuis Formatif', isDone: isActivityDone(meet, 'quiz') },
  { key: 'assignment', label: 'Tugas Mandiri', isDone: isActivityDone(meet, 'assignment') },
  { key: 'posttest', label: 'Posttest', isDone: isActivityDone(meet, 'posttest') },
]

// Select meeting and smooth scroll to specific activity
const selectSidebarActivity = (meet: any, activityKey: string) => {
  activeMeetId.value = meet.id
  openDropdowns[meet.id] = true
  expandedSidebarMeets[meet.id] = true
  activeActivityKey.value = activityKey

  nextTick(() => {
    const el = document.getElementById(`meet-${meet.id}-${activityKey}`)
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    } else {
      const meetEl = document.getElementById(`meet-item-${meet.id}`)
      if (meetEl) {
        meetEl.scrollIntoView({ behavior: 'smooth', block: 'start' })
      }
    }
  })
}

// Select meeting from left sidebar
const selectSidebarMeet = (meet: any) => {
  activeMeetId.value = meet.id
  openDropdowns[meet.id] = true
  expandedSidebarMeets[meet.id] = true

  nextTick(() => {
    const el = document.getElementById(`meet-item-${meet.id}`)
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  })
}

// Send interactive discussion comment
const handleSendDiscussion = async (meetId: number) => {
  const text = (discussionInputs[meetId] || '').trim()
  if (!text) return

  sendingDiscussion.value = meetId
  try {
    await $fetch(`${apiBase}/discussions`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
      body: {
        meet_id: meetId,
        message: text
      }
    })
    discussionInputs[meetId] = ''

    // Refresh meets to get new discussion thread
    const mRes: any = await $fetch(`${apiBase}/meets?course_id=${route.params.id}&paginate=false&with_details=true`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    meets.value = mRes?.data || mRes || []
  } catch (err: any) {
    alert(err?.data?.message || err?.message || 'Gagal mengirim pesan diskusi.')
  } finally {
    sendingDiscussion.value = null
  }
}

// Assignment modal handlers & computed
const currentAssignmentSubmission = computed(() => {
  if (!selectedAssignment.value) return null
  return getQuizSubmission(selectedAssignment.value)
})

const openAssignmentModal = (assignment: any) => {
  selectedAssignment.value = assignment
  assignmentFile.value = null
  fileErrorMsg.value = ''
  assignmentSuccessMsg.value = ''

  const prevSub = getQuizSubmission(assignment)
  if (prevSub) {
    assignmentNotes.value = prevSub.submission_text || ''
  } else {
    assignmentNotes.value = ''
  }
  showAssignmentModal.value = true
}

const validateAndSetFile = (file: File) => {
  fileErrorMsg.value = ''
  const allowedExts = ['pdf', 'doc', 'docx']
  const ext = file.name.split('.').pop()?.toLowerCase() || ''

  if (!allowedExts.includes(ext)) {
    fileErrorMsg.value = `Format file .${ext.toUpperCase()} tidak didukung! Format yang diterima hanya PDF, DOC, atau DOCX.`
    assignmentFile.value = null
    return false
  }

  // Maksimal 10 MB = 10 * 1024 * 1024 bytes (10.485.760 bytes)
  const maxBytes = 10 * 1024 * 1024
  if (file.size > maxBytes) {
    const sizeMb = (file.size / (1024 * 1024)).toFixed(2)
    fileErrorMsg.value = `Ukuran file (${sizeMb} MB) melebihi batas maksimal 10 MB!`
    assignmentFile.value = null
    return false
  }

  assignmentFile.value = file
  return true
}

const handleFileChange = (e: Event) => {
  const input = e.target as HTMLInputElement
  if (input.files && input.files[0]) {
    validateAndSetFile(input.files[0])
  }
}

const handleFileDrop = (e: DragEvent) => {
  isDraggingFile.value = false
  if (e.dataTransfer?.files && e.dataTransfer.files[0]) {
    validateAndSetFile(e.dataTransfer.files[0])
  }
}

const removeSelectedFile = () => {
  assignmentFile.value = null
  fileErrorMsg.value = ''
}

const formatFileSize = (bytes?: number) => {
  if (!bytes) return '0 B'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB'
}

const submitAssignment = async () => {
  if (!selectedAssignment.value) return
  fileErrorMsg.value = ''

  // Jika belum ada berkas lama dan belum memilih berkas baru
  if (!assignmentFile.value && !currentAssignmentSubmission.value?.file_path) {
    fileErrorMsg.value = 'Silakan pilih berkas tugas (PDF/DOC/DOCX) terlebih dahulu!'
    return
  }

  submittingAssignment.value = true
  assignmentSuccessMsg.value = ''

  try {
    const formData = new FormData()
    formData.append('quiz_id', selectedAssignment.value.id)
    if (assignmentNotes.value) {
      formData.append('submission_text', assignmentNotes.value)
      formData.append('notes', assignmentNotes.value)
    }
    if (assignmentFile.value) {
      formData.append('file', assignmentFile.value)
    }

    await $fetch(`${apiBase}/quizzes/submit`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: formData
    })

    assignmentSuccessMsg.value = 'Tugas Mandiri berhasil dikumpulkan!'
    setTimeout(() => {
      showAssignmentModal.value = false
      fetchCourseData()
    }, 1200)
  } catch (err: any) {
    fileErrorMsg.value = err?.data?.message || err?.message || 'Gagal mengumpulkan tugas. Silakan periksa kembali file Anda.'
  } finally {
    submittingAssignment.value = false
  }
}

onMounted(() => {
  fetchCourseData()
})
</script>

<template>
  <div class="flex min-h-[calc(100vh-64px)] w-full bg-slate-50/50">
    <!-- 1. LEFT SIDEBAR: LIST PERTEMUAN (IDENTICAL TO DASHBOARD COURSES LIST) -->
    <aside
      v-show="isSidebarOpen"
      class="w-64 sm:w-72 border-r border-slate-200 bg-white shrink-0 p-4 space-y-4 flex flex-col justify-between transition-all"
    >
      <div class="space-y-3">
        <!-- Sidebar Header Title matching Dashboard Courses List -->
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">
          Courses List
        </h2>

        <!-- Search Input Sidebar identical to dashboard -->
        <div class="relative">
          <input
            v-model="sidebarSearch"
            type="text"
            placeholder="Pencarian"
            class="w-full pl-8 pr-3 py-2 text-xs border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:border-blue-600 transition font-medium text-slate-800 placeholder-slate-400"
          />
          <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <!-- Vertical List of Meetings matching dashboard layout -->
        <div class="space-y-1 overflow-y-auto max-h-[calc(100vh-210px)] pr-1">
          <div v-if="loading" class="space-y-2 py-2">
            <AppSkeleton v-for="i in 5" :key="i" height="h-12" class="rounded-xl" />
          </div>

          <div
            v-else-if="sidebarFilteredMeets.length === 0"
            class="py-6 text-center text-xs text-slate-400 font-medium"
          >
            Pertemuan tidak ditemukan
          </div>

          <div
            v-else
            v-for="meet in sidebarFilteredMeets"
            :key="meet.id"
            class="rounded-xl border transition overflow-hidden"
            :class="activeMeetId === meet.id ? 'bg-blue-50/40 border-blue-200' : 'bg-white border-transparent hover:border-slate-200 hover:bg-slate-50/70'"
          >
            <!-- Meeting Header Toggle (Click to inspect/expand & select) -->
            <button
              type="button"
              @click="toggleSidebarMeet(meet)"
              class="w-full p-2.5 flex items-center justify-between text-left transition group"
            >
              <div class="min-w-0 pr-1">
                <div
                  class="text-xs font-bold uppercase tracking-tight truncate group-hover:text-blue-800"
                  :class="activeMeetId === meet.id ? 'text-[#1565c0]' : 'text-slate-800'"
                >
                  PERTEMUAN {{ meet.meeting_number }}
                </div>
                <div class="text-[11px] text-slate-500 font-medium mt-0.5 truncate">
                  {{ meet.title }}
                </div>
              </div>
              <div class="shrink-0 flex items-center gap-1.5">
                <svg
                  class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200"
                  :class="expandedSidebarMeets[meet.id] ? 'rotate-180 text-[#1565c0]' : ''"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </button>

            <!-- Dropdown 6 Aktivitas Belajar (Tanpa Angka, Outline Hijau jika Selesai) -->
            <div
              v-show="expandedSidebarMeets[meet.id]"
              class="px-2 pb-2 pt-1 border-t border-slate-100 space-y-1 bg-slate-50/60"
            >
              <button
                v-for="item in getActivityMenuItems(meet)"
                :key="item.key"
                type="button"
                @click="selectSidebarActivity(meet, item.key)"
                class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs font-medium transition text-left group/act"
                :class="[
                  item.isDone
                    ? 'border-2 border-emerald-500 bg-emerald-50/60 text-emerald-950 font-bold shadow-2xs'
                    : (activeMeetId === meet.id && activeActivityKey === item.key
                        ? 'bg-white font-bold text-[#1565c0] shadow-2xs border border-blue-200/80'
                        : 'text-slate-700 hover:text-blue-700 hover:bg-white border border-transparent')
                ]"
              >
                <span class="flex items-center gap-2 truncate">
                  <!-- Ikon Aktivitas Khusus (Tanpa Angka) -->
                  <span
                    class="w-5 h-5 rounded-md flex items-center justify-center shrink-0 transition"
                    :class="[
                      item.isDone
                        ? 'bg-emerald-100 text-emerald-700'
                        : (activeMeetId === meet.id && activeActivityKey === item.key
                            ? 'bg-[#1565c0] text-white'
                            : 'bg-slate-200/80 text-slate-600 group-hover/act:bg-blue-100 group-hover/act:text-blue-800')
                    ]"
                  >
                    <!-- Pretest -->
                    <svg v-if="item.key === 'pretest'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <!-- Modul & Bahan Ajar -->
                    <svg v-else-if="item.key === 'material'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <!-- Ruang Diskusi -->
                    <svg v-else-if="item.key === 'discussion'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <!-- Kuis Formatif -->
                    <svg v-else-if="item.key === 'quiz'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    <!-- Tugas Mandiri -->
                    <svg v-else-if="item.key === 'assignment'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <!-- Posttest -->
                    <svg v-else-if="item.key === 'posttest'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                  </span>
                  <span class="truncate">{{ item.label }}</span>
                </span>
                
                <!-- Indikator Centang Hijau saat Sudah Dikerjakan -->
                <span
                  v-if="item.isDone"
                  class="w-4 h-4 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] font-black shrink-0 shadow-2xs"
                  title="Aktivitas telah selesai dikerjakan"
                >
                  ✓
                </span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom of Sidebar: Navigasi Kembali ke Dashboard -->
      <div class="pt-3 border-t border-slate-100">
        <NuxtLink
          to="/student/dashboard"
          class="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border border-slate-200 hover:border-slate-300 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold transition shadow-2xs group"
        >
          <span class="group-hover:-translate-x-0.5 transition-transform">←</span>
          <span>Kembali ke Dashboard</span>
        </NuxtLink>
      </div>
    </aside>

    <!-- 2. RIGHT MAIN CONTENT AREA (IDENTICAL TO DASHBOARD MAIN AREA) -->
    <div class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 overflow-x-hidden">
      <!-- A. TOP BANNER CARD (EXACTLY MATCHING DASHBOARD BANNER IN SCREENSHOT) -->
      <div class="bg-[#dce9f8] rounded-2xl p-6 sm:p-7 border border-blue-200/60 flex items-center justify-between shadow-xs">
        <div>
          <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight uppercase">
            {{ subjectName }}
          </h1>
          <p class="text-xs text-slate-500 font-medium mt-1">
            Dashboard Belajar Siswa • Kelas {{ className }} • {{ teacherName }} • {{ meets.length }} Pertemuan
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

      <!-- B. SEARCH & FILTER CONTROLS (MATCHING DASHBOARD SCREENSHOT) -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
        <!-- Main Search Input -->
        <div class="relative flex-1">
          <input
            v-model="mainSearch"
            type="text"
            placeholder="Pencarian materi pertemuan atau aktivitas..."
            class="w-full pl-9 pr-4 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition shadow-xs"
          />
          <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <!-- Semester Select with Floating Label -->
        <div class="relative w-full sm:w-72">
          <label class="absolute -top-2 left-3 bg-white px-1 text-[10px] font-semibold text-slate-500 z-10">
            Semester
          </label>
          <div class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-800 shadow-xs flex items-center justify-between">
            <span>Semester GANJIL 2026/2027</span>
          </div>
        </div>
      </div>

      <!-- C. ACCORDION CONTROL TOGGLE BUTTONS -->
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-slate-800">
          Aktivitas & Materi Pertemuan
        </h2>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="expandAllDropdowns"
            class="px-3.5 py-1.5 rounded-full border border-blue-300 bg-white hover:bg-blue-50 text-blue-600 text-xs font-semibold transition shadow-xs"
          >
            Buka Semua Pertemuan
          </button>
          <button
            type="button"
            @click="collapseAllDropdowns"
            class="px-3.5 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 text-xs font-semibold transition shadow-xs"
          >
            Tutup Semua
          </button>
        </div>
      </div>

      <!-- D. CONTENT LIST PERTEMUAN DENGAN CONTENT DROPDOWN ITEM -->
      <div class="space-y-4">
        <!-- Loading Skeleton -->
        <div v-if="loading" class="space-y-4">
          <AppSkeleton v-for="i in 3" :key="i" height="h-28" class="rounded-2xl" />
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredMainMeets.length === 0">
          <AppEmptyState
            title="Tidak ada pertemuan"
            description="Tidak ditemukan materi pertemuan yang sesuai dengan pencarian Anda."
          />
        </div>

        <!-- DROPDOWN ITEM PER PERTEMUAN -->
        <div
          v-else
          v-for="meet in filteredMainMeets"
          :key="meet.id"
          :id="`meet-item-${meet.id}`"
          class="space-y-0"
        >
          <!-- DROPDOWN ITEM HEADER BAR -->
          <div
            @click="toggleDropdown(meet.id)"
            class="w-full bg-white border border-slate-200 rounded-2xl p-4 sm:p-5 flex items-center justify-between cursor-pointer hover:border-blue-400 hover:shadow-xs transition"
            :class="openDropdowns[meet.id] ? 'rounded-b-none border-b-0 bg-blue-50/20' : ''"
          >
            <div class="flex items-center gap-3.5">
              <span class="w-10 h-10 rounded-xl bg-blue-50 text-[#1565c0] font-black text-sm flex items-center justify-center shrink-0 border border-blue-100 shadow-2xs">
                P{{ meet.meeting_number }}
              </span>
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="font-extrabold text-slate-900 text-sm sm:text-base">
                    PERTEMUAN {{ meet.meeting_number }}: {{ meet.title }}
                  </h3>
                  <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                    :class="meet.type === 'online' ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-slate-100 text-slate-600'"
                  >
                    {{ meet.type === 'online' ? 'Online VCON' : 'Tatap Muka' }}
                  </span>
                </div>
                <p class="text-xs text-slate-500 font-medium mt-0.5">
                  {{ meet.start_time ? formatTimeNice(meet.start_time) : 'Jadwal Reguler KBM' }}
                </p>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <!-- Label sinkron aktivitas sebenarnya -->
              <span
                class="hidden sm:inline-block text-[11px] font-bold px-2.5 py-1 rounded-full transition"
                :class="countMeetActivities(meet) > 0 ? 'bg-blue-50 text-[#1565c0] border border-blue-100' : 'bg-slate-100 text-slate-500'"
              >
                {{ countMeetActivities(meet) > 0 ? `${countMeetActivities(meet)} Aktivitas Belajar` : 'Belum Ada Konten' }}
              </span>

              <!-- Chevron indicator rotates on toggle -->
              <div
                class="w-8 h-8 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-500 transition-transform duration-200"
                :class="openDropdowns[meet.id] ? 'rotate-180 text-blue-600 bg-blue-50 border-blue-200' : ''"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </div>
          </div>

          <!-- DROPDOWN ITEM CONTENT BODY (VISIBLE WHEN EXPANDED) -->
          <div
            v-show="openDropdowns[meet.id]"
            class="bg-white border-x border-b border-slate-200 rounded-b-2xl p-5 sm:p-6 space-y-6 shadow-xs"
          >
            <!-- Deskripsi Pertemuan / Capaian Pembelajaran -->
            <div v-if="meet.description" class="p-4 bg-slate-50/80 rounded-xl border border-slate-100 text-xs text-slate-700 space-y-1">
              <span class="font-bold text-slate-900 block text-[11px] uppercase tracking-wider">Capaian & Petunjuk Belajar:</span>
              <p class="leading-relaxed">{{ meet.description }}</p>
            </div>

            <!-- JIKA BELUM ADA KONTEN SAMA SEKALI: TAMPILKAN DEFAULT TEXT -->
            <div
              v-if="countMeetActivities(meet) === 0"
              class="py-8 px-4 text-center bg-slate-50/70 rounded-2xl border border-dashed border-slate-200 space-y-1.5"
            >
              <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-base">
                📖
              </div>
              <p class="text-xs font-bold text-slate-600">
                Belum ada konten aktivitas untuk pertemuan ini.
              </p>
              <p class="text-[11px] text-slate-400 font-medium">
                Guru pengampu belum mengunggah modul ajar, kuis, atau penugasan pada sesi pertemuan ini.
              </p>
            </div>

            <!-- JIKA ADA KONTEN: TAMPILKAN ALUR BELAJAR TERSTRUKTUR (PRETEST, MODUL, DISKUSI, KUIS, TUGAS, POSTTEST) -->
            <div v-else class="space-y-4">
              <!-- 1. PRETEST (Asesmen Diagnostik Awal) -->
              <div
                :id="`meet-${meet.id}-pretest`"
                class="border rounded-2xl p-5 bg-white space-y-3.5 shadow-xs transition"
                :class="isActivityDone(meet, 'pretest')
                  ? 'border-2 border-emerald-500 shadow-md ring-2 ring-emerald-100/80 bg-emerald-50/15'
                  : (activeMeetId === meet.id && activeActivityKey === 'pretest' ? 'border-blue-400 ring-2 ring-blue-100' : 'border-slate-200')"
              >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                  <span class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2.5">
                    <span
                      class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs border transition"
                      :class="isActivityDone(meet, 'pretest') ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-[#1565c0] border-blue-100'"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                    </span>
                    <span>Pretest (Asesmen Diagnostik Awal)</span>
                  </span>
                  <div class="flex items-center gap-2">
                    <span
                      v-if="isActivityDone(meet, 'pretest')"
                      class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1 shadow-2xs"
                    >
                      <span>✓</span>
                      <span>Sudah Dikerjakan</span>
                    </span>
                    <span
                      v-else-if="getMeetPretests(meet).length > 0"
                      class="text-[10px] font-bold text-blue-700 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded-full"
                    >
                      Tersedia
                    </span>
                  </div>
                </div>

                <!-- Default text jika belum ada konten -->
                <div v-if="getMeetPretests(meet).length === 0" class="text-xs text-slate-400 py-1">
                  Belum ada tes diagnostik awal untuk pertemuan ini.
                </div>
                <div v-else class="space-y-2">
                  <div
                    v-for="pre in getMeetPretests(meet)"
                    :key="pre.id"
                    class="p-3.5 rounded-xl border flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition"
                    :class="isQuizCompleted(pre) ? 'border-2 border-emerald-500 ring-1 ring-emerald-200/60 bg-emerald-50/25' : 'bg-slate-50/70 border-slate-200/80'"
                  >
                    <div>
                      <div class="font-bold text-slate-800 text-xs flex items-center gap-2">
                        <span>{{ pre.title }}</span>
                        <span v-if="isQuizCompleted(pre)" class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                          ✓ Selesai
                        </span>
                      </div>
                      <div class="text-[11px] text-slate-500 font-medium mt-0.5">
                        {{ pre.questions_count || 10 }} Soal • Durasi {{ pre.duration_minutes || 15 }} Menit • {{ getTimeWindowStatus(pre).info }}
                      </div>
                    </div>

                    <div class="flex items-center gap-2">
                      <NuxtLink
                        v-if="isQuizCompleted(pre)"
                        :to="`/student/quizzes/${pre.id}/onboarding`"
                        class="text-xs font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-lg border border-emerald-200 transition shadow-2xs flex items-center gap-1"
                      >
                        <span>✓ Nilai: {{ getQuizSubmission(pre)?.score !== null ? getQuizSubmission(pre)?.score : 'Terekam' }}</span>
                        <span class="text-[11px] underline ml-1">Detail</span>
                      </NuxtLink>
                      <NuxtLink
                        v-else-if="getTimeWindowStatus(pre).isOpen"
                        :to="`/student/quizzes/${pre.id}/onboarding`"
                        class="px-3.5 py-1.5 bg-[#1565c0] hover:bg-blue-700 text-white rounded-lg font-bold text-xs transition shadow-2xs flex items-center gap-1.5"
                      >
                        <span>Mulai Pretest</span>
                        <span>▶</span>
                      </NuxtLink>
                      <span v-else class="text-[11px] font-semibold text-slate-400 bg-slate-100 px-3 py-1.5 rounded-lg">
                        Akses Terkunci
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 2. MODUL AJAR & BAHAN AJAR -->
              <div
                :id="`meet-${meet.id}-material`"
                class="border rounded-2xl p-5 bg-white space-y-3.5 shadow-xs transition"
                :class="isActivityDone(meet, 'material')
                  ? 'border-2 border-emerald-500 shadow-md ring-2 ring-emerald-100/80 bg-emerald-50/15'
                  : (activeMeetId === meet.id && activeActivityKey === 'material' ? 'border-blue-400 ring-2 ring-blue-100' : 'border-slate-200')"
              >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                  <span class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2.5">
                    <span
                      class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs border transition"
                      :class="isActivityDone(meet, 'material') ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-[#1565c0] border-blue-100'"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                      </svg>
                    </span>
                    <span>Modul & Bahan Ajar Guru</span>
                  </span>
                  <div class="flex items-center gap-2">
                    <span
                      v-if="isActivityDone(meet, 'material')"
                      class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1 shadow-2xs"
                    >
                      <span>✓</span>
                      <span>Modul Sudah Diunduh</span>
                    </span>
                    <span
                      v-else-if="getMeetMaterials(meet).length > 0"
                      class="text-[10px] font-bold text-blue-700 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded-full"
                    >
                      Tersedia
                    </span>
                  </div>
                </div>

                <!-- Default text jika belum ada materi -->
                <div v-if="getMeetMaterials(meet).length === 0" class="text-xs text-slate-400 py-1">
                  Belum ada dokumen bahan ajar yang diunggah untuk pertemuan ini.
                </div>
                <div v-else class="space-y-2">
                  <div
                    v-for="mat in getMeetMaterials(meet)"
                    :key="mat.id"
                    class="p-3.5 rounded-xl border flex items-center justify-between gap-3 transition"
                    :class="isMaterialDownloaded(mat.id) ? 'border-2 border-emerald-500 ring-1 ring-emerald-200/60 bg-emerald-50/25' : 'bg-slate-50/70 border-slate-200/80'"
                  >
                    <div class="flex items-center gap-2.5 truncate">
                      <span class="text-base" :class="isMaterialDownloaded(mat.id) ? 'text-emerald-600' : 'text-[#1565c0]'">
                        {{ isMaterialDownloaded(mat.id) ? '✓' : '📄' }}
                      </span>
                      <div class="truncate">
                        <div class="font-bold text-slate-800 text-xs truncate flex items-center gap-2">
                          <span class="truncate">{{ mat.title }}</span>
                          <span
                            v-if="isMaterialDownloaded(mat.id)"
                            class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 shrink-0"
                          >
                            Sudah Diunduh
                          </span>
                        </div>
                        <div class="text-[11px] text-slate-500 font-medium truncate mt-0.5">
                          {{ mat.type || 'Dokumen' }} • {{ mat.content || 'Materi Pembelajaran' }}
                        </div>
                      </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <a
                        v-if="mat.file_url"
                        :href="mat.file_url"
                        target="_blank"
                        download
                        @click="markMaterialDownloaded(mat.id)"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1 shrink-0 shadow-2xs cursor-pointer"
                        :class="isMaterialDownloaded(mat.id) ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-slate-700 hover:bg-slate-800 text-white'"
                      >
                        <span>{{ isMaterialDownloaded(mat.id) ? '✓ Unduh Ulang' : 'Unduh Modul' }}</span>
                        <span>↓</span>
                      </a>
                      <NuxtLink
                        :to="`/student/materials/${mat.id}`"
                        @click="markMaterialDownloaded(mat.id)"
                        class="px-3.5 py-1.5 bg-[#1565c0] hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1 shrink-0 shadow-2xs"
                      >
                        <span>Buka Modul</span>
                        <span>➔</span>
                      </NuxtLink>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 3. FORUM DISKUSI -->
              <div
                :id="`meet-${meet.id}-discussion`"
                class="border rounded-2xl p-5 bg-white space-y-3.5 shadow-xs transition"
                :class="isActivityDone(meet, 'discussion')
                  ? 'border-2 border-emerald-500 shadow-md ring-2 ring-emerald-100/80 bg-emerald-50/15'
                  : (activeMeetId === meet.id && activeActivityKey === 'discussion' ? 'border-blue-400 ring-2 ring-blue-100' : 'border-slate-200')"
              >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                  <span class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2.5">
                    <span
                      class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs border transition"
                      :class="isActivityDone(meet, 'discussion') ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-[#1565c0] border-blue-100'"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                      </svg>
                    </span>
                    <span>Ruang Diskusi & Tanya Jawab</span>
                  </span>
                  <div class="flex items-center gap-2">
                    <span
                      v-if="isActivityDone(meet, 'discussion')"
                      class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1 shadow-2xs"
                    >
                      <span>✓</span>
                      <span>Sudah Berpartisipasi</span>
                    </span>
                    <NuxtLink
                      :to="`/student/discussions/${meet.id}`"
                      class="px-2.5 py-1 rounded-md bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-[#1565c0] text-[11px] font-semibold transition flex items-center gap-1 border border-slate-200"
                    >
                      <span>Buka Halaman Diskusi</span>
                      <span>↗</span>
                    </NuxtLink>
                  </div>
                </div>

                <!-- Discussion stream -->
                <div class="max-h-60 overflow-y-auto space-y-2 pr-1">
                  <!-- Default text jika belum ada diskusi -->
                  <div v-if="getMeetDiscussions(meet).length === 0" class="text-xs text-slate-400 py-2 text-center">
                    Belum ada tanggapan dalam forum diskusi ini. Tulis pertanyaan Anda di bawah.
                  </div>
                  <div
                    v-else
                    v-for="disc in getMeetDiscussions(meet)"
                    :key="disc.id"
                    class="p-3 rounded-xl border text-xs"
                    :class="disc.teacher || disc.user?.role === 'teacher' ? 'bg-blue-50/40 border-blue-200' : 'bg-slate-50/70 border-slate-200/80'"
                  >
                    <div class="flex items-center justify-between text-[11px] font-bold mb-1">
                      <span :class="disc.teacher || disc.user?.role === 'teacher' ? 'text-blue-900 font-extrabold' : 'text-slate-800'">
                        {{ disc.student?.name || disc.teacher?.name || disc.user?.name || 'Siswa' }}
                        <span v-if="disc.teacher || disc.user?.role === 'teacher'" class="ml-1.5 px-1.5 py-0.5 bg-blue-100 text-blue-800 rounded text-[10px]">Guru</span>
                      </span>
                      <span class="text-slate-400 font-normal">
                        {{ formatTimeNice(disc.created_at) }}
                      </span>
                    </div>
                    <p class="text-slate-700 leading-relaxed font-normal">
                      {{ disc.message }}
                    </p>
                  </div>
                </div>

                <!-- Live Comment Input Box -->
                <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                  <input
                    v-model="discussionInputs[meet.id]"
                    type="text"
                    placeholder="Tulis pertanyaan atau tanggapan diskusi..."
                    @keyup.enter="handleSendDiscussion(meet.id)"
                    class="flex-1 px-3.5 py-2 text-xs border border-slate-200 rounded-xl bg-slate-50/50 focus:bg-white focus:outline-none focus:border-[#1565c0] font-medium text-slate-800"
                  />
                  <button
                    type="button"
                    :disabled="sendingDiscussion === meet.id || !(discussionInputs[meet.id] || '').trim()"
                    @click="handleSendDiscussion(meet.id)"
                    class="px-4 py-2 bg-[#1565c0] hover:bg-blue-700 disabled:opacity-40 text-white rounded-xl text-xs font-bold transition shrink-0 shadow-2xs"
                  >
                    Kirim
                  </button>
                </div>
              </div>

              <!-- 4. KUIS FORMATIF -->
              <div
                :id="`meet-${meet.id}-quiz`"
                class="border rounded-2xl p-5 bg-white space-y-3.5 shadow-xs transition"
                :class="isActivityDone(meet, 'quiz')
                  ? 'border-2 border-emerald-500 shadow-md ring-2 ring-emerald-100/80 bg-emerald-50/15'
                  : (activeMeetId === meet.id && activeActivityKey === 'quiz' ? 'border-blue-400 ring-2 ring-blue-100' : 'border-slate-200')"
              >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                  <span class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2.5">
                    <span
                      class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs border transition"
                      :class="isActivityDone(meet, 'quiz') ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-[#1565c0] border-blue-100'"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                      </svg>
                    </span>
                    <span>Kuis Formatif (Pemahaman)</span>
                  </span>
                  <div class="flex items-center gap-2">
                    <span
                      v-if="isActivityDone(meet, 'quiz')"
                      class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1 shadow-2xs"
                    >
                      <span>✓</span>
                      <span>Sudah Dikerjakan</span>
                    </span>
                    <span
                      v-else-if="getMeetQuizzes(meet).length > 0"
                      class="text-[10px] font-bold text-blue-700 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded-full"
                    >
                      Tersedia
                    </span>
                  </div>
                </div>

                <!-- Default text jika belum ada kuis formatif -->
                <div v-if="getMeetQuizzes(meet).length === 0" class="text-xs text-slate-400 py-1">
                  Belum ada kuis formatif untuk pertemuan ini.
                </div>
                <div v-else class="space-y-2">
                  <div
                    v-for="quiz in getMeetQuizzes(meet)"
                    :key="quiz.id"
                    class="p-3.5 rounded-xl border flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition"
                    :class="isQuizCompleted(quiz) ? 'border-2 border-emerald-500 ring-1 ring-emerald-200/60 bg-emerald-50/25' : 'bg-slate-50/70 border-slate-200/80'"
                  >
                    <div>
                      <div class="font-bold text-slate-800 text-xs flex items-center gap-2">
                        <span>{{ quiz.title }}</span>
                        <span v-if="isQuizCompleted(quiz)" class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                          ✓ Selesai
                        </span>
                      </div>
                      <div class="text-[11px] text-slate-500 font-medium mt-0.5">
                        {{ quiz.questions_count || 10 }} Soal • Durasi {{ quiz.duration_minutes || 20 }} Menit • {{ getTimeWindowStatus(quiz).info }}
                      </div>
                    </div>

                    <div class="flex items-center gap-2">
                      <NuxtLink
                        v-if="isQuizCompleted(quiz)"
                        :to="`/student/quizzes/${quiz.id}/onboarding`"
                        class="text-xs font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-lg border border-emerald-200 transition shadow-2xs flex items-center gap-1"
                      >
                        <span>✓ Nilai: {{ getQuizSubmission(quiz)?.score !== null ? getQuizSubmission(quiz)?.score : 'Terekam' }}</span>
                        <span class="text-[11px] underline ml-1">Detail</span>
                      </NuxtLink>
                      <NuxtLink
                        v-else-if="getTimeWindowStatus(quiz).isOpen"
                        :to="`/student/quizzes/${quiz.id}/onboarding`"
                        class="px-3.5 py-1.5 bg-[#1565c0] hover:bg-blue-700 text-white rounded-lg font-bold text-xs transition shadow-2xs flex items-center gap-1.5"
                      >
                        <span>Kerjakan Kuis</span>
                        <span>▶</span>
                      </NuxtLink>
                      <span v-else class="text-[11px] font-semibold text-slate-400 bg-slate-100 px-3 py-1.5 rounded-lg">
                        Kuis Belum Dibuka
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 5. TUGAS MANDIRI -->
              <div
                :id="`meet-${meet.id}-assignment`"
                class="border rounded-2xl p-5 bg-white space-y-3.5 shadow-xs transition"
                :class="isActivityDone(meet, 'assignment')
                  ? 'border-2 border-emerald-500 shadow-md ring-2 ring-emerald-100/80 bg-emerald-50/15'
                  : (activeMeetId === meet.id && activeActivityKey === 'assignment' ? 'border-blue-400 ring-2 ring-blue-100' : 'border-slate-200')"
              >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                  <span class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2.5">
                    <span
                      class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs border transition"
                      :class="isActivityDone(meet, 'assignment') ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-[#1565c0] border-blue-100'"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                      </svg>
                    </span>
                    <span>Tugas Mandiri (Pengumpulan File)</span>
                  </span>
                  <div class="flex items-center gap-2">
                    <span
                      v-if="isActivityDone(meet, 'assignment')"
                      class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1 shadow-2xs"
                    >
                      <span>✓</span>
                      <span>Sudah Dikumpulkan</span>
                    </span>
                    <span
                      v-else-if="getMeetAssignments(meet).length > 0"
                      class="text-[10px] font-bold text-blue-700 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded-full"
                    >
                      Tersedia
                    </span>
                  </div>
                </div>

                <!-- Default text jika belum ada tugas -->
                <div v-if="getMeetAssignments(meet).length === 0" class="text-xs text-slate-400 py-1">
                  Tidak ada penugasan mandiri pada pertemuan ini.
                </div>
                <div v-else class="space-y-2">
                  <div
                    v-for="asg in getMeetAssignments(meet)"
                    :key="asg.id"
                    class="p-3.5 rounded-xl border flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition"
                    :class="isQuizCompleted(asg) ? 'border-2 border-emerald-500 ring-1 ring-emerald-200/60 bg-emerald-50/25' : 'bg-slate-50/70 border-slate-200/80'"
                  >
                    <div>
                      <div class="font-bold text-slate-800 text-xs flex items-center gap-2">
                        <span>{{ asg.title }}</span>
                        <span v-if="isQuizCompleted(asg)" class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                          ✓ Terkumpul
                        </span>
                      </div>
                      <div class="text-[11px] text-slate-500 font-medium mt-0.5">
                        Tenggat: {{ asg.end_time ? formatTimeNice(asg.end_time) : (asg.due_date ? formatTimeNice(asg.due_date) : 'Sesuai Jadwal') }}
                      </div>
                    </div>

                    <div class="flex items-center gap-2">
                      <NuxtLink
                        :to="`/student/assignments/${asg.id}`"
                        class="px-3.5 py-1.5 rounded-lg font-bold text-xs transition shadow-2xs flex items-center gap-1.5"
                        :class="isQuizCompleted(asg) ? 'bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-[#1565c0] hover:bg-blue-700 text-white'"
                      >
                        <span v-if="isQuizCompleted(asg)">✓ Terkumpul (Detail)</span>
                        <span v-else>Kumpulkan Tugas</span>
                        <span>➔</span>
                      </NuxtLink>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 6. POSTTEST (Asesmen Sumatif Pertemuan) -->
              <div
                :id="`meet-${meet.id}-posttest`"
                class="border rounded-2xl p-5 bg-white space-y-3.5 shadow-xs transition"
                :class="isActivityDone(meet, 'posttest')
                  ? 'border-2 border-emerald-500 shadow-md ring-2 ring-emerald-100/80 bg-emerald-50/15'
                  : (activeMeetId === meet.id && activeActivityKey === 'posttest' ? 'border-blue-400 ring-2 ring-blue-100' : 'border-slate-200')"
              >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                  <span class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2.5">
                    <span
                      class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs border transition"
                      :class="isActivityDone(meet, 'posttest') ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-[#1565c0] border-blue-100'"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                      </svg>
                    </span>
                    <span>Posttest (Asesmen Sumatif Pertemuan)</span>
                  </span>
                  <div class="flex items-center gap-2">
                    <span
                      v-if="isActivityDone(meet, 'posttest')"
                      class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1 shadow-2xs"
                    >
                      <span>✓</span>
                      <span>Sudah Dikerjakan</span>
                    </span>
                    <span
                      v-else-if="getMeetPosttests(meet).length > 0"
                      class="text-[10px] font-bold text-blue-700 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded-full"
                    >
                      Tersedia
                    </span>
                  </div>
                </div>

                <!-- Default text jika belum ada posttest -->
                <div v-if="getMeetPosttests(meet).length === 0" class="text-xs text-slate-400 py-1">
                  Belum ada tes sumatif akhir untuk pertemuan ini.
                </div>
                <div v-else class="space-y-2">
                  <div
                    v-for="post in getMeetPosttests(meet)"
                    :key="post.id"
                    class="p-3.5 rounded-xl border flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition"
                    :class="isQuizCompleted(post) ? 'border-2 border-emerald-500 ring-1 ring-emerald-200/60 bg-emerald-50/25' : 'bg-slate-50/70 border-slate-200/80'"
                  >
                    <div>
                      <div class="font-bold text-slate-800 text-xs flex items-center gap-2">
                        <span>{{ post.title }}</span>
                        <span v-if="isQuizCompleted(post)" class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                          ✓ Selesai
                        </span>
                      </div>
                      <div class="text-[11px] text-slate-500 font-medium mt-0.5">
                        {{ post.questions_count || 10 }} Soal • Durasi {{ post.duration_minutes || 20 }} Menit • {{ getTimeWindowStatus(post).info }}
                      </div>
                    </div>

                    <div class="flex items-center gap-2">
                      <NuxtLink
                        v-if="isQuizCompleted(post)"
                        :to="`/student/quizzes/${post.id}/onboarding`"
                        class="text-xs font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-lg border border-emerald-200 transition shadow-2xs flex items-center gap-1"
                      >
                        <span>✓ Nilai: {{ getQuizSubmission(post)?.score !== null ? getQuizSubmission(post)?.score : 'Terekam' }}</span>
                        <span class="text-[11px] underline ml-1">Detail</span>
                      </NuxtLink>
                      <NuxtLink
                        v-else-if="getTimeWindowStatus(post).isOpen"
                        :to="`/student/quizzes/${post.id}/onboarding`"
                        class="px-3.5 py-1.5 bg-[#1565c0] hover:bg-blue-700 text-white rounded-lg font-bold text-xs transition shadow-2xs flex items-center gap-1.5"
                      >
                        <span>Mulai Posttest</span>
                        <span>▶</span>
                      </NuxtLink>
                      <span v-else class="text-[11px] font-semibold text-slate-400 bg-slate-100 px-3 py-1.5 rounded-lg">
                        Akses Terkunci
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL PENGUMPULAN TUGAS MANDIRI SISWA -->
    <div
      v-if="showAssignmentModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in"
      @click.self="showAssignmentModal = false"
    >
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-7 space-y-5 shadow-2xl border border-slate-200 overflow-hidden max-h-[90vh] flex flex-col">
        <!-- Header Modal -->
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 shrink-0">
          <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-2xl bg-sky-50 text-sky-700 flex items-center justify-center text-lg font-bold border border-sky-100 shadow-2xs">📂</span>
            <div>
              <span class="inline-block text-[10px] font-extrabold uppercase tracking-wider text-sky-600 bg-sky-50 px-2 py-0.5 rounded-md mb-0.5">
                PENGUMPULAN TUGAS MANDIRI
              </span>
              <h3 class="font-extrabold text-slate-900 text-sm">Form Pengumpulan File Siswa</h3>
            </div>
          </div>
          <button
            type="button"
            @click="showAssignmentModal = false"
            class="text-slate-400 hover:text-slate-700 p-2 rounded-xl hover:bg-slate-100 transition"
          >
            ✕
          </button>
        </div>

        <!-- Success Alert -->
        <div v-if="assignmentSuccessMsg" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-900 rounded-2xl text-xs font-bold text-center flex items-center justify-center gap-2">
          <span class="text-base">✓</span>
          <span>{{ assignmentSuccessMsg }}</span>
        </div>

        <!-- Form Body (Scrollable) -->
        <form v-else @submit.prevent="submitAssignment" class="space-y-4 overflow-y-auto pr-1 flex-1">
          <!-- 1. Judul Tugas & Instruksi yang Diberikan Guru -->
          <div class="space-y-2">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1 flex items-center justify-between">
                <span>Judul Tugas Mandiri</span>
                <span class="text-[10px] font-semibold text-slate-400">
                  Pertemuan {{ selectedAssignment?.meet?.meeting_number || selectedAssignment?.meet_id || '-' }}
                </span>
              </label>
              <input
                type="text"
                :value="selectedAssignment?.title"
                readonly
                class="w-full px-3.5 py-2.5 text-xs font-bold text-slate-800 bg-slate-50 border border-slate-200 rounded-xl cursor-default focus:outline-none"
              />
            </div>
            
            <!-- INSTRUKSI DARI GURU (Porsi Utama & Jelas) -->
            <div class="p-3.5 bg-blue-50/60 border border-blue-100 rounded-2xl space-y-1.5">
              <div class="flex items-center justify-between">
                <span class="text-xs font-extrabold text-blue-900 flex items-center gap-1.5">
                  <span>📋</span>
                  <span>Instruksi & Petunjuk dari Guru</span>
                </span>
                <span class="text-[10px] font-bold text-blue-700 bg-blue-100/70 px-2 py-0.5 rounded-md">
                  Wajib Dibaca
                </span>
              </div>
              <p class="text-xs text-slate-700 leading-relaxed font-medium whitespace-pre-wrap">
                {{ selectedAssignment?.instructions || 'Ikuti instruksi pengerjaan yang diberikan oleh guru pengampu dan unggah file tugas Anda sebelum batas waktu berakhir.' }}
              </p>
              <div class="pt-1.5 border-t border-blue-100 flex flex-wrap items-center justify-between gap-2 text-[10px] text-slate-500 font-medium">
                <span>⏱ Batas Pengumpulan: <b>{{ selectedAssignment?.end_time ? formatTimeNice(selectedAssignment.end_time) : (selectedAssignment?.due_date ? formatTimeNice(selectedAssignment.due_date) : 'Sesuai Jadwal') }}</b></span>
                <span>Nilai Maksimal: <b>{{ selectedAssignment?.max_score || 100 }} Poin</b></span>
              </div>
            </div>
          </div>

          <!-- 2. Status Pengumpulan Sebelumnya (Jika Ada) -->
          <div v-if="currentAssignmentSubmission" class="p-3.5 bg-emerald-50/70 border border-emerald-200 rounded-2xl space-y-2">
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-emerald-900 flex items-center gap-1.5">
                <span>✓</span> Berkas Sebelumnya Sudah Terkumpul
              </span>
              <span class="text-[10px] text-emerald-700 font-medium">
                {{ formatTimeNice(currentAssignmentSubmission.submitted_at) }}
              </span>
            </div>
            
            <div v-if="currentAssignmentSubmission.file_url" class="flex items-center justify-between p-2.5 bg-white rounded-xl border border-emerald-100 text-xs">
              <span class="truncate font-medium text-slate-700 text-[11px] max-w-[200px]">
                📄 {{ currentAssignmentSubmission.file_path ? currentAssignmentSubmission.file_path.split('/').pop() : 'Berkas Tugas Anda' }}
              </span>
              <a
                :href="currentAssignmentSubmission.file_url"
                target="_blank"
                download
                class="px-2.5 py-1 rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-800 font-bold text-[10px] transition flex items-center gap-1"
              >
                <span>Unduh Berkas</span>
                <span>⤓</span>
              </a>
            </div>

            <!-- Nilai dan Catatan Guru jika sudah dinilai -->
            <div v-if="currentAssignmentSubmission.is_graded || currentAssignmentSubmission.score !== null" class="pt-1.5 border-t border-emerald-200/70 flex flex-col gap-1 text-[11px]">
              <div class="flex items-center justify-between">
                <span class="font-extrabold text-emerald-900">Nilai dari Guru:</span>
                <span class="px-2 py-0.5 rounded-md bg-emerald-200 text-emerald-900 font-mono font-bold text-xs">
                  {{ currentAssignmentSubmission.score }}/100
                </span>
              </div>
              <p v-if="currentAssignmentSubmission.teacher_feedback" class="text-emerald-800 text-[11px] bg-emerald-100/50 p-2 rounded-lg italic">
                <b>Catatan Guru:</b> "{{ currentAssignmentSubmission.teacher_feedback }}"
              </p>
            </div>

            <p class="text-[10px] text-emerald-700/90 italic">
              * Anda dapat mengunggah berkas baru di bawah ini untuk mengganti / memperbarui tugas yang telah dikirim.
            </p>
          </div>

          <!-- 3. Unggah Berkas Tugas (Input File: PDF, DOC, DOCX maks 10 MB) -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="text-xs font-bold text-slate-700 flex items-center gap-1">
                <span>Unggah File Tugas Siswa</span>
                <span class="text-rose-500">*</span>
              </label>
              <div class="flex items-center gap-1.5">
                <span class="px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 border border-sky-100 text-[10px] font-extrabold">
                  PDF, DOC, DOCX
                </span>
                <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-100 text-[10px] font-extrabold">
                  Maks. 10 MB
                </span>
              </div>
            </div>

            <!-- Error Banner -->
            <div v-if="fileErrorMsg" class="mb-2.5 p-2.5 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs font-semibold flex items-center gap-2">
              <span class="text-sm">⚠️</span>
              <span>{{ fileErrorMsg }}</span>
            </div>

            <!-- Drag & Drop Zone -->
            <div
              v-if="!assignmentFile"
              @dragover.prevent="isDraggingFile = true"
              @dragleave.prevent="isDraggingFile = false"
              @drop.prevent="handleFileDrop"
              :class="isDraggingFile ? 'border-sky-500 bg-sky-50/50' : 'border-slate-200 bg-slate-50/40 hover:bg-slate-50 hover:border-sky-300'"
              class="border-2 border-dashed rounded-2xl p-6 text-center cursor-pointer transition group"
              @click="$refs.fileInputRef && ($refs.fileInputRef as HTMLInputElement).click()"
            >
              <input
                ref="fileInputRef"
                type="file"
                accept=".pdf,.doc,.docx"
                @change="handleFileChange"
                class="hidden"
              />
              <div class="w-10 h-10 rounded-2xl bg-sky-100 text-sky-600 group-hover:scale-105 flex items-center justify-center mx-auto mb-2 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
              </div>
              <p class="text-xs font-bold text-slate-800 group-hover:text-sky-700 transition">
                Klik untuk memilih file tugas atau seret berkas ke sini
              </p>
              <p class="text-[11px] text-slate-400 mt-1 font-medium">
                Mendukung format: PDF, DOC, atau DOCX (Maksimal 10 MB)
              </p>
            </div>

            <!-- Preview Berkas yang Dipilih -->
            <div
              v-else
              class="p-3.5 bg-sky-50/70 border border-sky-200 rounded-2xl flex items-center justify-between gap-3"
            >
              <div class="flex items-center gap-3 overflow-hidden">
                <span
                  class="w-10 h-10 rounded-xl flex items-center justify-center font-extrabold text-xs shrink-0 text-white shadow-2xs"
                  :class="assignmentFile.name.endsWith('.pdf') ? 'bg-rose-500' : 'bg-blue-600'"
                >
                  {{ assignmentFile.name.endsWith('.pdf') ? 'PDF' : 'DOC' }}
                </span>
                <div class="truncate">
                  <p class="text-xs font-bold text-slate-800 truncate">{{ assignmentFile.name }}</p>
                  <p class="text-[10px] text-slate-500 font-semibold mt-0.5">
                    {{ formatFileSize(assignmentFile.size) }} • Siap Diunggah ✓
                  </p>
                </div>
              </div>
              <button
                type="button"
                @click="removeSelectedFile"
                class="text-xs font-bold text-rose-600 hover:text-rose-800 px-2.5 py-1.5 rounded-lg hover:bg-rose-50 transition shrink-0"
              >
                Ganti / Hapus
              </button>
            </div>
          </div>

          <!-- 4. Catatan / Jawaban Teks Siswa -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">
              Catatan / Ringkasan Pengerjaan Siswa (Opsional)
            </label>
            <textarea
              v-model="assignmentNotes"
              rows="3"
              placeholder="Tuliskan catatan, penjelasan tugas, atau ringkasan pengerjaan untuk guru..."
              class="w-full p-3 text-xs border border-slate-200 rounded-xl focus:outline-none focus:border-sky-500 font-medium placeholder-slate-400"
            ></textarea>
          </div>

          <!-- Tombol Aksi -->
          <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5 shrink-0">
            <button
              type="button"
              @click="showAssignmentModal = false"
              class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 transition"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="submittingAssignment"
              class="px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 disabled:opacity-50 text-white font-bold text-xs transition shadow-sm flex items-center gap-2"
            >
              <span v-if="submittingAssignment" class="animate-spin text-sm">⏳</span>
              <span>{{ submittingAssignment ? 'Mengunggah...' : (currentAssignmentSubmission ? 'Perbarui Tugas Mandiri' : 'Kirim Tugas Mandiri') }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
