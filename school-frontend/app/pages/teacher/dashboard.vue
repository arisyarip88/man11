<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

definePageMeta({ layout: 'teacher', middleware: 'auth' })

const { user, logout } = useAuth()
const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const loading = ref(true)
const schedules = ref<any[]>([])
const courses = ref<any[]>([])
const pendingSubmissions = ref<any[]>([])

const api = <T>(path: string) => $fetch<T>(`${apiBase}${path}`, { headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' } })
const list = (response: any) => response?.data || response || []
const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']
const todaySchedules = computed(() => schedules.value.filter(item => item.day === days[new Date().getDay()]))
const teacherName = computed(() => user.value?.name || user.value?.username || 'Guru')
const subjectName = (course: any) => course?.subject?.name || 'Mata pelajaran'
const className = (course: any) => course?.class?.class_name?.name || course?.class?.className?.name || 'Kelas'

onMounted(async () => {
  try {
    const [scheduleResponse, courseResponse] = await Promise.all([
      api<any>('/schedules?my_schedules=true&all=true'),
      api<any>('/courses?my_courses=true&all=true')
    ])
    schedules.value = list(scheduleResponse)
    courses.value = list(courseResponse)
    const details = await Promise.all(courses.value.map(course => api<any>(`/courses/${course.id}`)))
    const quizIds = new Set(details.flatMap(course => (course?.meets || []).flatMap((meet: any) => (meet.quizzes || []).map((quiz: any) => quiz.id))))
    const submissions = list(await api<any>('/submissions'))
    pendingSubmissions.value = submissions.filter((item: any) => quizIds.has(item.quiz_id) && item.status !== 'graded')
  } catch (error) {
    console.error('Gagal memuat dashboard guru:', error)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 pb-12 font-sans text-slate-800">
    <header class="sticky top-0 z-30 border-b border-slate-100 bg-white shadow-sm"><div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"><NuxtLink to="/teacher/dashboard" class="flex items-center gap-3"><img src="/logo.png" alt="Logo MAN 11 Jakarta" class="h-10 w-10 object-contain" /><span class="font-bold tracking-tight">Ruang Guru</span></NuxtLink><div class="flex items-center gap-3"><span class="hidden text-right text-xs sm:block"><b class="block">{{ teacherName }}</b><span class="text-slate-400">GURU</span></span><button @click="logout" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-rose-50 hover:text-rose-600">Keluar</button></div></div></header>
    <main class="mx-auto max-w-7xl space-y-6 px-4 pt-8 sm:px-6 lg:px-8">
      <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-indigo-950 via-slate-900 to-indigo-900 p-6 text-white shadow-xl sm:p-8"><div class="absolute -right-12 -top-12 h-64 w-64 rounded-full bg-indigo-400/10 blur-2xl" /><div class="relative flex flex-col justify-between gap-5 sm:flex-row sm:items-center"><div><span class="rounded-full border border-indigo-300/30 bg-indigo-500/20 px-3 py-1 text-[11px] font-bold text-indigo-100">RUANG KERJA GURU PENGAMPU</span><h1 class="mt-4 text-2xl font-extrabold sm:text-3xl">Selamat datang, {{ teacherName }}</h1><p class="mt-1 text-sm text-slate-300">Kelola kelas, materi pembelajaran, tugas, dan penilaian siswa.</p></div><div class="flex flex-wrap items-center gap-2.5"><NuxtLink to="/teacher/board" class="rounded-xl bg-purple-600/90 px-4 py-3 text-center text-xs font-bold hover:bg-purple-500 shadow-sm transition">EduBoard 🎨</NuxtLink><NuxtLink to="/teacher/journals" class="rounded-xl bg-emerald-600 px-4 py-3 text-center text-xs font-bold hover:bg-emerald-500 shadow-sm transition">Jurnal Guru →</NuxtLink><NuxtLink to="/teacher/classes" class="rounded-xl bg-white/10 px-4 py-3 text-center text-xs font-bold ring-1 ring-white/15 hover:bg-white/20">Kelola Kelas →</NuxtLink></div></div></section>
      <section class="grid gap-4 md:grid-cols-3"><div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kelas pengampuan</p><p class="mt-2 text-3xl font-extrabold">{{ loading ? '…' : courses.length }}</p><p class="mt-1 text-xs text-slate-500">Mata pelajaran aktif</p></div><div class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Jadwal hari ini</p><p class="mt-2 text-3xl font-extrabold">{{ loading ? '…' : todaySchedules.length }}</p><p class="mt-1 text-xs text-slate-500">Sesi mengajar terjadwal</p></div><div class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Perlu dinilai</p><p class="mt-2 text-3xl font-extrabold">{{ loading ? '…' : pendingSubmissions.length }}</p><p class="mt-1 text-xs text-slate-500">Kiriman tugas siswa</p></div></section>
      <section class="grid gap-6 lg:grid-cols-5"><div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm lg:col-span-3"><h2 class="font-bold">Jadwal Mengajar Hari Ini</h2><p class="mt-1 text-xs text-slate-400">Sesi yang perlu Anda siapkan.</p><div v-if="loading" class="mt-5 text-sm text-slate-400">Memuat jadwal…</div><div v-else-if="todaySchedules.length" class="mt-5 divide-y divide-slate-100"><div v-for="schedule in todaySchedules" :key="schedule.id" class="flex items-center gap-4 py-4"><b class="w-24 text-sm text-indigo-700">{{ schedule.start_time }}–{{ schedule.end_time }}</b><div><p class="text-sm font-bold">{{ subjectName(schedule.course) }}</p><p class="text-xs text-slate-500">{{ className(schedule.course) }} · {{ schedule.room_number || 'Ruang belum ditentukan' }}</p></div></div></div><div v-else class="mt-5 rounded-2xl bg-slate-50 p-6 text-center text-sm text-slate-400">Tidak ada jadwal mengajar hari ini.</div></div><div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm lg:col-span-2"><h2 class="font-bold">Tugas Perlu Dinilai</h2><p class="mt-1 text-xs text-slate-400">Kiriman siswa yang menunggu penilaian.</p><div v-if="loading" class="mt-5 text-sm text-slate-400">Memuat tugas…</div><div v-else-if="pendingSubmissions.length" class="mt-5 space-y-3"><div v-for="submission in pendingSubmissions.slice(0, 4)" :key="submission.id" class="rounded-xl bg-amber-50 p-3"><p class="text-xs font-bold text-slate-800">{{ submission.quiz?.title || 'Tugas siswa' }}</p><p class="mt-1 text-[11px] text-slate-500">{{ submission.student?.name || 'Siswa' }}</p></div><NuxtLink to="/elearning" class="block text-xs font-semibold text-indigo-600 hover:underline">Buka kelas untuk memberi nilai →</NuxtLink></div><div v-else class="mt-5 rounded-2xl bg-emerald-50 p-6 text-center text-sm text-emerald-700">Semua tugas sudah dinilai.</div></div></section>
      <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="font-bold text-slate-900">Kelas Pengampuan (Cockpit Terpadu)</h2>
            <p class="mt-1 text-xs text-slate-500">Buka ruang kerja terpadu 1-layar untuk mengelola pertemuan, presensi cepat, CBT, dan nilai.</p>
          </div>
          <NuxtLink to="/teacher/classes" class="text-xs font-bold text-emerald-700 hover:underline">
            Lihat Semua Kelas →
          </NuxtLink>
        </div>
        <div v-if="loading" class="mt-5 text-sm text-slate-400">Memuat kelas…</div>
        <div v-else-if="courses.length" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <NuxtLink
            v-for="course in courses"
            :key="course.id"
            :to="`/teacher/classes?course_id=${course.id}`"
            class="rounded-2xl border border-slate-200 p-5 transition hover:border-emerald-400 hover:bg-emerald-50/20 group flex flex-col justify-between"
          >
            <div>
              <span class="rounded-lg bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-800 border border-emerald-200">
                Cockpit Kelas
              </span>
              <p class="mt-2 text-base font-black text-slate-900 group-hover:text-emerald-700 transition">{{ subjectName(course) }}</p>
              <p class="mt-1 text-xs font-bold text-slate-500">{{ className(course) }}</p>
            </div>
            <p class="mt-4 text-xs font-bold text-emerald-700 flex items-center justify-between">
              <span>{{ course.meets_count || 0 }} pertemuan tersimpan</span>
              <span>Buka Cockpit 🚀</span>
            </p>
          </NuxtLink>
        </div>
        <div v-else class="mt-5 rounded-2xl bg-slate-50 p-6 text-center text-sm text-slate-400">Belum ada kelas yang diampu.</div>
      </section>

      <!-- SUPERVISI WALI KELAS SHORTCUT SECTION -->
      <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
        <div>
          <span class="rounded-lg bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-800 border border-emerald-200/60">
            Wali Kelas & Supervisi
          </span>
          <h2 class="mt-2 text-lg font-bold text-slate-900">Portal Supervisi Rombel Binaan</h2>
          <p class="text-xs text-slate-500">Akses cepat rekapitulasi presensi sekelas, ledger nilai rapor madrasah, dan analisa siswa berisiko (EWS).</p>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-3">
          <NuxtLink
            to="/teacher/supervision/attendance"
            class="rounded-2xl border border-slate-200 p-5 hover:border-emerald-300 hover:bg-emerald-50/20 transition flex flex-col justify-between"
          >
            <div>
              <div class="h-8 w-8 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center font-bold mb-3">
                📋
              </div>
              <h3 class="text-sm font-bold text-slate-900">Presensi Kehadiran Sekelas</h3>
              <p class="text-xs text-slate-500 mt-1">Monitoring kehadiran bulanan & semester siswa rombel binaan.</p>
            </div>
            <span class="mt-4 text-xs font-bold text-emerald-700">Buka Rekap Absensi →</span>
          </NuxtLink>

          <NuxtLink
            to="/teacher/supervision/ledger"
            class="rounded-2xl border border-slate-200 p-5 hover:border-emerald-300 hover:bg-emerald-50/20 transition flex flex-col justify-between"
          >
            <div>
              <div class="h-8 w-8 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center font-bold mb-3">
                📊
              </div>
              <h3 class="text-sm font-bold text-slate-900">Ledger Nilai Rapor</h3>
              <p class="text-xs text-slate-500 mt-1">Matriks capaian seluruh mapel, peringkat, dan status KKTP.</p>
            </div>
            <span class="mt-4 text-xs font-bold text-emerald-700">Buka Ledger Rapor →</span>
          </NuxtLink>

          <NuxtLink
            to="/teacher/supervision/analysis"
            class="rounded-2xl border border-slate-200 p-5 hover:border-emerald-300 hover:bg-emerald-50/20 transition flex flex-col justify-between"
          >
            <div>
              <div class="h-8 w-8 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center font-bold mb-3">
                ⚠️
              </div>
              <h3 class="text-sm font-bold text-slate-900">Analisa Siswa & EWS</h3>
              <p class="text-xs text-slate-500 mt-1">Early Warning System untuk siswa berisiko & bimbingan BK.</p>
            </div>
            <span class="mt-4 text-xs font-bold text-emerald-700">Buka Analisa Siswa →</span>
          </NuxtLink>
        </div>
      </section>
    </main>
  </div>
</template>
