<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

definePageMeta({ layout: 'student', middleware: 'auth' })

const { user, logout } = useAuth()
const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const loading = ref(true)
const schedules = ref<any[]>([])
const courses = ref<any[]>([])
const submissions = ref<any[]>([])
const tasks = ref<any[]>([])

const api = <T>(path: string) => $fetch<T>(`${apiBase}${path}`, {
  headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
})

const list = (response: any) => response?.data || response || []
const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']
const todayName = computed(() => dayNames[new Date().getDay()])
const todaySchedules = computed(() => schedules.value.filter(schedule => schedule.day === todayName.value))
const submittedQuizIds = computed(() => new Set(submissions.value.filter(item => item.submitted_at).map(item => item.quiz_id)))
const pendingTasks = computed(() => tasks.value.filter(task => !submittedQuizIds.value.has(task.id)).length)
const submittedTasks = computed(() => submissions.value.filter(item => item.submitted_at && item.status !== 'graded').length)
const gradedTasks = computed(() => submissions.value.filter(item => item.status === 'graded').length)
const firstName = computed(() => (user.value?.name || user.value?.username || 'Siswa').split(' ')[0])

const subjectName = (course: any) => course?.subject?.name || 'Mata pelajaran'
const className = (course: any) => course?.class?.class_name?.name || course?.class?.className?.name || 'Kelas terdaftar'

onMounted(async () => {
  try {
    const [scheduleResponse, courseResponse, submissionResponse] = await Promise.all([
      api<any>('/schedules?my_schedules=true&all=true'),
      api<any>('/courses?my_courses=true&all=true'),
      api<any>('/submissions?my_submissions=true')
    ])
    schedules.value = list(scheduleResponse)
    courses.value = list(courseResponse)
    submissions.value = list(submissionResponse)
    const courseDetails = await Promise.all(courses.value.map(course => api<any>(`/courses/${course.id}`)))
    tasks.value = courseDetails.flatMap(course =>
      (course?.meets || []).flatMap((meet: any) => (meet.quizzes || []).map((quiz: any) => ({ ...quiz, meet })))
    )
  } catch (error) {
    console.error('Gagal memuat dashboard siswa:', error)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 pb-12 font-sans text-slate-800">
    <header class="sticky top-0 z-30 border-b border-slate-100 bg-white shadow-sm">
      <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <NuxtLink to="/student/dashboard" class="flex items-center gap-3">
          <img src="/logo.png" alt="Logo MAN 11 Jakarta" class="h-10 w-10 object-contain" />
          <span class="font-bold tracking-tight">Portal Siswa</span>
        </NuxtLink>
        <div class="flex items-center gap-3">
          <span class="hidden text-right text-xs sm:block"><b class="block text-slate-800">{{ user?.name || user?.username }}</b><span class="text-slate-400">SISWA</span></span>
          <button @click="logout" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600">Keluar</button>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-7xl space-y-6 px-4 pt-8 sm:px-6 lg:px-8">
      <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-900 via-emerald-800 to-slate-900 p-6 text-white shadow-xl sm:p-8">
        <div class="absolute -right-12 -top-12 h-64 w-64 rounded-full bg-emerald-400/10 blur-2xl" />
        <div class="relative flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
          <div><span class="rounded-full border border-emerald-400/30 bg-emerald-500/20 px-3 py-1 text-[11px] font-bold text-emerald-100">PORTAL PEMBELAJARAN SISWA</span><h1 class="mt-4 text-2xl font-extrabold sm:text-3xl">Selamat datang, {{ firstName }}!</h1><p class="mt-1 text-sm text-emerald-100/80">Pantau jadwal, kelas, dan tugasmu dalam satu tempat.</p></div>
          <NuxtLink to="/elearning" class="rounded-xl bg-white/10 px-4 py-3 text-center text-xs font-bold ring-1 ring-white/15 transition hover:bg-white/20">Buka E-Learning →</NuxtLink>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kelas terdaftar</p><p class="mt-2 text-3xl font-extrabold text-slate-800">{{ loading ? '…' : courses.length }}</p><p class="mt-1 text-xs text-slate-500">Mata pelajaran aktif</p></div>
        <div class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Perlu dikumpulkan</p><p class="mt-2 text-3xl font-extrabold text-slate-800">{{ loading ? '…' : pendingTasks }}</p><p class="mt-1 text-xs text-slate-500">Tugas belum dikirim</p></div>
        <div class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Tugas selesai</p><p class="mt-2 text-3xl font-extrabold text-slate-800">{{ loading ? '…' : submittedTasks + gradedTasks }}</p><p class="mt-1 text-xs text-slate-500">{{ gradedTasks }} sudah dinilai</p></div>
      </section>

      <section class="grid gap-6 lg:grid-cols-5">
        <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm lg:col-span-3"><div class="flex items-center justify-between"><div><h2 class="font-bold text-slate-800">Jadwal Hari Ini</h2><p class="mt-1 text-xs text-slate-400">Kegiatan belajar {{ todaySchedules.length ? 'yang dijadwalkan' : 'belum dijadwalkan' }} hari ini</p></div><NuxtLink to="/admin/schedules" class="text-xs font-semibold text-emerald-600 hover:underline">Lihat jadwal</NuxtLink></div><div v-if="loading" class="mt-5 text-sm text-slate-400">Memuat jadwal…</div><div v-else-if="todaySchedules.length" class="mt-5 divide-y divide-slate-100"><div v-for="schedule in todaySchedules" :key="schedule.id" class="flex items-center gap-4 py-4"><b class="w-24 text-sm text-emerald-700">{{ schedule.start_time }}–{{ schedule.end_time }}</b><div><p class="text-sm font-bold">{{ subjectName(schedule.course) }}</p><p class="text-xs text-slate-500">{{ className(schedule.course) }} · {{ schedule.room_number || 'Ruang belum ditentukan' }}</p></div></div></div><div v-else class="mt-5 rounded-2xl bg-slate-50 p-6 text-center text-sm text-slate-400">Tidak ada jadwal pelajaran hari ini.</div></div>
        <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm lg:col-span-2"><h2 class="font-bold text-slate-800">Status Tugas</h2><p class="mt-1 text-xs text-slate-400">Ringkasan pengumpulan tugas Anda</p><div v-if="loading" class="mt-5 text-sm text-slate-400">Memuat status…</div><div v-else class="mt-5 space-y-3"><div class="flex justify-between rounded-xl bg-amber-50 p-3 text-xs"><span class="font-semibold text-amber-900">Belum dikumpulkan</span><b class="text-amber-700">{{ pendingTasks }}</b></div><div class="flex justify-between rounded-xl bg-blue-50 p-3 text-xs"><span class="font-semibold text-blue-900">Menunggu penilaian</span><b class="text-blue-700">{{ submittedTasks }}</b></div><div class="flex justify-between rounded-xl bg-emerald-50 p-3 text-xs"><span class="font-semibold text-emerald-900">Sudah dinilai</span><b class="text-emerald-700">{{ gradedTasks }}</b></div></div></div>
      </section>

      <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm"><div class="flex items-center justify-between"><div><h2 class="font-bold text-slate-800">Kelas Terdaftar</h2><p class="mt-1 text-xs text-slate-400">Pilih kelas untuk membaca materi dan mengumpulkan tugas.</p></div><NuxtLink to="/elearning" class="text-xs font-semibold text-emerald-600 hover:underline">Semua kelas</NuxtLink></div><div v-if="loading" class="mt-5 text-sm text-slate-400">Memuat kelas…</div><div v-else-if="courses.length" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"><NuxtLink v-for="course in courses" :key="course.id" :to="`/elearning/courses/${course.id}`" class="rounded-2xl border border-slate-200 p-4 transition hover:border-emerald-300 hover:bg-emerald-50/40"><p class="text-sm font-bold text-slate-800">{{ subjectName(course) }}</p><p class="mt-1 text-xs text-slate-500">{{ className(course) }}</p><p class="mt-4 text-xs font-semibold text-emerald-700">{{ course.meets_count || 0 }} pertemuan · Buka kelas →</p></NuxtLink></div><div v-else class="mt-5 rounded-2xl bg-slate-50 p-6 text-center text-sm text-slate-400">Belum ada kelas yang terdaftar.</div></section>
    </main>
  </div>
</template>
