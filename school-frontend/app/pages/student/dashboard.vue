<script setup lang="ts">
import { computed, onMounted, ref, reactive, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import SlideOver from '~/components/ui/SlideOver.vue'
import QuickTabs, { type TabItem } from '~/components/ui/QuickTabs.vue'

definePageMeta({ layout: 'student', middleware: 'auth' })

const route = useRoute()
const router = useRouter()
const { user } = useAuth()
const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const storageBase = config.public.storageBase || 'http://localhost:8000/storage'

const loading = ref(true)
const schedules = ref<any[]>([])
const courses = ref<any[]>([])
const submissions = ref<any[]>([])
const tasks = ref<any[]>([])
const gradesData = ref<any>(null)
const attendanceSummary = ref<any>(null)

// Active Hub Tab (synced with route query tab)
const activeTab = ref(route.query.tab ? String(route.query.tab) : 'courses')
const hubTabs: TabItem[] = [
  { id: 'courses', label: 'Kelas & Modul Materi', icon: '📚' },
  { id: 'tasks', label: 'Tugas & Ujian CBT', icon: '🎯' },
  { id: 'schedule', label: 'Jadwal Mingguan', icon: '📅' },
  { id: 'grades', label: 'Rapor Nilai KKTP', icon: '🏆' },
  { id: 'attendance', label: 'Rekap Presensi', icon: '📊' },
]

watch(() => route.query.tab, (newTab) => {
  if (newTab && typeof newTab === 'string') {
    activeTab.value = newTab
  }
})

watch(() => activeTab.value, (newTab) => {
  if (route.query.tab !== newTab) {
    router.replace({ query: { ...route.query, tab: newTab } })
  }
})

// SlideOver Drawer State (View Course Materials without leaving)
const drawerOpen = ref(false)
const selectedCourse = ref<any>(null)
const selectedCourseMeets = ref<any[]>([])
const drawerLoading = ref(false)

// Self Check-In State
const checkingIn = ref(false)
const checkInSuccessMsg = ref('')

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
const todaySchedules = computed(() => schedules.value.filter(schedule => schedule.day === todayName.value))

const submittedQuizIds = computed(() => new Set(submissions.value.filter(item => item.submitted_at).map(item => item.quiz_id)))
const pendingTasksList = computed(() => tasks.value.filter(task => !submittedQuizIds.value.has(task.id)))

const firstName = computed(() => (user.value?.student?.name || user.value?.name || user.value?.username || 'Siswa').split(' ')[0])
const studentNis = computed(() => user.value?.student?.nisn || user.value?.username || '-')

const subjectName = (course: any) => course?.subject?.name || 'Mata pelajaran'
const className = (course: any) => course?.class?.class_name?.name || course?.class?.className?.name || 'Kelas'

// Detect Active Session for Today
const activeSession = computed(() => {
  if (todaySchedules.value.length > 0) {
    return todaySchedules.value[0]
  }
  if (schedules.value.length > 0) {
    return schedules.value[0] // fallback to next schedule
  }
  return null
})

// Associated active meet for the active session (if any)
const activeSessionMeet = computed(() => {
  if (!activeSession.value) return null
  const c = courses.value.find(item => item.id === activeSession.value.course_id)
  if (c && c.meets && c.meets.length > 0) {
    return c.meets[c.meets.length - 1]
  }
  return null
})

// Initial Fetching
const fetchAllDashboardData = async () => {
  loading.value = true
  try {
    const [scheduleRes, courseRes, submissionRes, gradesRes, attRes] = await Promise.all([
      api<any>('/schedules?my_schedules=true&all=true'),
      api<any>('/courses?my_courses=true&all=true'),
      api<any>('/submissions?my_submissions=true'),
      api<any>('/scores/student/me').catch(() => null),
      api<any>('/student/attendance-summary').catch(() => null)
    ])

    schedules.value = list(scheduleRes)
    courses.value = list(courseRes)
    submissions.value = list(submissionRes)
    gradesData.value = gradesRes || null
    attendanceSummary.value = attRes?.data || attRes || null

    // Extract all quizzes across enrolled courses
    const allQuizzes: any[] = []
    courses.value.forEach(c => {
      (c?.meets || []).forEach((m: any) => {
        (m.quizzes || []).forEach((q: any) => {
          allQuizzes.push({
            ...q,
            meet_number: m.meeting_number,
            meet_title: m.title,
            course_name: subjectName(c)
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

// Open Course Materials in SlideOver Drawer
const openCourseDrawer = async (course: any) => {
  selectedCourse.value = course
  drawerLoading.value = true
  drawerOpen.value = true

  try {
    const res: any = await api(`/meets?course_id=${course.id}&paginate=false`)
    selectedCourseMeets.value = list(res)
  } catch (e) {
    console.error('Fetch course meets error:', e)
    selectedCourseMeets.value = course.meets || []
  } finally {
    drawerLoading.value = false
  }
}

// 1-Click Self Check-in
const doSelfCheckIn = async (meetId: number | string) => {
  if (!meetId) return
  checkingIn.value = true
  checkInSuccessMsg.value = ''

  try {
    const res: any = await $fetch(`${apiBase}/meets/${meetId}/attendances/check-in`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
      body: { status: 'H', notes: 'Presensi Mandiri Siswa' }
    })

    checkInSuccessMsg.value = res?.message || 'Presensi Anda berhasil dicatat!'
    await fetchAllDashboardData()
    setTimeout(() => {
      checkInSuccessMsg.value = ''
    }, 5000)
  } catch (err: any) {
    alert(err?.data?.message || err?.message || 'Gagal melakukan presensi.')
  } finally {
    checkingIn.value = false
  }
}
</script>

<template>
  <div class="space-y-6 font-sans text-slate-800 pb-12">
    <!-- 1. HERO BANNER: SELAMAT DATANG -->
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-900 via-slate-900 to-emerald-950 p-6 text-white shadow-xl sm:p-8">
      <div class="absolute -right-12 -top-12 h-64 w-64 rounded-full bg-emerald-400/10 blur-2xl" />
      <div class="relative flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
        <div>
          <div class="flex items-center gap-2">
            <span class="rounded-full border border-emerald-400/30 bg-emerald-500/20 px-3 py-0.5 text-[11px] font-bold text-emerald-200">
              PORTAL BELAJAR SISWA
            </span>
            <span class="text-xs text-slate-300 font-mono">NISN: {{ studentNis }}</span>
          </div>
          <h1 class="mt-3 text-2xl font-black sm:text-3xl tracking-tight">
            Assalamu'alaikum, {{ firstName }}! 👋
          </h1>
          <p class="mt-1 text-xs sm:text-sm text-emerald-100/80">
            Pusat kendali aktivitas harian: jadwal, presensi mandiri 1-klik, modul materi, dan ujian CBT di satu layar.
          </p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <NuxtLink
            to="/student/quizzes"
            class="px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold shadow-md transition flex items-center gap-1.5"
          >
            <span>⚡</span>
            <span>Ruang Ujian CBT</span>
          </NuxtLink>
        </div>
      </div>
    </section>

    <!-- 2. ACTIVE SESSION HERO CARD (PROACTIVE 1-CLICK ATTENDANCE & LESSON) -->
    <section class="border-2 border-emerald-500/30 bg-emerald-50/50 rounded-3xl p-6 shadow-xs relative overflow-hidden">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping" />
            <span class="text-[11px] font-black uppercase tracking-wider text-emerald-800">
              Sesi Pembelajaran Aktif Hari Ini ({{ dayLabels[todayName] || 'Hari Ini' }})
            </span>
          </div>

          <div v-if="activeSession">
            <h2 class="text-lg sm:text-xl font-black text-slate-900 flex items-center gap-2">
              <span>{{ subjectName(activeSession.course) }}</span>
              <span class="text-xs px-2.5 py-0.5 rounded-lg bg-emerald-200/80 text-emerald-900 font-bold">
                {{ activeSession.start_time }} - {{ activeSession.end_time }} WIB
              </span>
            </h2>
            <p class="text-xs text-slate-600 font-medium mt-0.5">
              Ruang: <span class="font-bold text-slate-800">{{ activeSession.room_number || 'Kelas Utama' }}</span> • 
              Guru Pengampu: <span class="font-bold text-slate-800">{{ activeSession.course?.teacher?.name || 'Guru' }}</span>
            </p>
          </div>
          <div v-else class="text-xs text-slate-500 italic py-1">
            Tidak ada jadwal tatap muka langsung saat ini. Anda dapat mempelajari modul materi di bawah.
          </div>
        </div>

        <!-- 1-Click Action Hub for Active Session -->
        <div class="flex flex-wrap items-center gap-2.5">
          <button
            v-if="activeSessionMeet"
            type="button"
            :disabled="checkingIn"
            class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-md transition flex items-center gap-2 disabled:opacity-50"
            @click="doSelfCheckIn(activeSessionMeet.id)"
          >
            <span v-if="checkingIn" class="animate-spin">⌛</span>
            <span v-else>✓</span>
            <span>{{ checkingIn ? 'Mencatat...' : 'Presensi Sekarang (1-Klik)' }}</span>
          </button>

          <button
            v-if="activeSession"
            type="button"
            class="px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-100 text-slate-800 text-xs font-bold shadow-xs transition flex items-center gap-1.5"
            @click="openCourseDrawer(activeSession.course)"
          >
            <span>📖</span>
            <span>Buka Modul Hari Ini</span>
          </button>
        </div>
      </div>

      <!-- Success check-in feedback alert -->
      <div v-if="checkInSuccessMsg" class="mt-4 p-3 bg-emerald-600 text-white rounded-2xl text-xs font-bold flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-2">
          <span>🎉</span>
          <span>{{ checkInSuccessMsg }}</span>
        </div>
        <button type="button" @click="checkInSuccessMsg = ''">✕</button>
      </div>
    </section>

    <!-- 3. QUICK STATS SUMMARY -->
    <section class="grid gap-4 sm:grid-cols-3">
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex items-center justify-between">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Kelas Terdaftar</p>
          <p class="mt-1 text-2xl font-black text-slate-900">{{ loading ? '…' : courses.length }}</p>
          <p class="text-xs text-slate-500">Mata pelajaran aktif</p>
        </div>
        <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-lg">
          📚
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex items-center justify-between">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-wider text-amber-600">Tugas & CBT Aktif</p>
          <p class="mt-1 text-2xl font-black text-slate-900">{{ loading ? '…' : pendingTasksList.length }}</p>
          <p class="text-xs text-slate-500">Perlu dikerjakan</p>
        </div>
        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center text-lg">
          🎯
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex items-center justify-between">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-600">Kehadiran Kumulatif</p>
          <p class="mt-1 text-2xl font-black text-slate-900">
            {{ attendanceSummary?.attendance_rate !== undefined ? attendanceSummary.attendance_rate + '%' : '100%' }}
          </p>
          <p class="text-xs text-slate-500">
            {{ attendanceSummary?.stats?.H || 0 }} Hadir dari {{ attendanceSummary?.stats?.total || 0 }} sesi
          </p>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-lg">
          📈
        </div>
      </div>
    </section>

    <!-- 4. SEGMENTED WORKSPACE (ALL ESSENTIALS AT A GLANCE WITHOUT URL SWITCHING) -->
    <section class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xs space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-2 border-b border-slate-100">
        <div>
          <h2 class="text-base font-black text-slate-900">Ruang Belajar Mandiri Terpadu</h2>
          <p class="text-xs text-slate-500">Semua aktivitas belajar, modul, tugas, jadwal, dan rapor dapat diakses langsung.</p>
        </div>
        <QuickTabs
          v-model="activeTab"
          :items="hubTabs"
        />
      </div>

      <!-- ============================================================== -->
      <!-- TAB 1: KELAS & MODUL MATERI                                    -->
      <!-- ============================================================== -->
      <div v-if="activeTab === 'courses'">
        <div v-if="courses.length === 0" class="p-8 text-center text-xs text-slate-400">
          Belum ada kelas terdaftar.
        </div>
        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="course in courses"
            :key="course.id"
            class="rounded-2xl border border-slate-200 p-5 hover:border-emerald-300 hover:shadow-xs transition bg-white flex flex-col justify-between group"
          >
            <div>
              <div class="flex items-center justify-between gap-2">
                <span class="rounded-lg bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-800 border border-emerald-200">
                  {{ className(course) }}
                </span>
                <span class="text-[11px] font-mono text-slate-400">
                  {{ course.meets_count || 0 }} Sesi
                </span>
              </div>
              <h3 class="mt-2.5 text-base font-black text-slate-900 group-hover:text-emerald-700 transition">
                {{ subjectName(course) }}
              </h3>
              <p class="mt-1 text-xs text-slate-500 font-medium">
                👨‍🏫 {{ course.teacher?.name || 'Guru Pengampu' }}
              </p>
            </div>

            <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
              <button
                type="button"
                class="flex-1 py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-xs"
                @click="openCourseDrawer(course)"
              >
                <span>📖</span>
                <span>Buka Materi (Drawer)</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ============================================================== -->
      <!-- TAB 2: TUGAS & UJIAN CBT                                       -->
      <!-- ============================================================== -->
      <div v-else-if="activeTab === 'tasks'">
        <div v-if="tasks.length === 0" class="p-8 text-center text-xs text-slate-400">
          Tidak ada tugas atau ujian yang aktif saat ini.
        </div>
        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="task in tasks"
            :key="task.id"
            class="rounded-2xl border border-slate-200 p-5 bg-white shadow-xs flex flex-col justify-between hover:border-indigo-300 transition"
          >
            <div>
              <div class="flex items-center justify-between gap-2">
                <span class="px-2 py-0.5 rounded-lg text-[10px] font-black bg-indigo-50 text-indigo-800 border border-indigo-200">
                  {{ task.course_name }}
                </span>
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                  :class="submittedQuizIds.has(task.id) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                >
                  {{ submittedQuizIds.has(task.id) ? '✓ Selesai' : 'Belum Dikerjakan' }}
                </span>
              </div>

              <h4 class="mt-2.5 text-sm font-black text-slate-900">{{ task.title }}</h4>
              <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ task.instructions || 'Tidak ada instruksi khusus.' }}</p>
              <p class="text-[11px] font-mono text-slate-400 mt-2">
                Durasi: {{ task.duration_minutes || 60 }}m • Skor Maks: {{ task.max_score }}
              </p>
            </div>

            <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between">
              <NuxtLink
                :to="`/student/quizzes/${task.id}/exam`"
                class="w-full py-2 px-3 rounded-xl text-center text-xs font-bold transition shadow-xs flex items-center justify-center gap-1.5"
                :class="submittedQuizIds.has(task.id)
                  ? 'bg-slate-100 hover:bg-slate-200 text-slate-700'
                  : 'bg-indigo-600 hover:bg-indigo-700 text-white'"
              >
                <span>{{ submittedQuizIds.has(task.id) ? 'Lihat Hasil Ujian' : '⚡ Mulai Ujian CBT Sekarang' }}</span>
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>

      <!-- ============================================================== -->
      <!-- TAB 3: JADWAL MINGGUAN                                         -->
      <!-- ============================================================== -->
      <div v-else-if="activeTab === 'schedule'">
        <div v-if="schedules.length === 0" class="p-8 text-center text-xs text-slate-400">
          Belum ada jadwal mingguan terdaftar.
        </div>
        <div v-else class="space-y-4">
          <div
            v-for="dayKey in ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']"
            :key="dayKey"
            class="border border-slate-200 rounded-2xl overflow-hidden"
          >
            <div class="bg-slate-50 px-4 py-2.5 border-b border-slate-200 flex items-center justify-between">
              <span class="text-xs font-black text-slate-800 uppercase tracking-wide">
                {{ dayLabels[dayKey] }}
              </span>
              <span class="text-[11px] font-bold text-slate-400">
                {{ schedules.filter(s => s.day === dayKey).length }} Pelajaran
              </span>
            </div>

            <div class="divide-y divide-slate-100 bg-white">
              <div
                v-for="sched in schedules.filter(s => s.day === dayKey)"
                :key="sched.id"
                class="p-3.5 flex items-center justify-between gap-3 hover:bg-slate-50/60 transition"
              >
                <div class="flex items-center gap-3">
                  <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 text-xs font-mono font-bold border border-emerald-200/80">
                    {{ sched.start_time }} - {{ sched.end_time }}
                  </span>
                  <div>
                    <p class="text-xs font-bold text-slate-900">{{ subjectName(sched.course) }}</p>
                    <p class="text-[11px] text-slate-500">Ruang: {{ sched.room_number || 'Kelas' }} • {{ sched.course?.teacher?.name || 'Guru Pengampu' }}</p>
                  </div>
                </div>
                <button
                  type="button"
                  class="px-2.5 py-1 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-700 text-[11px] font-bold transition"
                  @click="openCourseDrawer(sched.course)"
                >
                  Materi ↗
                </button>
              </div>
              <div v-if="schedules.filter(s => s.day === dayKey).length === 0" class="p-3 text-xs text-slate-400 italic">
                Tidak ada pelajaran pada hari ini.
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ============================================================== -->
      <!-- TAB 4: RAPOR NILAI KKTP                                        -->
      <!-- ============================================================== -->
      <div v-else-if="activeTab === 'grades'">
        <div v-if="!gradesData" class="p-8 text-center text-xs text-slate-400">
          Memuat transkrip nilai KKTP...
        </div>
        <div v-else class="space-y-4">
          <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <p class="text-xs font-bold text-emerald-900">Rata-rata Nilai Kumulatif Anda</p>
              <p class="text-2xl font-black text-emerald-700 mt-0.5">{{ gradesData.average_score || 0 }} / 100</p>
            </div>
            <div class="text-xs text-emerald-800 font-medium">
              Standar KKTP MAN 11 Jakarta: <b class="font-black text-emerald-950">≥ 75.00</b>
            </div>
          </div>

          <div class="border border-slate-200 rounded-2xl bg-white overflow-hidden shadow-xs">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-50 text-slate-700 border-b border-slate-200">
                <tr>
                  <th class="p-3 font-bold">Judul Tugas / Ujian</th>
                  <th class="p-3 font-bold text-center">Tanggal Pengumpulan</th>
                  <th class="p-3 font-bold text-center">Skor Perolehan</th>
                  <th class="p-3 font-bold text-center">Status KKTP</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="sub in gradesData.submissions || []" :key="sub.id" class="hover:bg-slate-50/60">
                  <td class="p-3">
                    <p class="font-bold text-slate-900">{{ sub.quiz?.title || 'Tugas Siswa' }}</p>
                    <p class="text-[10px] text-slate-400">{{ sub.quiz?.meet?.course?.subject?.name || '' }}</p>
                  </td>
                  <td class="p-3 text-center text-slate-500 font-mono text-[11px]">
                    {{ sub.submitted_at ? sub.submitted_at.substring(0, 10) : '-' }}
                  </td>
                  <td class="p-3 text-center font-black text-slate-900">
                    {{ sub.score !== null ? sub.score : '-' }}
                  </td>
                  <td class="p-3 text-center">
                    <span
                      v-if="sub.score !== null"
                      class="px-2.5 py-0.5 rounded-full text-[10px] font-bold"
                      :class="Number(sub.score) >= 75 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                    >
                      {{ Number(sub.score) >= 75 ? '✓ Tuntas' : 'Perlu Remedial' }}
                    </span>
                    <span v-else class="text-slate-400 text-[11px]">-</span>
                  </td>
                </tr>
                <tr v-if="!gradesData.submissions || gradesData.submissions.length === 0">
                  <td colspan="4" class="p-6 text-center text-slate-400 italic">Belum ada riwayat nilai tercatat.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ============================================================== -->
      <!-- TAB 5: REKAP PRESENSI                                          -->
      <!-- ============================================================== -->
      <div v-else-if="activeTab === 'attendance'">
        <div v-if="!attendanceSummary" class="p-8 text-center text-xs text-slate-400">
          Memuat rekapitulasi kehadiran...
        </div>
        <div v-else class="space-y-4">
          <div class="grid gap-3 sm:grid-cols-4">
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-center">
              <p class="text-xs font-bold text-emerald-800">Hadir (H)</p>
              <p class="text-2xl font-black text-emerald-700 mt-1">{{ attendanceSummary.stats?.H || 0 }}</p>
            </div>
            <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 text-center">
              <p class="text-xs font-bold text-blue-800">Sakit (S)</p>
              <p class="text-2xl font-black text-blue-700 mt-1">{{ attendanceSummary.stats?.S || 0 }}</p>
            </div>
            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-center">
              <p class="text-xs font-bold text-amber-800">Izin (I)</p>
              <p class="text-2xl font-black text-amber-700 mt-1">{{ attendanceSummary.stats?.I || 0 }}</p>
            </div>
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-center">
              <p class="text-xs font-bold text-rose-800">Alpa (A)</p>
              <p class="text-2xl font-black text-rose-700 mt-1">{{ attendanceSummary.stats?.A || 0 }}</p>
            </div>
          </div>

          <div class="border border-slate-200 rounded-2xl bg-white overflow-hidden shadow-xs">
            <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 font-bold text-xs text-slate-700">
              Riwayat Presensi per Pertemuan
            </div>
            <div class="divide-y divide-slate-100 max-h-72 overflow-y-auto">
              <div
                v-for="rec in attendanceSummary.history || []"
                :key="rec.id"
                class="p-3 px-4 flex items-center justify-between text-xs hover:bg-slate-50/60"
              >
                <div>
                  <p class="font-bold text-slate-900">{{ rec.meet?.title || 'Pertemuan' }}</p>
                  <p class="text-[10px] text-slate-400">{{ rec.meet?.course?.subject?.name || '' }} • {{ rec.created_at ? rec.created_at.substring(0, 10) : '' }}</p>
                </div>
                <span
                  class="px-2.5 py-1 rounded-lg text-[10px] font-black"
                  :class="
                    rec.status === 'H' ? 'bg-emerald-100 text-emerald-800' :
                    rec.status === 'S' ? 'bg-blue-100 text-blue-800' :
                    rec.status === 'I' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800'
                  "
                >
                  Status: {{ rec.status }}
                </span>
              </div>
              <div v-if="!attendanceSummary.history || attendanceSummary.history.length === 0" class="p-6 text-center text-slate-400 italic">
                Belum ada riwayat kehadiran pertemuan.
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 5. SLIDE-OVER DRAWER: MODUL MATERI & PERTEMUAN (ZERO PAGE NAVIGATION) -->
    <SlideOver
      v-model="drawerOpen"
      :title="selectedCourse ? subjectName(selectedCourse) : 'Modul Pembelajaran'"
      :subtitle="selectedCourse ? className(selectedCourse) : ''"
      width="max-w-xl"
    >
      <div v-if="drawerLoading" class="p-8 text-center text-xs text-slate-400">
        Memuat modul & sesi pertemuan...
      </div>
      <div v-else-if="selectedCourseMeets.length === 0" class="p-8 text-center text-xs text-slate-400">
        Belum ada materi pembelajaran yang diunggah oleh guru pada kelas ini.
      </div>
      <div v-else class="space-y-4">
        <div
          v-for="meet in selectedCourseMeets"
          :key="meet.id"
          class="border border-slate-200 rounded-2xl p-4 bg-white shadow-xs space-y-3"
        >
          <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-2">
            <span class="px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-800 text-[11px] font-black border border-emerald-200">
              Pertemuan {{ meet.meeting_number }}
            </span>
            <span class="text-[10px] font-bold text-slate-400 uppercase">
              {{ meet.type || 'offline' }}
            </span>
          </div>

          <h4 class="text-sm font-black text-slate-900">{{ meet.title }}</h4>
          <p v-if="meet.description" class="text-xs text-slate-600 leading-relaxed">{{ meet.description }}</p>

          <!-- Materials list inside meet -->
          <div v-if="meet.materials && meet.materials.length > 0" class="space-y-1.5 pt-1">
            <p class="text-[11px] font-bold text-slate-700">📄 File & Modul Terlampir:</p>
            <div
              v-for="mat in meet.materials"
              :key="mat.id"
              class="p-2 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-between text-xs"
            >
              <span class="font-medium text-slate-800 truncate">{{ mat.title }}</span>
              <a
                v-if="mat.file_path"
                :href="`${storageBase}/${mat.file_path}`"
                target="_blank"
                class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white font-bold text-[10px] hover:bg-emerald-700 shrink-0 shadow-xs"
              >
                Unduh / Buka ↗
              </a>
              <a
                v-else-if="mat.external_url"
                :href="mat.external_url"
                target="_blank"
                class="px-2.5 py-1 rounded-lg bg-blue-600 text-white font-bold text-[10px] hover:bg-blue-700 shrink-0 shadow-xs"
              >
                Tonton Video ↗
              </a>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <button
          type="button"
          class="w-full py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition"
          @click="drawerOpen = false"
        >
          Tutup Materi (Kembali ke Dashboard)
        </button>
      </template>
    </SlideOver>
  </div>
</template>
