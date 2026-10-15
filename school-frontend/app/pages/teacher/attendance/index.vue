<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import QRCode from 'qrcode'
import AppButton from '~/components/ui/AppButton.vue'
import AppBadge from '~/components/ui/AppBadge.vue'
import AppCard from '~/components/ui/AppCard.vue'
import AppSkeleton from '~/components/ui/AppSkeleton.vue'
import AppEmptyState from '~/components/ui/AppEmptyState.vue'

definePageMeta({ layout: 'teacher', middleware: 'auth' })

const route = useRoute()
const router = useRouter()
const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'

// State
const loading = ref(true)
const courses = ref<any[]>([])
const selectedCourseId = ref<number | null>(null)
const sessions = ref<any[]>([])
const sessionsLoading = ref(false)

// Active Working Session State
const selectedSessionId = ref<number | null>(null)
const sessionDetail = ref<any>(null)
const sessionDetailLoading = ref(false)
const studentRoster = ref<any[]>([])
const rosterStats = reactive({
  total: 0,
  H: 0,
  S: 0,
  I: 0,
  A: 0,
  unrecorded: 0
})

// Dynamic 15-Minute QR Projector State
const isQrModalOpen = ref(false)
const qrDataUrl = ref('')
const qrTimerSeconds = ref(15 * 60) // 15 minutes = 900 seconds
let qrInterval: any = null
const currentSlot = ref(Math.floor(Date.now() / 1000 / 900))

// Sub-bab Meets for Topic Assignment
const meetsList = ref<any[]>([])

// Notification
const toastMsg = ref('')
const showToast = (msg: string) => {
  toastMsg.value = msg
  setTimeout(() => {
    toastMsg.value = ''
  }, 4000)
}

// Helpers
const subjectName = (course: any) => course?.subject?.name || 'Mata Pelajaran'
const className = (course: any) => course?.class?.class_name?.name || course?.class?.className?.name || 'Kelas'

const selectedCourse = computed(() => {
  return courses.value.find(c => c.id === selectedCourseId.value) || null
})

// Indo Date Formatting Helper
const formatIndoDate = (dateStr: string) => {
  if (!dateStr) return '-'
  try {
    const parts = dateStr.split('-').map(Number)
    if (parts.length === 3) {
      const d = new Date(parts[0], parts[1] - 1, parts[2])
      return d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' })
    }
    return dateStr
  } catch {
    return dateStr
  }
}

// Today Active Session Helper
const todayDateString = computed(() => new Date().toISOString().split('T')[0])
const todayActiveSession = computed(() => {
  return sessions.value.find((s: any) => s.date === todayDateString.value && s.status === 'open') || null
})

// Search & Filter State for Sessions List (Handling 16-36+ meetings smoothly)
const sessionSearchQuery = ref('')
const sessionStatusFilter = ref<'all' | 'open' | 'closed'>('all')

const filteredSessions = computed(() => {
  let list = sessions.value
  if (sessionStatusFilter.value !== 'all') {
    list = list.filter((s: any) => s.status === sessionStatusFilter.value)
  }
  if (sessionSearchQuery.value.trim()) {
    const q = sessionSearchQuery.value.toLowerCase().trim()
    list = list.filter((s: any) => 
      s.date?.includes(q) ||
      formatIndoDate(s.date).toLowerCase().includes(q) ||
      s.active_meet?.title?.toLowerCase().includes(q) ||
      String(s.active_meet?.meeting_number || '').includes(q)
    )
  }
  return list
})

// Search & Filter State for Student Attendance Roster
const studentSearchQuery = ref('')
const filteredRoster = computed(() => {
  if (!studentSearchQuery.value.trim()) return studentRoster.value
  const q = studentSearchQuery.value.toLowerCase().trim()
  return studentRoster.value.filter((s: any) => 
    s.name?.toLowerCase().includes(q) ||
    s.nisn?.toLowerCase().includes(q) ||
    s.nis?.toLowerCase().includes(q)
  )
})

// Formatted Countdown MM:SS
const formattedCountdown = computed(() => {
  const m = Math.floor(qrTimerSeconds.value / 60)
  const s = qrTimerSeconds.value % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

// -------------------------------------------------------------
// Data Fetching
// -------------------------------------------------------------
const fetchCourses = async () => {
  loading.value = true
  try {
    const res: any = await $fetch(`${apiBase}/courses?my_courses=true&all=true`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    courses.value = res?.data || res || []

    if (route.query.course_id) {
      selectedCourseId.value = Number(route.query.course_id)
    } else if (courses.value.length > 0) {
      selectedCourseId.value = courses.value[0].id
    }
  } catch (err) {
    console.error('Gagal memuat mata pelajaran guru:', err)
  } finally {
    loading.value = false
  }
}

const fetchSessions = async () => {
  if (!selectedCourseId.value) return
  sessionsLoading.value = true
  try {
    const [sessRes, meetsRes] = await Promise.all([
      $fetch<any>(`${apiBase}/kbm-sessions?course_id=${selectedCourseId.value}&per_page=100`, {
        headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
      }),
      $fetch<any>(`${apiBase}/meets?course_id=${selectedCourseId.value}&paginate=false`, {
        headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
      })
    ])

    sessions.value = sessRes?.data?.data || sessRes?.data || []
    meetsList.value = meetsRes?.data || meetsRes || []

    // If session_id in query or open session exists
    if (route.query.session_id) {
      openSessionWorkspace(Number(route.query.session_id))
    } else {
      const openSession = sessions.value.find((s: any) => s.status === 'open')
      if (openSession && !selectedSessionId.value) {
        openSessionWorkspace(openSession.id)
      } else if (sessions.value.length > 0 && !selectedSessionId.value) {
        // Auto-select the latest meeting session
        openSessionWorkspace(sessions.value[0].id)
      }
    }
  } catch (err) {
    console.error('Gagal memuat sesi KBM:', err)
  } finally {
    sessionsLoading.value = false
  }
}

// -------------------------------------------------------------
// Session Workspace & Roster Management
// -------------------------------------------------------------
const openSessionWorkspace = async (sessionId: number) => {
  selectedSessionId.value = sessionId
  sessionDetailLoading.value = true
  try {
    const res: any = await $fetch(`${apiBase}/kbm-sessions/${sessionId}`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    sessionDetail.value = res?.data?.session || res?.data || null
    studentRoster.value = res?.data?.roster || []

    if (res?.data?.stats) {
      Object.assign(rosterStats, res.data.stats)
    }
  } catch (err) {
    console.error('Gagal memuat detail sesi presensi:', err)
  } finally {
    sessionDetailLoading.value = false
  }
}

const closeSessionWorkspace = () => {
  selectedSessionId.value = null
  sessionDetail.value = null
  studentRoster.value = []
  stopQrTimer()
}

// Buka Sesi Hari Ini Baru
const openingTodaySession = ref(false)
const openTodaySession = async () => {
  if (!selectedCourseId.value) return
  openingTodaySession.value = true
  try {
    const res: any = await $fetch(`${apiBase}/kbm-sessions/open`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
      body: {
        course_id: selectedCourseId.value,
        date: new Date().toISOString().split('T')[0],
        allow_self_checkin: true,
      }
    })
    showToast('Sesi KBM tatap muka hari ini berhasil dibuka!')
    await fetchSessions()
    if (res?.data?.id) {
      await openSessionWorkspace(res.data.id)
    }
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal membuka sesi KBM hari ini.')
  } finally {
    openingTodaySession.value = false
  }
}

// Set Sub-bab Topic
const savingTopic = ref(false)
const updateActiveTopic = async (meetId: string | number) => {
  if (!selectedSessionId.value) return
  savingTopic.value = true
  try {
    await $fetch(`${apiBase}/kbm-sessions/${selectedSessionId.value}/set-topic`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
      body: {
        active_meet_id: meetId ? Number(meetId) : null,
      }
    })
    showToast('Topik pembelajaran sub-bab berhasil ditautkan ke sesi ini.')
    await openSessionWorkspace(selectedSessionId.value)
    await fetchSessions()
  } catch (e: any) {
    alert(e?.data?.message || 'Gagal menyimpan topik pembelajaran.')
  } finally {
    savingTopic.value = false
  }
}

// -------------------------------------------------------------
// Manual Roster Quick Actions
// -------------------------------------------------------------
const savingAttendance = ref(false)

// 1-Klik Hadirkan Semua Siswa
const markAllPresent = async () => {
  if (!selectedSessionId.value) return
  if (!confirm('Tandai SELURUH siswa di kelas ini sebagai HADIR (H)?')) return

  savingAttendance.value = true
  try {
    const res: any = await $fetch(`${apiBase}/kbm-sessions/${selectedSessionId.value}/batch-attendance`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
      body: { mark_all: 'H' }
    })
    showToast(res?.message || 'Seluruh siswa berhasil ditandai Hadir!')
    await openSessionWorkspace(selectedSessionId.value)
  } catch (e: any) {
    alert(e?.data?.message || 'Gagal mengubah presensi massal.')
  } finally {
    savingAttendance.value = false
  }
}

// Toggle status H/S/I/A per anak
const updateStudentStatus = async (student: any, status: 'H' | 'S' | 'I' | 'A') => {
  if (!selectedSessionId.value) return
  const oldStatus = student.status
  student.status = status // Optimistic update

  try {
    await $fetch(`${apiBase}/kbm-sessions/${selectedSessionId.value}/batch-attendance`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
      body: {
        attendances: [
          {
            student_id: student.student_id,
            status: status,
            notes: student.notes || null
          }
        ]
      }
    })
    // Recount local stats
    recalculateStats()
  } catch (e: any) {
    student.status = oldStatus // Revert on failure
    alert(e?.data?.message || 'Gagal memperbarui status presensi siswa.')
  }
}

const recalculateStats = () => {
  const counts = { total: studentRoster.value.length, H: 0, S: 0, I: 0, A: 0, unrecorded: 0 }
  studentRoster.value.forEach(s => {
    if (s.status === 'H') counts.H++
    else if (s.status === 'S') counts.S++
    else if (s.status === 'I') counts.I++
    else if (s.status === 'A') counts.A++
    else counts.unrecorded++
  })
  Object.assign(rosterStats, counts)
}

// Tutup Sesi KBM
const closingSession = ref(false)
const closeCurrentSession = async () => {
  if (!selectedSessionId.value) return
  if (!confirm('Tutup sesi KBM ini? Siswa yang belum absen akan otomatis tercatat sebagai Alpa (A).')) return

  closingSession.value = true
  try {
    await $fetch(`${apiBase}/kbm-sessions/${selectedSessionId.value}/close`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
      body: { mark_unrecorded_as_alpa: true }
    })
    showToast('Sesi KBM telah ditutup dan presensi selesai direkap.')
    await openSessionWorkspace(selectedSessionId.value)
    await fetchSessions()
  } catch (e: any) {
    alert(e?.data?.message || 'Gagal menutup sesi KBM.')
  } finally {
    closingSession.value = false
  }
}

// -------------------------------------------------------------
// Dynamic 15-Minute Client-Side QR Projector
// -------------------------------------------------------------
const generateDynamicQr = async () => {
  if (!sessionDetail.value) return
  // Calculate slot based on 15-minute window (900 seconds)
  currentSlot.value = Math.floor(Date.now() / 1000 / 900)

  // Client-side payload without storing tokens in DB
  const payload = JSON.stringify({
    session_id: sessionDetail.value.id,
    course_id: sessionDetail.value.course_id,
    time_slot: currentSlot.value,
    date: sessionDetail.value.date,
  })

  try {
    qrDataUrl.value = await QRCode.toDataURL(payload, {
      width: 480,
      margin: 2,
      color: {
        dark: '#0f172a', // Slate-900
        light: '#ffffff'
      }
    })
  } catch (err) {
    console.error('Gagal generate QR code:', err)
  }
}

const startQrTimer = () => {
  qrTimerSeconds.value = 15 * 60
  generateDynamicQr()

  if (qrInterval) clearInterval(qrInterval)
  qrInterval = setInterval(() => {
    if (qrTimerSeconds.value > 0) {
      qrTimerSeconds.value--
    } else {
      // 15-minute countdown expired: reload new QR code automatically!
      qrTimerSeconds.value = 15 * 60
      generateDynamicQr()
      showToast('QR Code diperbarui otomatis untuk interval 15 menit berikutnya.')
    }
  }, 1000)
}

const stopQrTimer = () => {
  if (qrInterval) {
    clearInterval(qrInterval)
    qrInterval = null
  }
}

const openQrProjector = () => {
  isQrModalOpen.value = true
  startQrTimer()
}

const closeQrProjector = () => {
  isQrModalOpen.value = false
  stopQrTimer()
}

const forceReloadQrNow = () => {
  startQrTimer()
  showToast('QR Code baru berhasil di-generate!')
}

// Lifecycle Hooks
onMounted(async () => {
  await fetchCourses()
})

onUnmounted(() => {
  stopQrTimer()
})

watch(selectedCourseId, (newId) => {
  if (newId) {
    router.replace({ query: { ...route.query, course_id: String(newId) } })
    closeSessionWorkspace()
    fetchSessions()
  }
})
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8 font-sans">
    <div class="mx-auto max-w-7xl space-y-5">
      <!-- 1. CLEAN PAGE HEADER -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200/80">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-1">
            <NuxtLink to="/teacher/dashboard" class="hover:text-emerald-700 transition">Dashboard</NuxtLink>
            <span>/</span>
            <span class="text-slate-900 font-bold">Presensi Kelas</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
            Presensi Tatap Muka
          </h1>
          <p class="mt-0.5 text-xs text-slate-500">
            Pencatatan kehadiran sesi tatap muka, proyektor QR dinamis 15 menit, dan rekap per pertemuan.
          </p>
        </div>

        <!-- Quick Back & Cockpit link -->
        <div class="flex items-center gap-2">
          <NuxtLink
            v-if="selectedCourseId"
            :to="`/teacher/classes?course_id=${selectedCourseId}`"
            class="px-3.5 py-1.5 text-xs font-bold text-slate-700 hover:text-emerald-800 bg-white border border-slate-200 hover:border-emerald-300 rounded-xl transition shadow-2xs flex items-center gap-1.5"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            <span>Buka Ruang Belajar Kelas</span>
          </NuxtLink>
        </div>
      </div>

      <!-- 2. COURSE / KELAS SELECTOR & TODAY'S ACTION STRIP -->
      <div class="bg-white border border-slate-200/90 rounded-2xl p-3.5 sm:p-4 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-3">
        <!-- Selector -->
        <div class="flex items-center gap-3 flex-1">
          <span class="text-xs font-bold text-slate-500 shrink-0 hidden sm:inline">Kelas:</span>
          <select
            v-model="selectedCourseId"
            class="w-full sm:max-w-md px-3.5 py-2 rounded-xl border border-slate-300 bg-white text-xs font-bold text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
          >
            <option v-for="c in courses" :key="c.id" :value="c.id">
              {{ subjectName(c) }} &bull; {{ className(c) }} ({{ c.class?.capacity || c.class?.students_count || 36 }} Siswa)
            </option>
          </select>
        </div>

        <!-- Today Session Action CTA -->
        <div class="flex items-center gap-2 shrink-0">
          <!-- If today's active session is open -->
          <button
            v-if="todayActiveSession"
            type="button"
            class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-2xs flex items-center gap-2"
            @click="openSessionWorkspace(todayActiveSession.id)"
          >
            <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
            <span>Sesi Hari Ini Sedang Aktif (Buka)</span>
          </button>

          <!-- If no active session today -->
          <button
            v-else
            type="button"
            :disabled="openingTodaySession"
            class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-emerald-600 text-white text-xs font-bold transition shadow-2xs flex items-center gap-1.5 disabled:opacity-50"
            @click="openTodaySession"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span>{{ openingTodaySession ? 'Membuka...' : '+ Buka Sesi Hari Ini' }}</span>
          </button>
        </div>
      </div>

      <!-- Toast Notification -->
      <div
        v-if="toastMsg"
        class="p-3 bg-emerald-600 text-white rounded-xl text-xs font-bold flex items-center justify-between shadow-sm transition"
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

      <!-- 3. MAIN WORKSPACE: TWO-COLUMN LAYOUT -->
      <div class="grid gap-5 lg:grid-cols-12 items-start">
        <!-- LEFT COLUMN: SESSIONS MASTER LIST (Anticipating many sessions) -->
        <div class="lg:col-span-4 bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-2xs flex flex-col space-y-3">
          <!-- Header & Count -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">
                Daftar Pertemuan
              </h2>
              <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[10px] font-bold">
                {{ sessions.length }}
              </span>
            </div>
            <button
              v-if="selectedSessionId"
              type="button"
              class="text-[11px] font-semibold text-slate-400 hover:text-slate-700"
              @click="closeSessionWorkspace"
            >
              Tutup Pilihan
            </button>
          </div>

          <!-- Search & Filter Controls -->
          <div class="space-y-2">
            <!-- Search Input -->
            <div class="relative">
              <span class="absolute inset-y-0 left-2.5 flex items-center text-slate-400">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </span>
              <input
                v-model="sessionSearchQuery"
                type="text"
                placeholder="Cari tanggal / topik pertemuan..."
                class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl bg-slate-50 border border-slate-200 focus:outline-hidden focus:bg-white focus:ring-1 focus:ring-emerald-500"
              />
            </div>

            <!-- Filter Pills -->
            <div class="flex items-center gap-1 overflow-x-auto pb-0.5">
              <button
                type="button"
                class="px-2.5 py-1 rounded-lg text-[11px] transition whitespace-nowrap"
                :class="sessionStatusFilter === 'all' ? 'bg-slate-900 text-white font-bold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-medium'"
                @click="sessionStatusFilter = 'all'"
              >
                Semua
              </button>
              <button
                type="button"
                class="px-2.5 py-1 rounded-lg text-[11px] transition whitespace-nowrap flex items-center gap-1"
                :class="sessionStatusFilter === 'open' ? 'bg-emerald-600 text-white font-bold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-medium'"
                @click="sessionStatusFilter = 'open'"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                <span>Aktif</span>
              </button>
              <button
                type="button"
                class="px-2.5 py-1 rounded-lg text-[11px] transition whitespace-nowrap"
                :class="sessionStatusFilter === 'closed' ? 'bg-slate-700 text-white font-bold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-medium'"
                @click="sessionStatusFilter = 'closed'"
              >
                Selesai
              </button>
            </div>
          </div>

          <!-- Scrollable Session Cards (Clean & High Contrast) -->
          <div class="overflow-y-auto max-h-[calc(100vh-320px)] space-y-2 pr-0.5">
            <div v-if="sessionsLoading" class="space-y-2">
              <AppSkeleton v-for="i in 4" :key="i" height="h-20" class="rounded-xl" />
            </div>

            <div v-else-if="filteredSessions.length === 0" class="py-8 text-center text-xs text-slate-400">
              Tidak ada pertemuan yang sesuai filter.
            </div>

            <div
              v-else
              v-for="(sess, sIdx) in filteredSessions"
              :key="sess.id"
              class="p-3 rounded-xl border text-xs transition cursor-pointer flex flex-col justify-between gap-2"
              :class="selectedSessionId === sess.id 
                ? 'bg-emerald-50/50 border-emerald-500 shadow-2xs ring-1 ring-emerald-500/20' 
                : 'bg-white border-slate-200 hover:border-slate-300 hover:bg-slate-50/50'"
              @click="openSessionWorkspace(sess.id)"
            >
              <!-- Card Header -->
              <div class="flex items-center justify-between gap-2">
                <span class="font-bold text-slate-900 flex items-center gap-1.5">
                  <span class="w-4 h-4 rounded-md bg-slate-100 text-slate-600 text-[10px] font-bold flex items-center justify-center shrink-0">
                    {{ sessions.length - sIdx }}
                  </span>
                  <span>{{ formatIndoDate(sess.date) }}</span>
                </span>
                <span
                  class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider shrink-0 flex items-center gap-1"
                  :class="sess.status === 'open' 
                    ? 'bg-emerald-100 text-emerald-800' 
                    : 'bg-slate-100 text-slate-600'"
                >
                  <span v-if="sess.status === 'open'" class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  <span>{{ sess.status === 'open' ? 'Aktif' : 'Selesai' }}</span>
                </span>
              </div>

              <!-- Topic / Sub-bab -->
              <p class="text-[11px] font-semibold text-slate-700 truncate">
                {{ sess.active_meet ? `Sub-bab ${sess.active_meet.meeting_number}: ${sess.active_meet.title}` : 'Sesi Tatap Muka (Materi Fleksibel)' }}
              </p>

              <!-- Attendance Stat Footer -->
              <div class="pt-2 border-t border-slate-100/80 flex items-center justify-between text-[11px] text-slate-500">
                <span class="font-medium">
                  <span class="font-bold text-emerald-700">{{ sess.attendance_stats?.H || 0 }}</span> Hadir
                  <span v-if="(sess.attendance_stats?.S || 0) + (sess.attendance_stats?.I || 0) > 0" class="text-amber-700 font-bold ml-1.5">
                    • {{ (sess.attendance_stats?.S || 0) + (sess.attendance_stats?.I || 0) }} Izin/Sakit
                  </span>
                  <span v-if="(sess.attendance_stats?.A || 0) > 0" class="text-rose-700 font-bold ml-1.5">
                    • {{ sess.attendance_stats?.A }} Alpa
                  </span>
                </span>
                <span class="font-bold text-emerald-700 hover:underline">Detail &rarr;</span>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: SELECTED SESSION ROSTER & WORKSPACE -->
        <div class="lg:col-span-8 space-y-4">
          <!-- Loading State -->
          <div v-if="sessionDetailLoading" class="p-12 text-center text-xs text-slate-400 bg-white border border-slate-200 rounded-2xl">
            Memuat detail roster presensi...
          </div>

          <!-- Empty State (No session selected) -->
          <div v-else-if="!sessionDetail" class="p-12 text-center bg-white border border-slate-200/90 rounded-2xl space-y-3">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
              </svg>
            </div>
            <h3 class="text-sm font-bold text-slate-900">Pilih Pertemuan atau Buka Sesi Hari Ini</h3>
            <p class="text-xs text-slate-500 max-w-sm mx-auto">
              Pilih salah satu sesi pertemuan di panel sebelah kiri untuk melihat absensi, atau buat sesi baru untuk hari ini.
            </p>
            <button
              type="button"
              class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-2xs"
              @click="openTodaySession"
            >
              + Buka Sesi Hari Ini
            </button>
          </div>

          <!-- Active Workspace Container -->
          <div v-else class="space-y-4">
            <!-- 1. SESSION CONTROL HEADER -->
            <div class="p-4 sm:p-5 bg-white border border-slate-200/90 rounded-2xl shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div class="space-y-1.5 min-w-0">
                <div class="flex items-center gap-2">
                  <span
                    class="px-2.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider"
                    :class="sessionDetail.status === 'open' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'"
                  >
                    {{ sessionDetail.status === 'open' ? 'Sesi Aktif' : 'Sesi Selesai' }}
                  </span>
                  <span class="text-xs font-bold text-slate-800">{{ formatIndoDate(sessionDetail.date) }}</span>
                </div>
                
                <h3 class="text-base sm:text-lg font-bold text-slate-900 truncate">
                  {{ sessionDetail.course?.subject?.name }} &bull; {{ sessionDetail.course?.class?.class_name?.name || sessionDetail.course?.class?.className?.name }}
                </h3>

                <!-- Sub-bab Allocation Selector -->
                <div class="flex items-center gap-2 text-xs pt-1">
                  <span class="text-slate-500 font-medium">Topik Sub-bab:</span>
                  <select
                    :value="sessionDetail.active_meet_id || ''"
                    class="px-2.5 py-1 bg-slate-50 hover:bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-800 focus:outline-hidden focus:ring-1 focus:ring-emerald-500 transition"
                    @change="(e: any) => updateActiveTopic(e.target.value)"
                  >
                    <option value="">-- Pilih Materi / Sub-bab --</option>
                    <option v-for="m in meetsList" :key="m.id" :value="m.id">
                      Sub-bab {{ m.meeting_number }}: {{ m.title }}
                    </option>
                  </select>
                </div>
              </div>

              <!-- Top Action Buttons: QR Projector & Close Session -->
              <div class="flex flex-wrap items-center gap-2 self-start md:self-center shrink-0">
                <button
                  type="button"
                  class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-2xs transition flex items-center gap-2"
                  @click="openQrProjector"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                  </svg>
                  <span>Tampilkan QR Code</span>
                </button>

                <button
                  v-if="sessionDetail.status === 'open'"
                  type="button"
                  :disabled="closingSession"
                  class="px-3 py-2 rounded-xl border border-slate-200 hover:border-rose-300 hover:bg-rose-50 text-slate-700 hover:text-rose-700 text-xs font-bold transition flex items-center gap-1.5 disabled:opacity-50"
                  @click="closeCurrentSession"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span>{{ closingSession ? 'Menutup...' : 'Tutup Sesi' }}</span>
                </button>
              </div>
            </div>

            <!-- 2. QUICK RECAP METRIC PILLS -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
              <div class="p-2.5 sm:p-3 bg-white border border-slate-200/80 rounded-xl text-center shadow-2xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Siswa</span>
                <p class="text-lg font-bold text-slate-900 mt-0.5">{{ rosterStats.total }}</p>
              </div>
              <div class="p-2.5 sm:p-3 bg-emerald-50/70 border border-emerald-200/80 rounded-xl text-center shadow-2xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Hadir (H)</span>
                <p class="text-lg font-bold text-emerald-800 mt-0.5">{{ rosterStats.H }}</p>
              </div>
              <div class="p-2.5 sm:p-3 bg-sky-50/70 border border-sky-200/80 rounded-xl text-center shadow-2xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-sky-800">Sakit (S)</span>
                <p class="text-lg font-bold text-sky-800 mt-0.5">{{ rosterStats.S }}</p>
              </div>
              <div class="p-2.5 sm:p-3 bg-amber-50/70 border border-amber-200/80 rounded-xl text-center shadow-2xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800">Izin (I)</span>
                <p class="text-lg font-bold text-amber-800 mt-0.5">{{ rosterStats.I }}</p>
              </div>
              <div class="p-2.5 sm:p-3 bg-rose-50/70 border border-rose-200/80 rounded-xl text-center shadow-2xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-rose-800">Alpa (A)</span>
                <p class="text-lg font-bold text-rose-800 mt-0.5">{{ rosterStats.A }}</p>
              </div>
            </div>

            <!-- 3. STUDENT ATTENDANCE ROSTER -->
            <div class="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-2xs">
              <!-- Toolbar: Search, Filter, 1-Click Mark All -->
              <div class="p-3.5 bg-slate-50/80 border-b border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2 flex-1 max-w-sm">
                  <div class="relative w-full">
                    <span class="absolute inset-y-0 left-2.5 flex items-center text-slate-400">
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                      </svg>
                    </span>
                    <input
                      v-model="studentSearchQuery"
                      type="text"
                      placeholder="Cari nama atau NISN siswa..."
                      class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl bg-white border border-slate-200 focus:outline-hidden focus:ring-1 focus:ring-emerald-500"
                    />
                  </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                  <button
                    type="button"
                    :disabled="savingAttendance"
                    class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-2xs flex items-center gap-1.5 disabled:opacity-50"
                    title="Tandai seluruh siswa sebagai Hadir"
                    @click="markAllPresent"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>1-Klik Hadirkan Semua</span>
                  </button>
                </div>
              </div>

              <!-- Student Rows -->
              <div class="divide-y divide-slate-100 max-h-[480px] overflow-y-auto">
                <div
                  v-for="(student, idx) in filteredRoster"
                  :key="student.student_id"
                  class="p-3 flex items-center justify-between gap-3 hover:bg-slate-50/60 transition text-xs"
                >
                  <div class="flex items-center gap-3 min-w-0">
                    <span class="w-5 text-center text-[11px] font-bold text-slate-400 shrink-0">{{ idx + 1 }}</span>
                    <div class="min-w-0">
                      <p class="font-bold text-slate-900 truncate">{{ student.name }}</p>
                      <p class="text-[10px] text-slate-400 font-mono">
                        {{ student.nisn || student.nis || '-' }}
                        <span v-if="student.checkin_at" class="text-emerald-700 font-bold ml-1">
                          • {{ student.checkin_at.substring(11, 16) }} ({{ student.method === 'qr_scan' ? 'QR' : 'Manual' }})
                        </span>
                      </p>
                    </div>
                  </div>

                  <!-- 4 Action Chips: H, S, I, A -->
                  <div class="flex items-center gap-1 shrink-0">
                    <button
                      type="button"
                      class="w-7 h-7 rounded-lg text-xs font-bold transition border"
                      :class="student.status === 'H' ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-white'"
                      title="Hadir"
                      @click="updateStudentStatus(student, 'H')"
                    >
                      H
                    </button>
                    <button
                      type="button"
                      class="w-7 h-7 rounded-lg text-xs font-bold transition border"
                      :class="student.status === 'S' ? 'bg-sky-600 text-white border-sky-600 shadow-2xs' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-white'"
                      title="Sakit"
                      @click="updateStudentStatus(student, 'S')"
                    >
                      S
                    </button>
                    <button
                      type="button"
                      class="w-7 h-7 rounded-lg text-xs font-bold transition border"
                      :class="student.status === 'I' ? 'bg-amber-600 text-white border-amber-600 shadow-2xs' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-white'"
                      title="Izin"
                      @click="updateStudentStatus(student, 'I')"
                    >
                      I
                    </button>
                    <button
                      type="button"
                      class="w-7 h-7 rounded-lg text-xs font-bold transition border"
                      :class="student.status === 'A' ? 'bg-rose-600 text-white border-rose-600 shadow-2xs' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-white'"
                      title="Alpa"
                      @click="updateStudentStatus(student, 'A')"
                    >
                      A
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================== -->
    <!-- DYNAMIC 15-MINUTE QR CODE PROJECTOR MODAL (FULLSCREEN FRIENDLY) -->
    <!-- ============================================================== -->
    <div
      v-if="isQrModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4 overflow-y-auto"
      @click.self="closeQrProjector"
    >
      <div class="w-full max-w-xl rounded-3xl bg-white p-6 sm:p-7 shadow-2xl border border-slate-100 text-center space-y-5">
        <!-- Header Modal -->
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div class="text-left">
            <span class="px-2.5 py-0.5 rounded-lg bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase tracking-wider">
              Proyektor Presensi QR Dinamis
            </span>
            <h3 class="text-base font-bold text-slate-900 mt-1">
              {{ sessionDetail?.course?.subject?.name }} &bull; {{ sessionDetail?.course?.class?.class_name?.name || sessionDetail?.course?.class?.className?.name }}
            </h3>
          </div>
          <button
            type="button"
            class="p-2 rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition"
            @click="closeQrProjector"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- 15-Minute Countdown Timer Badge -->
        <div class="flex items-center justify-center gap-2.5">
          <div class="px-3.5 py-1.5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs font-bold flex items-center gap-2">
            <span class="relative flex h-2.5 w-2.5">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
            </span>
            <span>Reload QR Otomatis dalam: <span class="font-mono text-sm font-black text-amber-950">{{ formattedCountdown }}</span></span>
          </div>
          <button
            type="button"
            class="p-1.5 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-600 transition"
            title="Perbarui QR Sekarang"
            @click="forceReloadQrNow"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </button>
        </div>

        <!-- Large QR Display -->
        <div class="flex justify-center p-4 bg-slate-50 border border-slate-200 rounded-2xl">
          <img
            v-if="qrDataUrl"
            :src="qrDataUrl"
            alt="QR Code Presensi KBM"
            class="w-64 h-64 sm:w-72 sm:h-72 object-contain rounded-xl shadow-xs"
          />
        </div>

        <!-- Realtime Counter -->
        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between text-xs font-bold text-emerald-900">
          <span>Kehadiran Terkini:</span>
          <span class="text-sm font-black font-mono">{{ rosterStats.H }} / {{ rosterStats.total }} Siswa Hadir</span>
        </div>

        <p class="text-xs text-slate-500 leading-relaxed">
          Arahkan proyektor ke layar ini. Siswa membuka menu presensi di HP masing-masing dan memindai QR code. Kode otomatis diperbarui setiap 15 menit.
        </p>

        <div class="pt-1">
          <button
            type="button"
            class="w-full py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition shadow-xs"
            @click="closeQrProjector"
          >
            Tutup Tampilan Proyektor
          </button>
        </div>
      </div>
    </div>
  </main>
</template>
