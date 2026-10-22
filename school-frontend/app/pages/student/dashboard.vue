<script setup lang="ts">
import { computed, onMounted, ref, reactive } from 'vue'
import AppButton from '~/components/ui/AppButton.vue'
import AppBadge from '~/components/ui/AppBadge.vue'
import AppSkeleton from '~/components/ui/AppSkeleton.vue'
import AppEmptyState from '~/components/ui/AppEmptyState.vue'

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
  { key: 'pretest', label: 'Pretest' },
  { key: 'material', label: 'Modul & Bahan Ajar' },
  { key: 'discussion', label: 'Ruang Diskusi' },
  { key: 'quiz', label: 'Kuis Formatif' },
  { key: 'assignment', label: 'Tugas Mandiri' },
  { key: 'posttest', label: 'Posttest' },
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
    const [scheduleRes, courseRes, submissionRes, semesterRes] = await Promise.all([
      api<any>('/schedules?my_schedules=true&all=true&with_current_meet=true'),
      api<any>('/courses?my_courses=true&all=true&with_meets=true'),
      api<any>('/submissions?my_submissions=true'),
      api<any>('/semesters').catch(() => null)
    ])

    schedules.value = list(scheduleRes)
    courses.value = list(courseRes)
    submissions.value = list(submissionRes)
    semesters.value = list(semesterRes)

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

onMounted(() => {
  fetchAllDashboardData()
})
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
            class="w-full pl-8 pr-3 py-2 text-xs border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-hidden focus:border-brand-700 transition font-medium text-slate-800 placeholder-slate-400"
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
                  <span class="w-5 h-5 rounded-md flex items-center justify-center shrink-0 border border-slate-200 bg-slate-100 text-slate-500 group-hover/act:bg-blue-100 group-hover/act:text-blue-800 transition">
                    <!-- Pretest -->
                    <svg v-if="act.key === 'pretest'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <!-- Modul & Bahan Ajar -->
                    <svg v-else-if="act.key === 'material'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <!-- Ruang Diskusi -->
                    <svg v-else-if="act.key === 'discussion'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <!-- Kuis Formatif -->
                    <svg v-else-if="act.key === 'quiz'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    <!-- Tugas Mandiri -->
                    <svg v-else-if="act.key === 'assignment'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <!-- Posttest -->
                    <svg v-else-if="act.key === 'posttest'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
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

  </div>
</template>
