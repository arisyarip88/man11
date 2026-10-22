<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const { user, logout, hasRole } = useAuth()
const isMasterOpen = ref(false)
const isProfileOpen = ref(false)
const isProfilModalOpen = ref(false)
const isSidebarOpen = ref(false)
const profileDropdownRef = ref(null)

const handleClickOutside = (event) => {
  if (profileDropdownRef.value && !profileDropdownRef.value.contains(event.target)) {
    isProfileOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

// Menu Utama Single (Tanpa Dashboard karena ditaruh terpisah di paling atas)
const menuItems = [
  { 
    label: 'Rombel Kelas', 
    icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0V11m0 0h4m-4 0H7m4 0v10', 
    to: '/admin/rombel', 
    roles: ['admin', 'super_admin', 'manager'] 
  },
  // { 
  //   label: 'Role', 
  //   icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 
  //   to: '/admin/roles', 
  //   roles: ['admin', 'super_admin'] 
  // },
  { 
    label: 'User', 
    icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 
    to: '/admin/users', 
    roles: ['admin', 'super_admin'] 
  },
  { 
    label: 'Data Siswa', 
    icon: 'M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z', 
    to: '/admin/students', 
    roles: ['admin', 'super_admin', 'teacher'] 
  },
  { 
    label: 'Teachers', 
    icon: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 
    to: '/admin/teachers', 
    roles: ['admin', 'super_admin'] 
  },
  { 
    label: 'Plotting Pengajaran', 
    icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', 
    to: '/admin/courses', 
    roles: ['admin', 'super_admin'] 
  },
  { 
    label: 'Subject', 
    icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10', 
    to: '/admin/subjects', 
    roles: ['admin', 'super_admin', 'teacher'] 
  },
  { 
    label: 'Schedule', 
    icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 
    to: '/admin/schedules', 
    roles: ['admin', 'super_admin', 'teacher', 'student'] 
  },
  { 
    label: 'Score', 
    icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 
    to: '/admin/scores', 
    roles: ['admin', 'super_admin', 'teacher', 'student'] 
  },
]

// Sub Menu Khusus Master Data
const masterSubItems = [
  // { label: 'Role', to: '/admin/master/role', roles: ['admin', 'super_admin'] },
  { label: 'Semester', to: '/admin/semesters', roles: ['admin', 'super_admin'] },
  { label: 'Major', to: '/admin/majors', roles: ['admin', 'super_admin'] },
  { label: 'Class Name', to: '/admin/class-names', roles: ['admin', 'super_admin'] },
  { label: 'Rombel Kelas', to: '/admin/rombel', roles: ['admin', 'super_admin'] },
]
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex font-sans relative">
    
    <!-- Mobile Overlay Backdrop -->
    <div 
      v-if="isSidebarOpen" 
      @click="isSidebarOpen = false" 
      class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-30 lg:hidden transition-opacity"
    ></div>

    <!-- Sidebar Responsive Hijau Gradasi -->
    <aside 
      class="w-64 bg-slate-900 text-slate-100 flex flex-col fixed inset-y-0 left-0 z-40 transform lg:translate-x-0 transition-transform duration-300 ease-in-out overflow-y-auto border-r border-slate-800"
      :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- Logo Header -->
      <div class="py-5 flex items-center gap-3 px-6 border-b border-slate-800">
        <img 
          src="/logo.png" 
          alt="MAN 11 Jakarta Logo" 
          class="w-10 h-10 object-contain drop-shadow"
        />
        <div>
          <h1 class="text-sm font-bold text-white tracking-tight">
            MAN 11 JAKARTA
          </h1>
          <p class="text-[10px] text-brand-400 font-semibold uppercase tracking-wider">
            Admin Portal
          </p>
        </div>
      </div>

      <!-- Navigation Menu -->
      <nav class="flex-1 py-3 px-3 space-y-1">

        <!-- 1. DASHBOARD (PALING ATAS) -->
        <NuxtLink 
          v-if="hasRole(['admin', 'user', 'agent', 'manager', 'super_admin', 'student', 'lecturer'])"
          to="/admin/dashboard" 
          @click="isSidebarOpen = false"
          active-class="text-white font-bold bg-brand-700 shadow-xs"
          class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-slate-300 hover:text-white hover:bg-slate-800 transition text-xs font-semibold"
        >
          <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          <span>Dashboard</span>
        </NuxtLink>
        
        <!-- 2. MENU MASTER DROPDOWN -->
        <div v-if="hasRole(['admin', 'super_admin'])">
          <button 
            @click="isMasterOpen = !isMasterOpen"
            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-slate-300 hover:text-white hover:bg-slate-800 transition text-xs font-semibold cursor-pointer"
          >
            <div class="flex items-center space-x-3">
              <svg class="w-5 h-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
              </svg>
              <span>Master Data</span>
            </div>
            <svg class="w-4 h-4 transition-transform duration-200 text-slate-400" :class="{ 'rotate-180': isMasterOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          
          <!-- Sub-menu Master -->
          <div v-if="isMasterOpen" class="ml-4 pl-3 py-1.5 space-y-1 border-l border-slate-800 text-xs text-slate-400">
            <template v-for="sub in masterSubItems" :key="sub.to">
              <NuxtLink 
                v-if="hasRole(sub.roles)"
                :to="sub.to" 
                @click="isSidebarOpen = false" 
                active-class="text-brand-400 font-bold bg-slate-800/80"
                class="block px-3 py-1.5 rounded-lg hover:text-white hover:bg-slate-800 transition"
              >
                {{ sub.label }}
              </NuxtLink>
            </template>
          </div>
        </div>

        <!-- 3. MENU UTAMA LAINNYA -->
        <template v-for="item in menuItems" :key="item.to">
          <NuxtLink 
            v-if="hasRole(item.roles)"
            :to="item.to" 
            @click="isSidebarOpen = false"
            active-class="text-white font-bold bg-brand-700 shadow-xs"
            class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-slate-300 hover:text-white hover:bg-slate-800 transition text-xs font-semibold"
          >
            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
            </svg>
            <span>{{ item.label }}</span>
          </NuxtLink>
        </template>
      </nav>
    </aside>

    <!-- Content Area -->
    <div class="w-full lg:pl-64 flex-1 flex flex-col min-w-0">
      
      <!-- Top Bar Header Clean Brand Standard -->
      <header class="h-16 bg-white border-b border-slate-200 px-4 lg:px-8 flex items-center justify-between sticky top-0 z-20 shadow-2xs">
        
        <!-- Left Side: Hamburger Button (Mobile Only) -->
        <div class="flex items-center space-x-3">
          <button 
            @click="isSidebarOpen = !isSidebarOpen"
            class="p-2 rounded-lg text-slate-600 hover:bg-slate-100 focus:outline-hidden lg:hidden transition min-tap-target"
            aria-label="Toggle Navigation"
          >
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
        </div>

        <!-- Right Side Controls & Profile Dropdown -->
        <div class="flex items-center space-x-3 lg:space-x-4">
          <!-- Profile Container -->
          <div 
            class="relative" 
            ref="profileDropdownRef"
            @mouseenter="isProfileOpen = true"
            @mouseleave="isProfileOpen = false"
          >
            <button 
              @click="isProfileOpen = !isProfileOpen" 
              class="flex items-center focus:outline-hidden rounded-full ring-2 ring-slate-200 hover:ring-brand-500 transition p-0.5 shadow-2xs min-tap-target"
            >
              <img 
                class="w-8 h-8 rounded-full object-cover" 
                 :src="user?.student?.nis || user?.teacher?.nip || '/images.png'"  
                     
                alt="Avatar"
              />
            </button>

            <!-- Dropdown Menu -->
            <Transition
              enter-active-class="transition ease-out duration-150"
              enter-from-class="opacity-0 scale-95 -translate-y-1"
              enter-to-class="opacity-100 scale-100 translate-y-0"
              leave-active-class="transition ease-in duration-100"
              leave-from-class="opacity-100 scale-100 translate-y-0"
              leave-to-class="opacity-0 scale-95 -translate-y-1"
            >
              <div 
                v-if="isProfileOpen" 
                class="absolute right-0 mt-2 w-64 bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden z-50"
              >
                <!-- Detail User -->
                <div class="p-4 bg-emerald-50/50 border-b border-slate-100 flex items-center space-x-3">
                  <img 
                    class="w-10 h-10 rounded-full object-cover border-2 border-emerald-500" 
                    :src="user?.student?.nis || user?.teacher?.nip || '/images.png'"  
                    alt="Avatar" 
                  />
                  <div class="overflow-hidden">
                    <h4 class="text-xs font-bold text-slate-800 truncate">
                      {{ user?.username || user?.name || 'Jane Doe' }}
                    </h4>
                    <p class="text-[11px] text-slate-500 truncate mb-1">
                      {{ user?.email || 'jane.doe@example.com' }}
                    </p>
                    <span class="inline-block px-2 py-0.5 text-[9px] font-bold text-emerald-700 bg-emerald-100 rounded-full uppercase tracking-wider">
                      {{ user?.role?.name || user?.role || 'Guest' }}
                    </span>
                  </div>
                </div>

                <!-- Profile Submenu -->
                <div class="p-1.5 space-y-0.5">
                  <button 
                    type="button"
                    @click="isProfilModalOpen = true; isProfileOpen = false"
                    class="w-full flex items-center space-x-2.5 px-3 py-2 text-xs font-medium text-slate-600 rounded-xl hover:bg-slate-50 hover:text-emerald-600 transition text-left cursor-pointer"
                  >
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span>Profil & Pengaturan Akun</span>
                  </button>

                  <div class="my-1 border-t border-slate-100"></div>

                  <!-- Logout Button -->
                  <button 
                    @click="logout(); isProfileOpen = false"
                    class="w-full flex items-center space-x-2.5 px-3 py-2 text-xs font-medium text-red-600 rounded-xl hover:bg-red-50 transition text-left"
                  >
                    <svg class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Keluar / Logout</span>
                  </button>
                </div>
              </div>
            </Transition>
          </div>
        </div>
      </header>

      <!-- Slot Konten Utama -->
      <main class="p-4 lg:p-8 bg-slate-50 flex-1">
        <slot />
      </main>
    </div>

    <!-- Modal Profil Admin -->
    <ProfilModal 
      :is-open="isProfilModalOpen" 
      :user="user" 
      @close="isProfilModalOpen = false" 
    />
  </div>
</template>