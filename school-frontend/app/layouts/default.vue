<script setup lang="ts">
import { computed } from 'vue'

const { user, logout, getUserRole } = useAuth()
const role = computed(() => (getUserRole() || '').toLowerCase())
const isStudent = computed(() => ['student', 'siswa'].includes(role.value))
const isTeacher = computed(() => ['teacher', 'guru'].includes(role.value))
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-sans text-slate-800 flex flex-col">
    <!-- Top global bar for LMS / E-learning when default layout is active -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-200/80 shadow-xs">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <NuxtLink :to="isStudent ? '/student/dashboard' : (isTeacher ? '/teacher/dashboard' : '/')" class="flex items-center gap-3">
            <img src="/logo.png" alt="Logo MAN 11 Jakarta" class="h-9 w-9 object-contain" />
            <div>
              <div class="font-black tracking-tight text-slate-900 text-sm sm:text-base leading-tight">MAN 11 JAKARTA</div>
              <div class="text-xs font-bold text-brand-800 tracking-wider uppercase">Learning Management System</div>
            </div>
          </NuxtLink>
        </div>

        <div class="flex items-center gap-3">
          <NuxtLink
            v-if="isStudent"
            to="/student/dashboard"
            class="px-3.5 py-2 rounded-xl bg-brand-100 text-brand-900 text-xs font-bold hover:bg-brand-200 transition flex items-center gap-1.5 min-h-[44px]"
          >
            <span>← Kembali ke Dashboard Siswa</span>
          </NuxtLink>
          <NuxtLink
            v-else-if="isTeacher"
            to="/teacher/dashboard"
            class="px-3.5 py-2 rounded-xl bg-brand-100 text-brand-900 text-xs font-bold hover:bg-brand-200 transition flex items-center gap-1.5 min-h-[44px]"
          >
            <span>← Kembali ke Dashboard Guru</span>
          </NuxtLink>

          <button
            v-if="user"
            @click="logout"
            class="px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-600 transition min-h-[44px]"
          >
            Keluar
          </button>
        </div>
      </div>
    </header>

    <main class="flex-1 w-full">
      <slot />
    </main>
  </div>
</template>
