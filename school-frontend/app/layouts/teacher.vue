<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'

const { user, logout, hasRole } = useAuth()
const isSidebarOpen = ref(false)
const isProfileOpen = ref(false)
const profileDropdownRef = ref(null)

const teacherName = computed(() => user.value?.name || user.value?.username || 'Guru Pengampu')
const teacherNip = computed(() => user.value?.teacher?.nip || user.value?.nip || '198912092019031007')
const isHomeroom = computed(() => {
  return hasRole('admin') || hasRole('super_admin') || (user.value?.teacher?.homeroom_classes && user.value.teacher.homeroom_classes.length > 0)
})

const handleClickOutside = (event: MouseEvent) => {
  if (profileDropdownRef.value && !(profileDropdownRef.value as any).contains(event.target)) {
    isProfileOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

const generalMenuItems = [
  {
    label: 'Dashboard',
    to: '/teacher/dashboard',
    icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'
  },
  {
    label: 'Silabus (ATP / CP)',
    to: '/teacher/syllabus',
    icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
  },
  {
    label: 'Kelas Master',
    to: '/teacher/master-courses',
    icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
    badge: 'Multi-Rombel'
  },
  {
    label: 'Kelas Saya',
    to: '/teacher/classes',
    icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'
  },
  {
    label: 'Edu Board',
    to: '/teacher/board',
    icon: 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01'
  },
  {
    label: 'Jurnal Guru',
    to: '/teacher/journals',
    icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'
  },
  {
    label: 'Buku Nilai',
    to: '/teacher/grades',
    icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    badge: 'K. Merdeka'
  },
]

const bahanAjarMenuItems = [
  {
    label: 'Bank Materi',
    to: '/teacher/materials/bank',
    icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'
  },
  {
    label: 'Bank Soal',
    to: '/teacher/questions/bank',
    icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'
  },
  {
    label: 'Kuis & CBT',
    to: '/teacher/quizzes',
    icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    badge: 'Evaluasi'
  },
]

const supervisiMenuItems = [
  {
    label: 'Presensi Kehadiran',
    to: '/teacher/supervision/attendance',
    icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'
  },
  {
    label: 'Ledger Nilai',
    to: '/teacher/supervision/ledger',
    icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
  },
  {
    label: 'Analisa Siswa',
    to: '/teacher/supervision/analysis',
    icon: 'M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'
  },
]
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex font-sans relative text-slate-800">
    <!-- Mobile Backdrop -->
    <div
      v-if="isSidebarOpen"
      @click="isSidebarOpen = false"
      class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-30 lg:hidden transition-opacity"
    ></div>

    <!-- Sidebar Navigation -->
    <aside
      :class="[
        'fixed lg:sticky top-0 left-0 z-40 h-screen w-64 bg-white border-r border-slate-200/80 flex flex-col justify-between transition-transform duration-300 ease-in-out',
        isSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
      ]"
    >
      <div class="flex flex-col h-full">
        <!-- Logo & Brand Header -->
        <div class="h-16 flex items-center justify-between px-5 border-b border-slate-100">
          <NuxtLink to="/teacher/dashboard" class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-700 to-emerald-500 flex items-center justify-center text-white font-bold shadow-md shadow-emerald-600/20">
              11
            </div>
            <div>
              <span class="font-extrabold text-sm tracking-tight text-slate-900 block leading-tight">MAN 11 JAKARTA</span>
              <span class="text-[10px] font-semibold text-emerald-700 uppercase tracking-wider block">LMS Guru Madrasah</span>
            </div>
          </NuxtLink>
          <button
            @click="isSidebarOpen = false"
            class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Academic Year Active Badge -->
        <div class="px-5 py-3 bg-emerald-50/70 border-b border-emerald-100/50 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="relative flex h-2 w-2">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <span class="text-[11px] font-bold text-emerald-800">TA 2025/2026 Ganjil</span>
          </div>
          <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-200/60 text-emerald-900">Aktif</span>
        </div>

        <!-- Navigation Links Menu -->
        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-6">
          <!-- GENERAL SECTION -->
          <div>
            <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
              General
            </div>
            <nav class="space-y-1">
              <NuxtLink
                v-for="item in generalMenuItems"
                :key="item.to"
                :to="item.to"
                class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:text-emerald-700 hover:bg-emerald-50/60 transition-all group"
                active-class="bg-emerald-600 !text-white shadow-sm shadow-emerald-600/30"
              >
                <div class="flex items-center gap-3">
                  <svg class="w-4 h-4 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                  </svg>
                  <span>{{ item.label }}</span>
                </div>
                <span
                  v-if="item.badge"
                  class="text-[9px] px-1.5 py-0.5 rounded font-bold bg-amber-100 text-amber-800 group-hover:bg-amber-200"
                >
                  {{ item.badge }}
                </span>
              </NuxtLink>
            </nav>
          </div>

          <!-- BAHAN AJAR SECTION (Repository Mandiri & Pusat) -->
          <div>
            <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
              Bahan Ajar
            </div>
            <nav class="space-y-1">
              <NuxtLink
                v-for="item in bahanAjarMenuItems"
                :key="item.to"
                :to="item.to"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:text-emerald-700 hover:bg-emerald-50/60 transition-all group"
                active-class="bg-emerald-600 !text-white shadow-sm shadow-emerald-600/30"
              >
                <svg class="w-4 h-4 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                </svg>
                <span>{{ item.label }}</span>
              </NuxtLink>
            </nav>
          </div>

          <!-- SUPERVISI SECTION (Homeroom / Wali Kelas) -->
          <div>
            <div class="px-3 mb-2 flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-slate-400">
              <span>Supervisi</span>
              <span class="text-[9px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">Wali Kelas</span>
            </div>
            <nav class="space-y-1">
              <NuxtLink
                v-for="item in supervisiMenuItems"
                :key="item.to"
                :to="item.to"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:text-emerald-700 hover:bg-emerald-50/60 transition-all group"
                active-class="bg-emerald-600 !text-white shadow-sm shadow-emerald-600/30"
              >
                <svg class="w-4 h-4 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                </svg>
                <span>{{ item.label }}</span>
              </NuxtLink>
            </nav>
          </div>
        </div>

        <!-- Sidebar Footer / Teacher Profile Mini -->
        <div class="p-3 border-t border-slate-100 bg-slate-50/60">
          <div class="flex items-center justify-between p-2 rounded-xl bg-white border border-slate-200/70 shadow-xs">
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-xs shrink-0">
                {{ teacherName.charAt(0) }}
              </div>
              <div class="min-w-0">
                <p class="text-xs font-bold text-slate-800 truncate leading-tight">{{ teacherName }}</p>
                <p class="text-[10px] text-slate-400 truncate">NIP: {{ teacherNip }}</p>
              </div>
            </div>
            <button
              @click="logout"
              title="Keluar"
              class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Top Navbar (Desktop & Mobile) -->
      <header class="h-16 bg-white border-b border-slate-200/80 sticky top-0 z-20 flex items-center justify-between px-4 sm:px-6">
        <div class="flex items-center gap-3">
          <button
            @click="isSidebarOpen = true"
            class="lg:hidden p-2 rounded-xl text-slate-600 hover:bg-slate-100 focus:outline-none"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <div>
            <span class="text-xs font-semibold text-slate-400">Portal Pengajar</span>
            <h2 class="text-sm font-extrabold text-slate-800 tracking-tight">MAN 11 Jakarta Selatan</h2>
          </div>
        </div>

        <!-- Top Right Actions -->
        <div class="flex items-center gap-3">
          <NuxtLink
            to="/elearning"
            class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 hover:text-emerald-700 transition"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
            Ruang E-Learning Siswa
          </NuxtLink>

          <!-- User Profile Dropdown -->
          <div class="relative" ref="profileDropdownRef">
            <button
              @click="isProfileOpen = !isProfileOpen"
              class="flex items-center gap-2.5 p-1.5 rounded-xl hover:bg-slate-100 transition-colors focus:outline-none"
            >
              <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white font-bold flex items-center justify-center text-xs shadow-xs">
                {{ teacherName.charAt(0) }}
              </div>
              <div class="hidden md:block text-left">
                <span class="block text-xs font-bold text-slate-800 leading-tight">{{ teacherName }}</span>
                <span class="block text-[10px] font-semibold text-emerald-700">Guru Madrasah</span>
              </div>
              <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <!-- Dropdown Menu -->
            <div
              v-if="isProfileOpen"
              class="absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-xl border border-slate-100 py-1.5 z-50 animate-in fade-in zoom-in-95 duration-100"
            >
              <div class="px-4 py-2 border-b border-slate-100">
                <p class="text-xs font-bold text-slate-900 truncate">{{ teacherName }}</p>
                <p class="text-[10px] text-slate-400 truncate">{{ user?.email || 'guru@man11jakarta.sch.id' }}</p>
              </div>
              <NuxtLink
                to="/teacher/dashboard"
                @click="isProfileOpen = false"
                class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-emerald-50 hover:text-emerald-700"
              >
                Dashboard Pengajar
              </NuxtLink>
              <button
                @click="logout"
                class="w-full flex items-center gap-2 px-4 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 text-left"
              >
                Keluar Aplikasi
              </button>
            </div>
          </div>
        </div>
      </header>

      <!-- Main Page Slot -->
      <main class="flex-1 p-4 sm:p-6 lg:p-8">
        <slot />
      </main>
    </div>
  </div>
</template>
