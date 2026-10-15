<script setup lang="ts">
import { computed, onMounted, ref, reactive } from 'vue'
import AppButton from '~/components/ui/AppButton.vue'
import AppBadge from '~/components/ui/AppBadge.vue'
import AppSkeleton from '~/components/ui/AppSkeleton.vue'
import AppEmptyState from '~/components/ui/AppEmptyState.vue'
import QrScannerModal from '~/components/ui/QrScannerModal.vue'

definePageMeta({ layout: 'student', middleware: 'auth' })

const { user } = useAuth()
const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const storageBase = config.public.storageBase || 'http://localhost:8000/storage'

// Global sidebar state synced with header hamburger button
const isSidebarOpen = useState('isStudentSidebarOpen', () => true)

const loading = ref(true)
const schedules = ref<any[]>([])
const courses = ref<any[]>([])
const submissions = ref<any[]>([])
const tasks = ref<any[]>([])
const semesters = ref<any[]>([])

// -------------------------------------------------------------------
// Sesi KBM Harian (Decoupled Daily KBM Sessions)
// -------------------------------------------------------------------
const activeKbmData = ref<any>(null)
const todaySessions = computed(() => activeKbmData.value?.today_sessions || [])
const activeOpenSession = computed(() => activeKbmData.value?.active_open_session || null)
const checkingInSessionId = ref<number | null>(null)
const sessionSuccessMsg = ref('')

// Modal Presensi QR untuk Siswa
const showQrModal = ref(false)
const selectedQrSession = ref<any>(null)
const qrInputToken = ref('')
const qrSubmitting = ref(false)
const qrErrorMsg = ref('')

// Filter & Search states
const sidebarSearch = ref('')
const mainSearch = ref('')
const selectedSemester = ref('all')
const currentPage = ref(1)
const itemsPerPage = 8

const navigateToCourse = (course: any) => {
  if (!course?.id) return
  navigateTo(`/elearning/courses/${course.id}`)
}

// -------------------------------------------------------------------
// Sidebar Course Dropdown (6 Menu Default Aktivitas Belajar LMS)
// -------------------------------------------------------------------
const expandedCourseIds = reactive<Record<number, boolean>>({})

const toggleCourseDropdown = (courseId: number) => {
  expandedCourseIds[courseId] = !expandedCourseIds[courseId]
}

const defaultActivityMenuItems = [
  { key: 'pretest', number: 1, label: 'Pretest' },
  { key: 'material', number: 2, label: 'Modul & Bahan Ajar' },
  { key: 'discussion', number: 3, label: 'Ruang Diskusi' },
  { key: 'quiz', number: 4, label: 'Kuis Formatif' },
  { key: 'assignment', number: 5, label: 'Tugas Mandiri' },
  { key: 'posttest', number: 6, label: 'Posttest' },
]

const navigateToCourseActivity = (course: any, activityKey: string) => {
  if (!course?.id) return
  navigateTo(`/elearning/courses/${course.id}?activity=${activityKey}`)
}

const api = <T>(path: string) => $fetch<T>(`${apiBase}${path}`, {
  headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
})

const list = (response: any) => response?.data || response || []
const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']
const dayLabels: Record<string, string> = {
  sunday: 'Minggu', monday: 'Senin', tuesday: 'Selasa', wednesday: 'Rabu',
  thursday: 'Kamis', friday: 'Jumat', saturday: 'Sabtu'
}

const todayName = computed(() => dayNames[new Date().getDay()])
const todayIndoDateString = computed(() => {
  return new Date().toLocaleDateString('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
})

const subjectName = (course: any) => course?.subject?.name || 'Mata Pelajaran'
const className = (course: any) => course?.class?.class_name?.name || course?.class?.className?.name || course?.class?.name || 'Kelas'

// Helper for finding course schedule
const getCourseSchedule = (course: any) => {
  const match = schedules.value.find(s => s.course_id === course.id)
  if (match) {
    return {
      day: dayLabels[match.day] || match.day,
      time: `${match.start_time || '07:30'} - ${match.end_time || '09:00'} WIB`,
      room: match.room_number || 'R. Kelas'
    }
  }
  return {
    day: 'Senin',
    time: '07:30 - 09:00 WIB',
    room: 'R. Belajar'
  }
}

// Time formatter helpers
const formatTimeOnly = (timeStr?: string) => {
  if (!timeStr) return '-'
  return timeStr.slice(0, 5) + ' WIB'
}

const formatDateTimeNice = (dtStr?: string) => {
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

// Filtered courses for left sidebar
const sidebarFilteredCourses = computed(() => {
  if (!sidebarSearch.value.trim()) return courses.value
  const q = sidebarSearch.value.toLowerCase().trim()
  return courses.value.filter(c => {
    const s = subjectName(c).toLowerCase()
    const code = className(c).toLowerCase()
    return s.includes(q) || code.includes(q)
  })
})

// Filtered courses for main grid
const filteredMainCourses = computed(() => {
  let list = courses.value
  if (mainSearch.value.trim()) {
    const q = mainSearch.value.toLowerCase().trim()
    list = list.filter(c => {
      const s = subjectName(c).toLowerCase()
      const t = (c.teacher?.name || '').toLowerCase()
      const cl = className(c).toLowerCase()
      return s.includes(q) || t.includes(q) || cl.includes(q)
    })
  }
  return list
})

// Pagination
const totalPages = computed(() => Math.max(1, Math.ceil(filteredMainCourses.value.length / itemsPerPage)))
const paginatedCourses = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  return filteredMainCourses.value.slice(start, start + itemsPerPage)
})

// Fetch all dashboard data
const fetchAllDashboardData = async () => {
  loading.value = true
  try {
    const [scheduleRes, courseRes, submissionRes, semesterRes, kbmRes] = await Promise.all([
      api<any>('/schedules?my_schedules=true&all=true&with_current_meet=true'),
      api<any>('/courses?my_courses=true&all=true&with_meets=true'),
      api<any>('/submissions?my_submissions=true'),
      api<any>('/semesters').catch(() => null),
      api<any>('/kbm-sessions/active').catch(() => null)
    ])

    schedules.value = list(scheduleRes)
    courses.value = list(courseRes)
    submissions.value = list(submissionRes)
    semesters.value = list(semesterRes)
    activeKbmData.value = kbmRes?.data || null

    // Extract all quizzes
    const allQuizzes: any[] = []
    courses.value.forEach(c => {
      (c?.meets || []).forEach((m: any) => {
        (m.quizzes || []).forEach((q: any) => {
          allQuizzes.push({
            ...q,
            meet_id: m.id,
            meet_number: m.meeting_number,
            meet_title: m.title,
            course_name: subjectName(c),
            teacher_name: c?.teacher?.name || 'Guru Pengampu'
          })
        })
      })
    })
    tasks.value = allQuizzes
  } catch (error) {
    console.error('Gagal memuat dashboard siswa:', error)
  } finally {
    loading.value = false
  }
}

// Refresh active KBM sessions
const refreshActiveKbmSessions = async () => {
  try {
    const res: any = await api('/kbm-sessions/active')
    activeKbmData.value = res?.data || null
  } catch (e) {
    console.error('Gagal merefresh sesi KBM aktif:', e)
  }
}

onMounted(() => {
  fetchAllDashboardData()
})

// -------------------------------------------------------------------
// Presensi KBM Harian Siswa (Self Check-in & QR)
// -------------------------------------------------------------------
const doSessionCheckIn = async (sessionId: number, method: 'self_click' | 'qr_scan' = 'self_click', customPayload: any = {}) => {
  checkingInSessionId.value = sessionId
  sessionSuccessMsg.value = ''
  qrErrorMsg.value = ''

  try {
    const body: any = {
      method,
      status: 'H',
      notes: method === 'self_click' ? 'Presensi Mandiri Siswa' : 'Presensi Scan QR Proyektor',
      ...customPayload
    }

    const res: any = await $fetch<any>(`${apiBase}/kbm-sessions/${sessionId}/check-in`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
      body
    })

    sessionSuccessMsg.value = res?.message || 'Presensi berhasil dicatat! Status: Hadir.'
    if (showQrModal.value) {
      showQrModal.value = false
    }

    await refreshActiveKbmSessions()

    setTimeout(() => {
      sessionSuccessMsg.value = ''
    }, 6000)
  } catch (err: any) {
    const msg = err?.data?.message || err?.message || 'Gagal melakukan presensi.'
    if (method === 'qr_scan') {
      qrErrorMsg.value = msg
    } else {
      alert(msg)
    }
  } finally {
    checkingInSessionId.value = null
    qrSubmitting.value = false
  }
}

const openQrModal = (session: any) => {
  selectedQrSession.value = session
  qrInputToken.value = ''
  qrErrorMsg.value = ''
  showQrModal.value = true
}

const submitQrPayload = async (payload: { qr_token: string, time_slot?: number }) => {
  if (!selectedQrSession.value) return
  qrSubmitting.value = true
  qrErrorMsg.value = ''
  await doSessionCheckIn(selectedQrSession.value.id, 'qr_scan', payload)
}

// Buka ruang belajar LMS langsung dari sesi KBM hari ini
const openDrawerFromKbmSession = (session: any) => {
  if (!session?.course) return
  const meetNum = session.active_meet?.meeting_number || 1
  navigateTo(`/elearning/courses/${session.course.id}?meet=${meetNum}`)
}
</script>

<template>
  <div class="flex min-h-[calc(100vh-64px)] w-full bg-slate-50/50">
    <!-- 1. LEFT SIDEBAR: Daftar Mata Pelajaran (Collapsible via Dashboard Button) -->
    <aside
      v-show="isSidebarOpen"
      class="w-64 sm:w-72 border-r border-slate-200 bg-white shrink-0 p-4 space-y-4 flex flex-col justify-between transition-all"
    >
      <div class="space-y-3">
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">
          Courses List
        </h2>

        <!-- Search Input Sidebar -->
        <div class="relative">
          <input
            v-model="sidebarSearch"
            type="text"
            placeholder="Pencarian"
            class="w-full pl-8 pr-3 py-2 text-xs border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:border-[#1565c0] transition font-medium text-slate-800 placeholder-slate-400"
          />
          <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <!-- Vertical Courses List -->
        <div class="space-y-1.5 overflow-y-auto max-h-[calc(100vh-210px)] pr-1">
          <div
            v-if="loading"
            class="space-y-2 py-2"
          >
            <AppSkeleton v-for="i in 5" :key="i" height="h-12" class="rounded-xl" />
          </div>

          <div
            v-else-if="sidebarFilteredCourses.length === 0"
            class="py-6 text-center text-xs text-slate-400 font-medium"
          >
            Mata pelajaran tidak ditemukan
          </div>

          <div
            v-else
            v-for="course in sidebarFilteredCourses"
            :key="course.id"
            class="rounded-xl border transition overflow-hidden"
            :class="expandedCourseIds[course.id] ? 'bg-blue-50/40 border-blue-200' : 'bg-white border-transparent hover:border-slate-200 hover:bg-slate-50/70'"
          >
            <!-- Course Header Toggle (Click to inspect/expand) -->
            <div class="p-2.5 flex items-center justify-between transition group">
              <div
                @click="navigateToCourse(course)"
                class="min-w-0 pr-1 cursor-pointer flex-1"
              >
                <div class="text-xs font-bold text-slate-800 uppercase tracking-tight group-hover:text-[#1565c0] truncate">
                  {{ subjectName(course) }}
                </div>
                <div class="text-[11px] text-slate-500 font-medium mt-0.5 truncate">
                  Kelas {{ className(course) }}
                </div>
              </div>

              <!-- Dropdown Trigger Button -->
              <button
                type="button"
                @click.stop="toggleCourseDropdown(course.id)"
                class="p-1 rounded-md text-slate-400 hover:text-[#1565c0] hover:bg-blue-100/60 transition shrink-0"
                :title="expandedCourseIds[course.id] ? 'Tutup aktivitas' : 'Buka aktivitas belajar'"
              >
                <svg
                  class="w-3.5 h-3.5 transition-transform duration-200"
                  :class="expandedCourseIds[course.id] ? 'rotate-180 text-[#1565c0]' : ''"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
            </div>

            <!-- Dropdown 6 Aktivitas Belajar LMS -->
            <div
              v-show="expandedCourseIds[course.id]"
              class="px-2 pb-2 pt-1 border-t border-slate-100 space-y-0.5 bg-slate-50/60"
            >
              <button
                v-for="act in defaultActivityMenuItems"
                :key="act.key"
                type="button"
                @click="navigateToCourseActivity(course, act.key)"
                class="w-full flex items-center justify-between px-2 py-1.5 rounded-lg text-xs font-medium text-slate-700 hover:text-blue-700 hover:bg-white transition text-left group/act"
              >
                <span class="flex items-center gap-2 truncate">
                  <span class="w-4 h-4 rounded text-[10px] font-bold flex items-center justify-center shrink-0 bg-slate-200 text-slate-700 group-hover/act:bg-blue-100 group-hover/act:text-blue-800">
                    {{ act.number }}
                  </span>
                  <span class="truncate">{{ act.label }}</span>
                </span>
                <span class="text-[10px] text-slate-400 group-hover/act:text-[#1565c0]">➔</span>
              </button>

              <div class="pt-1 border-t border-slate-200/60 mt-1">
                <button
                  type="button"
                  @click="navigateToCourse(course)"
                  class="w-full text-center py-1 text-[11px] font-bold text-[#1565c0] hover:underline"
                >
                  Buka Halaman Kursus
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </aside>

    <!-- 2. RIGHT MAIN CONTENT AREA -->
    <div class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 overflow-x-hidden">
      <!-- A. Top Dashboard Banner Card -->
      <div class="bg-[#dce9f8] rounded-2xl p-6 sm:p-7 border border-blue-200/60 flex items-center justify-between shadow-xs">
        <div>
          <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight">
            Dashboard
          </h1>
          <p class="text-xs text-slate-500 font-medium mt-1">
            Dashboard Belajar Siswa • MAN 11 Jakarta • {{ todayIndoDateString }}
          </p>
        </div>

        <!-- Right Connected Nodes Emblem Icon -->
        <div class="w-16 h-16 shrink-0 flex items-center justify-center text-[#1565c0]">
          <svg class="w-12 h-12" viewBox="0 0 48 48" fill="none">
            <circle cx="16" cy="16" r="5" stroke="currentColor" stroke-width="3" fill="none" />
            <circle cx="36" cy="20" r="4.5" stroke="currentColor" stroke-width="3" fill="none" />
            <circle cx="28" cy="36" r="5" stroke="currentColor" stroke-width="3" fill="none" />
            <path d="M21 17l10.5 2m-16 1.5l8.5 12.5m10-12.5l-3.5 10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
          </svg>
        </div>
      </div>

      <!-- Live Sesi KBM Hari Ini & Presensi Mandiri / Scan QR -->
      <div v-if="sessionSuccessMsg" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-900 rounded-2xl flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-3">
          <span class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-sm">✓</span>
          <span class="text-xs font-bold">{{ sessionSuccessMsg }}</span>
        </div>
        <button type="button" @click="sessionSuccessMsg = ''" class="text-emerald-700 hover:text-emerald-900 text-xs font-bold">✕</button>
      </div>

      <div v-if="todaySessions.length > 0" class="space-y-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
            <h2 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider">
              Sesi KBM & Presensi Hari Ini
            </h2>
          </div>
          <span class="text-xs text-slate-500 font-medium">
            {{ todaySessions.length }} Jadwal KBM
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="s in todaySessions"
            :key="s.id"
            class="bg-white rounded-2xl border p-5 transition flex flex-col justify-between shadow-xs"
            :class="s.status === 'open' ? 'border-emerald-300 ring-2 ring-emerald-100' : 'border-slate-200'"
          >
            <div class="space-y-3">
              <div class="flex items-center justify-between gap-2">
                <span
                  class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider"
                  :class="s.status === 'open' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                >
                  {{ s.status === 'open' ? '● KBM Berlangsung' : 'Selesai / Terjadwal' }}
                </span>

                <!-- Status Presensi Badge -->
                <span
                  v-if="s.my_attendance"
                  class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1"
                >
                  <span>✓</span>
                  <span>Sudah Presensi ({{ s.my_attendance.status_label || 'Hadir' }})</span>
                </span>
                <span
                  v-else
                  class="px-2.5 py-1 rounded-full text-[10px] font-black bg-amber-50 text-amber-700 border border-amber-200"
                >
                  Belum Presensi
                </span>
              </div>

              <div>
                <h3 class="font-extrabold text-slate-900 text-sm">
                  {{ subjectName(s.course) }}
                </h3>
                <p class="text-xs text-slate-500 font-medium mt-0.5">
                  {{ s.teacher?.name || 'Guru Pengampu' }} • Kelas {{ className(s.course) }}
                </p>
                <div v-if="s.active_meet" class="mt-2 p-2.5 bg-slate-50 rounded-xl border border-slate-100 text-xs">
                  <span class="text-slate-400 font-semibold block text-[10px]">Materi / Sub-bab Aktif:</span>
                  <span class="font-bold text-slate-800 block truncate">
                    Pertemuan {{ s.active_meet.meeting_number }}: {{ s.active_meet.title }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Action buttons -->
            <div class="pt-4 mt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
              <button
                type="button"
                @click="openDrawerFromKbmSession(s)"
                class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5"
              >
                <span>Buka Pembelajaran</span>
                <span>➔</span>
              </button>

              <div class="flex items-center gap-2">
                <!-- 1-Klik Checkin jika diizinkan dan belum presensi -->
                <button
                  v-if="s.status === 'open' && !s.my_attendance && s.allow_self_checkin"
                  type="button"
                  :disabled="checkingInSessionId === s.id"
                  @click="doSessionCheckIn(s.id, 'self_click')"
                  class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center gap-1"
                >
                  <span v-if="checkingInSessionId === s.id" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                  <span>1-Klik Hadir</span>
                </button>

                <!-- Tombol Scan QR Proyektor -->
                <button
                  v-if="s.status === 'open' && !s.my_attendance"
                  type="button"
                  @click="openQrModal(s)"
                  class="px-3.5 py-2 bg-emerald-800 hover:bg-emerald-900 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center gap-1.5"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                  </svg>
                  <span>Scan QR</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- B. Search & Semester Filter Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        <!-- Search Input -->
        <div class="relative flex-1 max-w-md">
          <input
            v-model="mainSearch"
            type="text"
            placeholder="Pencarian mata pelajaran atau guru pengampu..."
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
          <select
            v-model="selectedSemester"
            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition shadow-xs appearance-none pr-9 cursor-pointer"
          >
            <option value="all">Semua Semester</option>
            <option value="ganjil_2026">Semester GANJIL 2026/2027</option>
            <option value="genap_2025">Semester GENAP 2025/2026</option>
          </select>
          <svg class="w-4 h-4 text-slate-500 absolute right-3 top-3 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </div>
      </div>

      <!-- COURSES SECTION (Grid of Subjects) -->
      <div class="space-y-4 pt-2">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-bold text-slate-800">
            Mata Pelajaran Saya
          </h2>

          <!-- Pagination Controls -->
          <div class="flex items-center gap-1 text-xs">
            <button
              type="button"
              :disabled="currentPage === 1"
              @click="currentPage--"
              class="w-7 h-7 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center font-bold text-slate-600 transition"
            >
              &lt;
            </button>
            <span class="w-7 h-7 rounded-lg bg-blue-50 border border-blue-200 text-[#1565c0] font-bold flex items-center justify-center">
              {{ currentPage }}
            </span>
            <button
              type="button"
              :disabled="currentPage >= totalPages"
              @click="currentPage++"
              class="w-7 h-7 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center font-bold text-slate-600 transition"
            >
              &gt;
            </button>
          </div>
        </div>

        <!-- Skeleton Loading -->
        <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          <AppSkeleton v-for="i in 4" :key="i" height="h-56" class="rounded-2xl" />
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredMainCourses.length === 0">
          <AppEmptyState
            title="Tidak ada mata pelajaran"
            description="Tidak ditemukan mata pelajaran yang sesuai dengan pencarian Anda."
          />
        </div>

        <!-- Course Cards Grid -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          <div
            v-for="course in paginatedCourses"
            :key="course.id"
            @click="navigateToCourse(course)"
            class="bg-white border border-slate-200 rounded-2xl p-5 hover:border-blue-400 hover:shadow-md transition cursor-pointer flex flex-col justify-between min-h-[220px] group"
          >
            <!-- Top Section -->
            <div>
              <h3 class="text-[#1565c0] font-extrabold text-sm uppercase leading-tight line-clamp-2 group-hover:underline">
                {{ subjectName(course) }}
              </h3>
              <p class="text-[10px] text-slate-400 font-medium mt-1 leading-tight line-clamp-2">
                Kurikulum Merdeka • Kelas {{ className(course) }} • {{ course.meets_count || 0 }} Pertemuan
              </p>

              <!-- Middle Teacher Section -->
              <div class="mt-3">
                <span class="text-[10px] text-slate-400 font-medium block">
                  Guru Pengampu
                </span>
                <span class="text-xs font-bold text-slate-800 block truncate mt-0.5">
                  {{ course.teacher?.name || 'Guru Pengampu' }}
                </span>
              </div>
            </div>

            <!-- Bottom Metadata Section -->
            <div class="pt-3 border-t border-slate-100 mt-4 space-y-1">
              <div class="flex items-center justify-between text-xs">
                <div>
                  <span class="text-[10px] text-slate-400 block leading-tight">Kelas</span>
                  <span class="font-bold text-slate-800 text-xs">{{ className(course) }}</span>
                </div>
                <div class="text-right">
                  <span class="text-[10px] text-slate-400 block leading-tight">Total Sesi</span>
                  <span class="font-bold text-slate-800 text-xs">{{ course.meets_count || 0 }} Pertemuan</span>
                </div>
              </div>

              <div>
                <span class="text-[10px] text-slate-400 block leading-tight">Jadwal Rutin</span>
                <span class="text-xs font-medium text-slate-700 block truncate">
                  {{ getCourseSchedule(course).day }}, {{ getCourseSchedule(course).time }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL PRESENSI QR PROYEKTOR SISWA -->
    <QrScannerModal
      v-model="showQrModal"
      :session="selectedQrSession"
      :loading="qrSubmitting"
      :error-message="qrErrorMsg"
      @submit="submitQrPayload"
      @close="showQrModal = false"
    />
  </div>
</template>
