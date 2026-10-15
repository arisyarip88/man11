<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AppButton from '~/components/ui/AppButton.vue'
import AppBadge from '~/components/ui/AppBadge.vue'
import AppCard from '~/components/ui/AppCard.vue'
import AppSkeleton from '~/components/ui/AppSkeleton.vue'
import AppEmptyState from '~/components/ui/AppEmptyState.vue'

definePageMeta({ layout: 'teacher', middleware: 'auth' })

const { user, logout } = useAuth()
const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const loading = ref(true)
const schedules = ref<any[]>([])
const courses = ref<any[]>([])
const quizzes = ref<any[]>([])
const pendingSubmissions = ref<any[]>([])
const activeSemester = ref<any>(null)

const api = <T>(path: string) =>
  $fetch<T>(`${apiBase}${path}`, {
    headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
  })

const list = (response: any) => response?.data?.data || response?.data || response || []
const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']
const dayNamesId: Record<string, string> = {
  sunday: 'Minggu',
  monday: 'Senin',
  tuesday: 'Selasa',
  wednesday: 'Rabu',
  thursday: 'Kamis',
  friday: 'Jumat',
  saturday: 'Sabtu'
}

const currentDayKey = days[new Date().getDay()]
const currentDayName = dayNamesId[currentDayKey] || 'Hari Ini'
const todaySchedules = computed(() => schedules.value.filter(item => item.day === currentDayKey))
const teacherName = computed(() => user.value?.name || user.value?.username || 'Guru Pengampu')
const teacherNip = computed(() => user.value?.teacher?.nip || user.value?.username || '-')
const subjectName = (course: any) => course?.subject?.name || 'Mata Pelajaran'
const className = (course: any) => course?.class?.class_name?.name || course?.class?.className?.name || 'Kelas'

onMounted(async () => {
  try {
    const [scheduleResponse, courseResponse, quizResponse, semesterResponse] = await Promise.all([
      api<any>('/schedules?my_schedules=true&all=true&with_current_meet=true').catch(() => []),
      api<any>('/courses?my_courses=true&all=true').catch(() => []),
      api<any>('/quizzes?my_quizzes=true').catch(() => []),
      api<any>('/semesters').catch(() => null)
    ])

    schedules.value = list(scheduleResponse)
    courses.value = list(courseResponse)
    quizzes.value = list(quizResponse)

    const semestersList = list(semesterResponse)
    if (Array.isArray(semestersList) && semestersList.length > 0) {
      activeSemester.value = semestersList.find((s: any) => s.is_active) || semestersList[0]
    }

    const details = await Promise.all(
      courses.value.map(course => api<any>(`/courses/${course.id}`).catch(() => null))
    )
    const quizIds = new Set(
      details
        .filter(Boolean)
        .flatMap(course => (course?.meets || []).flatMap((meet: any) => (meet.quizzes || []).map((quiz: any) => quiz.id)))
    )
    const submissions = list(await api<any>('/submissions').catch(() => []))
    pendingSubmissions.value = submissions.filter((item: any) => quizIds.has(item.quiz_id) && item.status !== 'graded')
  } catch (error) {
    console.error('Gagal memuat dashboard guru:', error)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 pb-16 font-sans text-slate-800">
    <!-- Top Header Navigation -->
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white shadow-xs">
      <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <NuxtLink to="/teacher/dashboard" class="flex items-center gap-3">
          <img src="/logo.png" alt="Logo MAN 11 Jakarta" class="h-10 w-10 object-contain" />
          <div>
            <span class="block font-black text-slate-900 tracking-tight leading-none text-base">Ruang Guru</span>
            <span class="text-[11px] font-semibold text-emerald-700">MAN 11 Jakarta LMS</span>
          </div>
        </NuxtLink>

        <div class="flex items-center gap-3">
          <div class="hidden text-right text-xs sm:block">
            <b class="block text-slate-900 font-bold">{{ teacherName }}</b>
            <span class="text-slate-400 font-mono text-[11px]">NIP: {{ teacherNip }}</span>
          </div>
          <AppButton @click="logout" variant="ghost" size="sm" class="text-slate-600 hover:text-rose-600">
            Keluar
          </AppButton>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-7xl space-y-6 px-4 pt-6 sm:px-6 lg:px-8">
      <!-- 1. Banner Tahun Ajaran Aktif -->
      <section class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50 via-teal-50/50 to-white p-4 shadow-xs">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-xs">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold uppercase tracking-wider text-emerald-800">Tahun Ajaran Aktif</span>
              <AppBadge variant="brand" size="xs">AKTIF</AppBadge>
            </div>
            <p class="text-sm font-extrabold text-slate-900">
              {{ activeSemester ? `${activeSemester.academic_year} (${activeSemester.semester_type})` : '2026/2027 (Semester Ganjil)' }}
            </p>
          </div>
        </div>
        <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
          <span class="inline-block h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span>Kurikulum Merdeka</span>
        </div>
      </section>

      <!-- 2. Hero Welcome Card -->
      <section class="relative overflow-hidden rounded-3xl bg-slate-900 p-6 text-white shadow-xl sm:p-8 border border-slate-800">
        <div class="relative flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
          <div>
            <AppBadge variant="neutral" size="xs" class="bg-white/10 text-white border-white/20">
              RUANG KERJA GURU PENGAMPU
            </AppBadge>
            <h1 class="mt-3 text-2xl font-black sm:text-3xl tracking-tight text-white">
              Selamat datang kembali, {{ teacherName }}
            </h1>
            <p class="mt-1 text-sm text-slate-300">
              Kelola aktivitas kelas, bahan ajar, ujian CBT, dan buku nilai Kurikulum Merdeka secara terpadu.
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2.5">
            <AppButton to="/teacher/board" variant="secondary" size="sm">
              EduBoard
            </AppButton>
            <AppButton to="/teacher/journals" variant="primary" size="sm">
              Jurnal Guru
            </AppButton>
            <AppButton to="/teacher/classes" variant="outline" size="sm" class="text-white border-white/30 hover:bg-white/10">
              Cockpit Kelas
            </AppButton>
          </div>
        </div>
      </section>

      <!-- 3. Grid 4 Kartu Metrik Utama -->
      <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Kartu 1: Kelas Diampu -->
        <AppCard class="flex flex-col justify-between">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Kelas Diampu</p>
              <p class="mt-2 text-3xl font-black text-slate-900">{{ loading ? '…' : courses.length }}</p>
            </div>
            <div class="rounded-xl bg-slate-100 p-2.5 text-slate-700">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
              </svg>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
            <span class="text-slate-500 font-medium">Total rombel aktif</span>
            <NuxtLink to="/teacher/classes" class="font-bold text-emerald-700 hover:underline">
              Lihat Kelas
            </NuxtLink>
          </div>
        </AppCard>

        <!-- Kartu 2: Ujian Aktif -->
        <AppCard class="flex flex-col justify-between">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Ujian Aktif</p>
              <p class="mt-2 text-3xl font-black text-slate-900">{{ loading ? '…' : quizzes.length }}</p>
            </div>
            <div class="rounded-xl bg-slate-100 p-2.5 text-slate-700">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
            <span class="text-slate-500 font-medium">Asesmen CBT terjadwal</span>
            <NuxtLink to="/teacher/quizzes" class="font-bold text-emerald-700 hover:underline">
              Monitoring CBT
            </NuxtLink>
          </div>
        </AppCard>

        <!-- Kartu 3: Nilai Pending -->
        <AppCard class="flex flex-col justify-between">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Nilai Pending</p>
              <p class="mt-2 text-3xl font-black text-slate-900">{{ loading ? '…' : pendingSubmissions.length }}</p>
            </div>
            <div class="rounded-xl bg-amber-50 p-2.5 text-amber-700">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
            <span class="text-slate-500 font-medium">Menunggu penilaian</span>
            <NuxtLink to="/teacher/quizzes" class="font-bold text-amber-700 hover:underline">
              Koreksi Jawaban
            </NuxtLink>
          </div>
        </AppCard>

        <!-- Kartu 4: Presensi Kehadiran & Aksi Cepat -->
        <div class="rounded-2xl border border-emerald-600 bg-emerald-700 p-5 text-white shadow-xs flex flex-col justify-between">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-emerald-100">Presensi Kehadiran</p>
              <p class="mt-1 text-base font-extrabold text-white">Input Absensi Sesi</p>
              <p class="text-[11px] text-emerald-100 mt-0.5">Catat H/S/I/A sekelas 1-klik</p>
            </div>
            <div class="rounded-xl bg-white/20 p-2 text-white">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
              </svg>
            </div>
          </div>
          <div class="mt-4 pt-2">
            <NuxtLink
              to="/teacher/attendance"
              class="block w-full rounded-xl bg-white px-3 py-2 text-center text-xs font-bold text-emerald-800 shadow-xs hover:bg-emerald-50 transition"
            >
              + Buka Presensi Kelas (QR & Manual)
            </NuxtLink>
          </div>
        </div>
      </section>

      <!-- 4. Quick Shortcuts -->
      <AppCard>
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-4">Pintasan Cepat</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
          <!-- Shortcut 1: Kelas -->
          <NuxtLink
            to="/teacher/classes"
            class="group flex flex-col items-center justify-center rounded-xl border border-slate-200 bg-slate-50/70 p-4 text-center transition hover:border-emerald-500 hover:bg-emerald-50/30 hover:shadow-xs min-tap-target"
          >
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-200 text-slate-700 transition group-hover:bg-emerald-600 group-hover:text-white">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m3 0h1m-4-8l2-2 2 2m-2-2v6" />
              </svg>
            </div>
            <span class="mt-2.5 block text-xs font-bold text-slate-900 group-hover:text-emerald-800">Kelas Saya</span>
            <span class="text-[10px] text-slate-500">Rombel & Cockpit</span>
          </NuxtLink>

          <!-- Shortcut 2: Jadwal -->
          <NuxtLink
            to="#jadwal-hari-ini"
            class="group flex flex-col items-center justify-center rounded-xl border border-slate-200 bg-slate-50/70 p-4 text-center transition hover:border-emerald-500 hover:bg-emerald-50/30 hover:shadow-xs min-tap-target"
          >
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-200 text-slate-700 transition group-hover:bg-emerald-600 group-hover:text-white">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <span class="mt-2.5 block text-xs font-bold text-slate-900 group-hover:text-emerald-800">Jadwal Mengajar</span>
            <span class="text-[10px] text-slate-500">{{ currentDayName }}</span>
          </NuxtLink>

          <!-- Shortcut 3: Bahan Ajar -->
          <NuxtLink
            to="/teacher/materials/bank"
            class="group flex flex-col items-center justify-center rounded-xl border border-slate-200 bg-slate-50/70 p-4 text-center transition hover:border-emerald-500 hover:bg-emerald-50/30 hover:shadow-xs min-tap-target"
          >
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-200 text-slate-700 transition group-hover:bg-emerald-600 group-hover:text-white">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
            </div>
            <span class="mt-2.5 block text-xs font-bold text-slate-900 group-hover:text-emerald-800">Bahan Ajar</span>
            <span class="text-[10px] text-slate-500">Bank Materi & Soal</span>
          </NuxtLink>

          <!-- Shortcut 4: Presensi Kelas -->
          <NuxtLink
            to="/teacher/attendance"
            class="group flex flex-col items-center justify-center rounded-xl border border-slate-200 bg-slate-50/70 p-4 text-center transition hover:border-emerald-500 hover:bg-emerald-50/30 hover:shadow-xs min-tap-target"
          >
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-200 text-slate-700 transition group-hover:bg-emerald-600 group-hover:text-white">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
              </svg>
            </div>
            <span class="mt-2.5 block text-xs font-bold text-slate-900 group-hover:text-emerald-800">Absensi Siswa</span>
            <span class="text-[10px] text-slate-500">Rekapitulasi Harian</span>
          </NuxtLink>

          <!-- Shortcut 5: Jurnal Guru -->
          <NuxtLink
            to="/teacher/journals"
            class="group flex flex-col items-center justify-center rounded-xl border border-slate-200 bg-slate-50/70 p-4 text-center transition hover:border-emerald-500 hover:bg-emerald-50/30 hover:shadow-xs min-tap-target"
          >
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-200 text-slate-700 transition group-hover:bg-emerald-600 group-hover:text-white">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </div>
            <span class="mt-2.5 block text-xs font-bold text-slate-900 group-hover:text-emerald-800">Jurnal Guru</span>
            <span class="text-[10px] text-slate-500">Log Tatap Muka</span>
          </NuxtLink>
        </div>
      </AppCard>

      <!-- 5. Section: Mengajar Hari Ini & Antrean Koreksi Nilai -->
      <section id="jadwal-hari-ini" class="grid gap-6 lg:grid-cols-5">
        <!-- Jadwal Mengajar Hari Ini -->
        <AppCard class="lg:col-span-3 space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-base font-black text-slate-900">Jadwal Mengajar Hari Ini</h2>
              <p class="mt-0.5 text-xs text-slate-500">
                Sesi tatap muka pada hari <b>{{ currentDayName }}</b>.
              </p>
            </div>
            <AppBadge variant="brand" size="xs">
              {{ todaySchedules.length }} Sesi
            </AppBadge>
          </div>

          <div v-if="loading" class="space-y-3">
            <AppSkeleton v-for="i in 2" :key="i" height="h-20" class="rounded-xl" />
          </div>

          <div v-else-if="todaySchedules.length" class="divide-y divide-slate-100">
            <div
              v-for="schedule in todaySchedules"
              :key="schedule.id"
              class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-4 first:pt-0 last:pb-0"
            >
              <div class="flex items-start gap-3.5">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 font-bold text-xs">
                  <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div>
                  <b class="text-sm font-bold text-slate-900">{{ subjectName(schedule.course) }}</b>
                  <p class="text-xs text-slate-500 font-medium">
                    {{ className(schedule.course) }} · Ruang {{ schedule.room_number || 'Belum ditentukan' }}
                  </p>
                  <div v-if="schedule.current_meet" class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <AppBadge v-if="schedule.current_meet.chapter" variant="neutral" size="xs">
                      Bab {{ schedule.current_meet.chapter.order || '' }}: {{ schedule.current_meet.chapter.title }}
                    </AppBadge>
                    <AppBadge variant="brand" size="xs">
                      Pertemuan {{ schedule.current_meet.meeting_number }}: {{ schedule.current_meet.title }}
                    </AppBadge>
                  </div>
                  <div v-else class="mt-1 text-[11px] text-slate-400 italic font-medium">
                    Belum ada modul pertemuan spesifik
                  </div>
                </div>
              </div>
              <div class="flex flex-wrap items-center gap-2 self-end sm:self-center">
                <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-mono font-bold text-slate-700">
                  {{ schedule.start_time }} – {{ schedule.end_time }}
                </span>
                <NuxtLink
                  :to="`/teacher/attendance?course_id=${schedule.course?.id}&schedule_id=${schedule.id}`"
                  class="px-2.5 py-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center gap-1 shadow-2xs"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                  </svg>
                  <span>Presensi</span>
                </NuxtLink>
                <NuxtLink
                  :to="`/teacher/classes?course_id=${schedule.course?.id}`"
                  class="px-2.5 py-1 rounded-xl border border-slate-200 hover:border-emerald-300 hover:bg-emerald-50/50 text-slate-700 hover:text-emerald-900 text-xs font-bold transition flex items-center gap-1"
                >
                  <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                  </svg>
                  <span>Cockpit Kelas</span>
                </NuxtLink>
              </div>
            </div>
          </div>

          <AppEmptyState
            v-else
            title="Tidak ada jadwal mengajar hari ini"
            description="Manfaatkan waktu luang untuk merancang bahan ajar atau mengevaluasi tugas siswa."
          />
        </AppCard>

        <!-- Tugas Perlu Dinilai -->
        <AppCard class="lg:col-span-2 flex flex-col justify-between">
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-base font-black text-slate-900">Tugas Perlu Dinilai</h2>
                <p class="mt-0.5 text-xs text-slate-500 font-medium">Kiriman siswa yang menunggu pemeriksaan.</p>
              </div>
              <AppBadge variant="warning" size="xs">
                {{ pendingSubmissions.length }} Antrean
              </AppBadge>
            </div>

            <div v-if="loading" class="space-y-2">
              <AppSkeleton v-for="i in 2" :key="i" height="h-16" class="rounded-xl" />
            </div>

            <div v-else-if="pendingSubmissions.length" class="space-y-2">
              <div
                v-for="submission in pendingSubmissions.slice(0, 3)"
                :key="submission.id"
                class="rounded-xl border border-slate-200 bg-slate-50 p-3"
              >
                <p class="text-xs font-bold text-slate-900">{{ submission.quiz?.title || 'Tugas Siswa' }}</p>
                <div class="mt-1 flex items-center justify-between text-[11px] text-slate-500 font-medium">
                  <span>{{ submission.student?.name || 'Siswa' }}</span>
                  <span class="font-mono text-amber-700 font-bold">Menunggu</span>
                </div>
              </div>
            </div>

            <AppEmptyState
              v-else
              title="Semua tugas sudah dinilai"
              description="Tidak ada antrean kiriman tugas baru saat ini."
            />
          </div>

          <div class="mt-4 pt-3 border-t border-slate-100">
            <NuxtLink to="/teacher/quizzes" class="block text-center text-xs font-bold text-emerald-700 hover:underline">
              Buka Manajemen Kuis & CBT →
            </NuxtLink>
          </div>
        </AppCard>
      </section>

      <!-- 6. Section: Kelas Diampu -->
      <AppCard class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <div class="flex items-center gap-2">
              <AppBadge variant="neutral" size="xs">PORTAL KELAS</AppBadge>
              <h2 class="text-lg font-black text-slate-900">Kelas Diampu (Classroom Cockpit)</h2>
            </div>
            <p class="mt-0.5 text-xs text-slate-500">
              Akses ruang kerja terpadu layar tunggal untuk absensi cepat, materi pembelajaran, CBT, dan buku nilai.
            </p>
          </div>
          <NuxtLink to="/teacher/classes" class="text-xs font-bold text-emerald-700 hover:underline">
            Lihat Semua Kelas →
          </NuxtLink>
        </div>

        <div v-if="loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <AppSkeleton v-for="i in 3" :key="i" height="h-36" class="rounded-xl" />
        </div>

        <div v-else-if="courses.length" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="course in courses"
            :key="course.id"
            class="group bg-white border border-slate-200/90 hover:border-emerald-500 rounded-3xl p-5 shadow-xs hover:shadow-md transition-all flex flex-col justify-between"
          >
            <div>
              <div class="flex items-center justify-between gap-2">
                <span class="px-2.5 py-1 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-black tracking-tight">
                  {{ className(course) }}
                </span>
                <span class="text-[11px] font-bold text-slate-400">
                  {{ course.class?.room_number ? `Ruang ${course.class.room_number}` : 'Ruang KBM' }}
                </span>
              </div>

              <h3 class="mt-3.5 text-base font-black text-slate-900 group-hover:text-emerald-800 transition leading-snug">
                {{ subjectName(course) }}
              </h3>

              <p class="mt-1 text-xs text-slate-500 font-medium">
                Semester {{ course.class?.semester?.semester_type || 'Ganjil' }} &bull; Kurikulum Merdeka
              </p>

              <div class="mt-4 grid grid-cols-2 gap-2 pt-3 border-t border-slate-100 text-xs">
                <div class="p-2.5 bg-slate-50 rounded-xl">
                  <span class="block text-[10px] font-bold text-slate-400 uppercase">Siswa Terdaftar</span>
                  <span class="text-xs font-black text-slate-900">{{ course.class?.capacity || course.class?.students_count || 36 }} Siswa</span>
                </div>
                <div class="p-2.5 bg-slate-50 rounded-xl">
                  <span class="block text-[10px] font-bold text-slate-400 uppercase">Sesi Sub-bab</span>
                  <span class="text-xs font-black text-slate-900">{{ course.meets_count || 0 }} Pertemuan</span>
                </div>
              </div>
            </div>

            <div class="mt-5 pt-3.5 border-t border-slate-100 space-y-2">
              <NuxtLink
                :to="`/teacher/classes?course_id=${course.id}`"
                class="w-full py-2.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold shadow-2xs transition flex items-center justify-center gap-1.5"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                <span>Buka Cockpit Kelas (1 Layar)</span>
              </NuxtLink>

              <div class="flex items-center gap-2 text-xs">
                <NuxtLink
                  :to="`/teacher/attendance?course_id=${course.id}`"
                  class="flex-1 py-1.5 px-2 rounded-xl border border-slate-200 hover:border-emerald-300 hover:bg-emerald-50/50 text-slate-700 hover:text-emerald-900 text-center font-bold transition flex items-center justify-center gap-1"
                >
                  <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                  </svg>
                  <span>Presensi</span>
                </NuxtLink>

                <NuxtLink
                  :to="`/teacher/grades/${course.id}`"
                  class="flex-1 py-1.5 px-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-center font-bold transition flex items-center justify-center gap-1"
                >
                  <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                  </svg>
                  <span>Buku Nilai</span>
                </NuxtLink>
              </div>
            </div>
          </div>
        </div>

        <AppEmptyState
          v-else
          title="Belum ada kelas yang diampu"
          description="Kelas yang ditugaskan kepada Anda akan tampil di sini."
        />
      </AppCard>

      <!-- 7. Section: Portal Supervisi Wali Kelas -->
      <AppCard class="space-y-4">
        <div>
          <AppBadge variant="brand" size="xs">Wali Kelas & Supervisi</AppBadge>
          <h2 class="mt-2 text-lg font-black text-slate-900">Portal Supervisi Rombel Binaan</h2>
          <p class="text-xs text-slate-500">
            Monitoring rekapitulasi kehadiran sekelas, ledger nilai rapor madrasah, dan Early Warning System (EWS).
          </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
          <NuxtLink
            to="/teacher/supervision/attendance"
            class="group flex flex-col justify-between rounded-xl border border-slate-200 p-5 transition hover:border-emerald-600 hover:bg-emerald-50/20 min-tap-target"
          >
            <div>
              <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700 font-bold text-sm">
                <svg class="w-5 h-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
              </div>
              <h3 class="text-sm font-bold text-slate-900 group-hover:text-emerald-800 transition">Presensi Kehadiran Sekelas</h3>
              <p class="mt-1 text-xs text-slate-500 font-medium leading-relaxed">
                Monitoring persentase kehadiran bulanan dan semester rombel binaan.
              </p>
            </div>
            <span class="mt-4 text-xs font-bold text-emerald-700">Buka Rekap Absensi →</span>
          </NuxtLink>

          <NuxtLink
            to="/teacher/supervision/ledger"
            class="group flex flex-col justify-between rounded-xl border border-slate-200 p-5 transition hover:border-emerald-600 hover:bg-emerald-50/20 min-tap-target"
          >
            <div>
              <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700 font-bold text-sm">
                <svg class="w-5 h-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
              </div>
              <h3 class="text-sm font-bold text-slate-900 group-hover:text-emerald-800 transition">Ledger Nilai Rapor</h3>
              <p class="mt-1 text-xs text-slate-500 font-medium leading-relaxed">
                Matriks capaian seluruh mapel, peringkat kelas, dan status kelulusan KKTP.
              </p>
            </div>
            <span class="mt-4 text-xs font-bold text-emerald-700">Buka Ledger Rapor →</span>
          </NuxtLink>

          <NuxtLink
            to="/teacher/supervision/analysis"
            class="group flex flex-col justify-between rounded-xl border border-slate-200 p-5 transition hover:border-emerald-600 hover:bg-emerald-50/20 min-tap-target"
          >
            <div>
              <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700 font-bold text-sm">
                <svg class="w-5 h-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <h3 class="text-sm font-bold text-slate-900 group-hover:text-emerald-800 transition">Analisa Siswa & EWS</h3>
              <p class="mt-1 text-xs text-slate-500 font-medium leading-relaxed">
                Early Warning System untuk mendeteksi siswa berisiko dan bimbingan konseling.
              </p>
            </div>
            <span class="mt-4 text-xs font-bold text-emerald-700">Buka Analisa Siswa →</span>
          </NuxtLink>
        </div>
      </AppCard>
    </main>
  </div>
</template>
