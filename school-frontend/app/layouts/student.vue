<script setup lang="ts">
import { ref, computed } from 'vue'

const { user, logout } = useAuth()
const route = useRoute()
const isUserMenuOpen = ref(false)
const isDarkMode = ref(false)

// Global state for sidebar toggle in student dashboard
const isStudentSidebarOpen = useState('isStudentSidebarOpen', () => true)

const toggleSidebar = () => {
  isStudentSidebarOpen.value = !isStudentSidebarOpen.value
}

const studentName = computed(() => user.value?.student?.name || user.value?.name || user.value?.username || 'Siswa')
const studentNis = computed(() => user.value?.student?.nis || user.value?.student?.nisn || user.value?.username || '-')

const userInitials = computed(() => {
  const name = user.value?.student?.name || user.value?.name
  if (!name) return 'S'
  const parts = name.trim().split(/\s+/)
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase()
  }
  return name.substring(0, 2).toUpperCase()
})

const isDashboardPage = computed(() => route.path === '/student/dashboard')
const isCoursePage = computed(() => route.path.includes('/courses/') || route.path.includes('/elearning/'))
const isDedicatedPage = computed(() =>
  route.path.includes('/discussions/') ||
  route.path.includes('/materials/') ||
  route.path.includes('/assignments/') ||
  (route.path.includes('/quizzes/') && route.path.includes('/onboarding'))
)
const isFullWidthPage = computed(() => isDashboardPage.value || isCoursePage.value || isDedicatedPage.value)
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-sans text-slate-800 flex flex-col pb-16 md:pb-0">
    <!-- Top Navbar MAN 11 with Layout Dashboard Bar -->
    <header class="sticky top-0 z-40 bg-white border-b border-slate-200 shadow-xs">
      <div class="w-full px-4 sm:px-6 flex items-center justify-between h-16">
        <!-- Left: Logo MAN 11 Jakarta & Dashboard Toggle Button -->
        <div class="flex items-center gap-3 sm:gap-4">
          <NuxtLink to="/student/dashboard" class="flex items-center gap-3">
            <img src="/logo.png" alt="Logo MAN 11 Jakarta" class="h-9 w-9 object-contain" />
            <div class="flex flex-col">
              <span class="font-black text-base sm:text-lg tracking-tight text-slate-900 leading-tight">MAN 11 JAKARTA</span>
              <span class="text-[10px] font-bold text-emerald-700 tracking-wider uppercase">Portal Siswa</span>
            </div>
          </NuxtLink>

          <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

          <!-- Hamburger Button DASHBOARD -->
          <button
            type="button"
            @click="toggleSidebar"
            class="flex items-center gap-2 text-slate-700 hover:text-emerald-700 font-bold text-xs uppercase tracking-wider px-2.5 py-1.5 rounded-lg hover:bg-slate-100 transition min-tap-target"
            title="Buka / Tutup Daftar Mata Pelajaran"
          >
            <svg class="w-5 h-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <span class="hidden sm:inline">{{ isCoursePage ? 'COURSE' : (isDedicatedPage ? 'AKTIVITAS' : 'DASHBOARD') }}</span>
          </button>
        </div>

        <!-- Right: Dark Mode Toggle & User Initials Avatar -->
        <div class="flex items-center gap-3">
          <!-- Dark Mode Toggle Button -->
          <button
            type="button"
            @click="isDarkMode = !isDarkMode"
            class="p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-full transition min-tap-target"
            title="Mode Tampilan"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>
          </button>

          <!-- User Avatar Circle -->
          <div class="relative">
            <button
              type="button"
              @click="isUserMenuOpen = !isUserMenuOpen"
              class="w-9 h-9 rounded-full bg-slate-400 hover:bg-slate-500 text-white font-bold text-xs flex items-center justify-center transition uppercase tracking-wider shadow-xs focus:ring-2 focus:ring-emerald-400"
              :title="studentName"
            >
              {{ userInitials }}
            </button>

            <!-- Dropdown Menu -->
            <div
              v-if="isUserMenuOpen"
              @click="isUserMenuOpen = false"
              class="fixed inset-0 z-40"
            />
            <div
              v-if="isUserMenuOpen"
              class="absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-slate-200 py-2 z-50 text-xs"
            >
              <div class="px-4 py-2 border-b border-slate-100">
                <p class="font-bold text-slate-900 truncate">{{ studentName }}</p>
                <p class="text-slate-400 font-mono text-[11px] mt-0.5">NIS: {{ studentNis }}</p>
              </div>

              <div class="py-1">
                <NuxtLink
                  to="/student/dashboard"
                  class="block px-4 py-2 text-slate-700 hover:bg-slate-50 font-medium"
                >
                  Dashboard Belajar
                </NuxtLink>
                <NuxtLink
                  to="/student/attendance"
                  class="block px-4 py-2 text-slate-700 hover:bg-slate-50 font-medium"
                >
                  Presensi Kehadiran
                </NuxtLink>
              </div>

              <div class="border-t border-slate-100 pt-1">
                <button
                  type="button"
                  @click="logout"
                  class="w-full text-left px-4 py-2 text-rose-600 hover:bg-rose-50 font-bold transition flex items-center justify-between"
                >
                  <span>Keluar</span>
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content Area -->
    <main v-if="isFullWidthPage" class="flex-1 w-full">
      <slot />
    </main>
    <main v-else class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <slot />
    </main>

    <!-- Mobile Fixed Bottom Navigation Bar -->
    <nav class="md:hidden fixed inset-x-0 bottom-0 z-40 bg-white border-t border-slate-200 px-2 py-1 shadow-lg">
      <div class="flex items-center justify-around text-xs font-medium text-slate-500">
        <NuxtLink
          to="/student/dashboard"
          class="flex flex-col items-center justify-center py-1.5 px-4 hover:text-emerald-700 transition min-h-[48px]"
          :class="$route.path === '/student/dashboard' ? 'text-emerald-800 font-black' : 'text-slate-500'"
        >
          <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          <span class="text-[11px] leading-tight">Dashboard</span>
        </NuxtLink>
        <NuxtLink
          to="/student/attendance"
          class="flex flex-col items-center justify-center py-1.5 px-4 hover:text-emerald-700 transition min-h-[48px]"
          :class="$route.path.includes('/attendance') ? 'text-emerald-800 font-black' : 'text-slate-500'"
        >
          <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
          </svg>
          <span class="text-[11px] leading-tight">Presensi</span>
        </NuxtLink>
      </div>
    </nav>
  </div>
</template>
