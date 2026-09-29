<script setup lang="ts">
import { ref, computed } from 'vue'

const { user, logout } = useAuth()
const isMobileMenuOpen = ref(false)

const studentName = computed(() => user.value?.student?.name || user.value?.name || user.value?.username || 'Siswa')
const studentNis = computed(() => user.value?.student?.nis || user.value?.username || '-')

const navItems = [
  { label: 'Dashboard', to: '/student/dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { label: 'Kelas & Materi', to: '/student/dashboard?tab=courses', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
  { label: 'Ujian & CBT', to: '/student/dashboard?tab=tasks', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01' },
  { label: 'Jadwal', to: '/student/dashboard?tab=schedule', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
  { label: 'Nilai & Rapor', to: '/student/dashboard?tab=grades', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { label: 'Presensi', to: '/student/dashboard?tab=attendance', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
]
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-sans text-slate-800 flex flex-col pb-16 md:pb-0">
    <!-- Desktop & Tablet Header -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-100 shadow-xs">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <!-- Logo & Brand -->
          <NuxtLink to="/student/dashboard" class="flex items-center gap-3">
            <img src="/logo.png" alt="Logo MAN 11 Jakarta" class="h-9 w-9 object-contain" />
            <div>
              <div class="font-extrabold tracking-tight text-slate-900 text-sm sm:text-base leading-tight">MAN 11 JAKARTA</div>
              <div class="text-[10px] font-bold text-emerald-600 tracking-wider uppercase">Portal Siswa</div>
            </div>
          </NuxtLink>

          <!-- Desktop Navigation Menu -->
          <nav class="hidden lg:flex items-center gap-1">
            <NuxtLink
              v-for="item in navItems"
              :key="item.to"
              :to="item.to"
              active-class="bg-emerald-50 text-emerald-700 font-bold"
              class="px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition flex items-center gap-1.5"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
              </svg>
              <span>{{ item.label }}</span>
            </NuxtLink>
          </nav>

          <!-- User Profile & Logout -->
          <div class="flex items-center gap-3">
            <div class="hidden sm:block text-right">
              <div class="text-xs font-bold text-slate-800">{{ studentName }}</div>
              <div class="text-[10px] text-slate-400 font-medium">NIS: {{ studentNis }}</div>
            </div>

            <button
              @click="logout"
              class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-600 transition"
              title="Keluar dari Akun"
            >
              Keluar
            </button>

            <!-- Mobile Hamburger Button -->
            <button
              @click="isMobileMenuOpen = !isMobileMenuOpen"
              class="lg:hidden p-2 text-slate-600 hover:bg-slate-100 rounded-xl"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Mobile Dropdown Navigation -->
      <div v-if="isMobileMenuOpen" class="lg:hidden border-t border-slate-100 bg-white px-4 py-3 space-y-1">
        <NuxtLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          @click="isMobileMenuOpen = false"
          active-class="bg-emerald-50 text-emerald-700 font-bold"
          class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-slate-700 hover:bg-slate-50"
        >
          <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
          </svg>
          <span>{{ item.label }}</span>
        </NuxtLink>
      </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <slot />
    </main>

    <!-- Mobile Fixed Bottom Navigation Bar -->
    <nav class="md:hidden fixed inset-x-0 bottom-0 z-40 bg-white/95 border-t border-slate-200 px-2 py-1.5 backdrop-blur-md">
      <div class="flex items-center justify-around text-[10px] font-medium text-slate-500">
        <NuxtLink
          to="/student/dashboard"
          class="flex flex-col items-center py-1 px-2 hover:text-emerald-600 transition"
          :class="!$route.query.tab ? 'text-emerald-700 font-bold' : 'text-slate-500'"
        >
          <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          <span>Beranda</span>
        </NuxtLink>
        <NuxtLink
          to="/student/dashboard?tab=courses"
          class="flex flex-col items-center py-1 px-2 hover:text-emerald-600 transition"
          :class="$route.query.tab === 'courses' ? 'text-emerald-700 font-bold' : 'text-slate-500'"
        >
          <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
          <span>Materi</span>
        </NuxtLink>
        <NuxtLink
          to="/student/dashboard?tab=tasks"
          class="flex flex-col items-center py-1 px-2 hover:text-emerald-600 transition"
          :class="$route.query.tab === 'tasks' ? 'text-emerald-700 font-bold' : 'text-slate-500'"
        >
          <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
          </svg>
          <span>Ujian CBT</span>
        </NuxtLink>
        <NuxtLink
          to="/student/dashboard?tab=schedule"
          class="flex flex-col items-center py-1 px-2 hover:text-emerald-600 transition"
          :class="$route.query.tab === 'schedule' ? 'text-emerald-700 font-bold' : 'text-slate-500'"
        >
          <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <span>Jadwal</span>
        </NuxtLink>
        <NuxtLink
          to="/student/dashboard?tab=grades"
          class="flex flex-col items-center py-1 px-2 hover:text-emerald-600 transition"
          :class="$route.query.tab === 'grades' ? 'text-emerald-700 font-bold' : 'text-slate-500'"
        >
          <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <span>Nilai</span>
        </NuxtLink>
      </div>
    </nav>
  </div>
</template>
