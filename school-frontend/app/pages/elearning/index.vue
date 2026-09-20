<script setup>
import { ref } from 'vue'

definePageMeta({
  layout: 'default' // Atau layout khusus siswa Anda
})

// Mock Data Siswa
const student = ref({
  name: 'Andi Pratama',
  email: 'siswa@siakad.sch.id',
  class: 'Kelas X IPA 1',
  semester: 'Semester 1 (Ganjil)',
  academicYear: '2026/2027',
  progress: 17
})

// Tab Mata Pelajaran
const activeSubject = ref(1)
const subjects = ref([
  { id: 1, name: 'Matematika Wajib', progress: 50 },
  { id: 2, name: 'Bahasa Indonesia', progress: 0 },
  { id: 3, name: 'Fisika Peminatan', progress: 0 },
  { id: 4, name: 'Bahasa Inggris', progress: 10 }
])

// Linimasa Modul Pembelajaran
const timeline = ref([
  {
    id: 1,
    meeting: 'Pertemuan 1',
    status: 'Sesi Berlangsung',
    duration: '90 Menit',
    title: 'Pertemuan 1: Trigonometri Dasar',
    description: 'Pengenalan konsep sinus, cosinus, dan tangen pada segitiga siku-siku.',
    materials: [
      { name: 'Modul 1: Konsep Trigonometri PDF', type: 'pdf', link: '#' },
      { name: 'Quiz Pertemuan 1: Trigonometri Dasar (15m)', type: 'quiz', link: '#' }
    ]
  },
  {
    id: 2,
    meeting: 'Pertemuan 2',
    status: 'Mendatang',
    duration: '90 Menit',
    title: 'Pertemuan 2: Identitas Trigonometri',
    description: 'Pembahasan rumus identitas trigonometri dan pembuktian persamaan sederhana.',
    materials: [
      { name: 'Modul 2: Identitas Trigonometri PDF', type: 'pdf', link: '#' },
      { name: 'Tugas Latihan Soal 1', type: 'task', link: '#' }
    ]
  }
])

const handleLogout = () => {
  if (confirm('Apakah Anda yakin ingin keluar?')) {
    // Logika logout
    useCookie('auth_token').value = null
    navigateTo('/login')
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased pb-12">
    
    <!-- Top Navigation Bar -->
    <header class="bg-white border-b border-slate-100 sticky top-0 z-30 shadow-xs">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        
        <!-- Brand Logo -->
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-extrabold text-xl shadow-md shadow-emerald-600/20">
            S
          </div>
          <span class="font-bold text-lg text-slate-800 tracking-tight">SIAKAD</span>
        </div>

        <!-- Navigation Links -->
        <nav class="hidden md:flex items-center gap-2">
          <button class="flex items-center gap-2 px-4 py-2 bg-emerald-50 text-emerald-700 font-semibold text-xs rounded-xl border border-emerald-100 transition">
            <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C20.832 18.477 19.247 18 17.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            Dashboard Belajar
          </button>
          <button class="flex items-center gap-2 px-4 py-2 text-slate-600 hover:bg-slate-100 font-medium text-xs rounded-xl transition">
            <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            Profil Saya
          </button>
        </nav>

        <!-- User Info & Logout -->
        <div class="flex items-center gap-3">
          <span class="px-2.5 py-1 bg-emerald-100/70 text-emerald-800 text-[10px] font-bold tracking-wider rounded-lg uppercase">
            SISWA
          </span>

          <div class="hidden sm:block text-right">
            <p class="text-xs font-bold text-slate-800 leading-tight">{{ student.name }}</p>
            <p class="text-[10px] text-slate-400">{{ student.email }}</p>
          </div>

          <div class="w-9 h-9 bg-slate-100 text-slate-700 font-bold text-sm rounded-full flex items-center justify-center border border-slate-200">
            A
          </div>

          <button 
            @click="handleLogout"
            class="px-3 py-1.5 text-xs font-medium text-slate-600 hover:text-rose-600 hover:bg-rose-50 border border-slate-200 hover:border-rose-200 rounded-xl transition"
          >
            Keluar
          </button>
        </div>

      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 space-y-6">

      <!-- Hero Welcome Card (Hijau Emerald Dark Gradient) -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-900 via-emerald-800 to-slate-900 text-white p-6 sm:p-8 shadow-xl">
        <!-- Background Decorative Circles -->
        <div class="absolute -right-12 -top-12 w-64 h-64 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>
        <div class="absolute right-1/3 -bottom-12 w-48 h-48 bg-emerald-400/10 rounded-full blur-xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-3">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/20 backdrop-blur-md border border-emerald-400/30 text-[11px] font-medium text-emerald-200">
              <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
              </svg>
              Portal Pembelajaran Siswa
            </span>
            
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
              Selamat Datang, {{ student.name }}
            </h1>

            <p class="text-xs sm:text-sm text-emerald-100/80 font-normal">
              {{ student.class }} <span class="mx-1">•</span> {{ student.semester }} <span class="mx-1">•</span> Tahun Ajaran {{ student.academicYear }}
            </p>
          </div>

          <!-- Progress Card Right -->
          <div class="bg-white/10 backdrop-blur-md border border-white/10 p-5 rounded-2xl flex flex-col items-center justify-center min-w-[200px]">
            <span class="text-[10px] uppercase tracking-wider font-semibold text-emerald-200 mb-1">Capaian Progres</span>
            <span class="text-3xl font-black tracking-tight text-white mb-3">{{ student.progress }}%</span>
            
            <button class="w-full py-2 px-4 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-xl shadow-sm transition flex items-center justify-center gap-2">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              Profil Saya
            </button>
          </div>
        </div>
      </div>

      <!-- Subject Tabs / Mata Pelajaran -->
      <div class="flex items-center gap-3 overflow-x-auto pb-2 scrollbar-none">
        <button 
          v-for="sub in subjects" 
          :key="sub.id"
          @click="activeSubject = sub.id"
          class="px-4 py-2.5 rounded-2xl text-xs font-semibold transition flex items-center gap-2 border whitespace-nowrap shadow-xs"
          :class="activeSubject === sub.id 
            ? 'bg-emerald-600 text-white border-emerald-600 shadow-emerald-600/20' 
            : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50 hover:text-slate-900'"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C20.832 18.477 19.247 18 17.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
          <span>{{ sub.name }}</span>
          <span 
            class="px-2 py-0.5 rounded-full text-[10px] font-bold"
            :class="activeSubject === sub.id ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-500'"
          >
            {{ sub.progress }}%
          </span>
        </button>
      </div>

      <!-- Timeline Box -->
      <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 sm:p-8 space-y-6">
        
        <!-- Section Title -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-5">
          <div>
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
              <h2 class="text-base font-bold text-slate-800">Linimasa Pembelajaran Modul</h2>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">Alur kuis interaktif & materi ajar pertemuan mata pelajaran</p>
          </div>

          <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 self-start sm:self-auto">
            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            2 Pertemuan Terjadwal
          </div>
        </div>

        <!-- Timeline Items -->
        <div class="relative pl-6 sm:pl-8 space-y-8 before:absolute before:left-2.5 sm:before:left-3.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-slate-200">
          
          <div v-for="(item, index) in timeline" :key="item.id" class="relative group">
            
            <!-- Timeline Indicator Circle -->
            <div 
              class="absolute -left-6 sm:-left-8 top-1.5 w-5 h-5 rounded-full border-4 border-white shadow-sm flex items-center justify-center transition"
              :class="index === 0 ? 'bg-emerald-600 ring-4 ring-emerald-100' : 'bg-slate-300'"
            ></div>

            <!-- Content Card -->
            <div class="bg-slate-50/60 hover:bg-slate-50 border border-slate-200/80 rounded-2xl p-5 space-y-4 transition">
              
              <!-- Card Header -->
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div class="flex items-center gap-2.5">
                  <span class="px-2.5 py-1 bg-white border border-slate-200 text-slate-700 font-bold text-xs rounded-lg shadow-xs">
                    {{ item.meeting }}
                  </span>
                  <span 
                    class="px-2.5 py-1 text-[11px] font-bold rounded-lg"
                    :class="index === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'"
                  >
                    {{ item.status }}
                  </span>
                </div>
                <span class="text-xs font-medium text-slate-400">Durasi: {{ item.duration }}</span>
              </div>

              <!-- Title & Description -->
              <div>
                <h3 class="text-sm sm:text-base font-bold text-slate-800">{{ item.title }}</h3>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ item.description }}</p>
              </div>

              <!-- Materials & Actions -->
              <div class="flex flex-wrap items-center gap-3 pt-2">
                <template v-for="(mat, idx) in item.materials" :key="idx">
                  
                  <!-- PDF Material Button -->
                  <a 
                    v-if="mat.type === 'pdf'" 
                    :href="mat.link"
                    class="px-3.5 py-2 bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold flex items-center gap-2 transition shadow-xs"
                  >
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>{{ mat.name }}</span>
                    <span class="text-[9px] uppercase font-bold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">PDF</span>
                  </a>

                  <!-- Quiz Button -->
                  <a 
                    v-else-if="mat.type === 'quiz'" 
                    :href="mat.link"
                    class="px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold flex items-center gap-2 transition shadow-xs"
                  >
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    <span>{{ mat.name }}</span>
                  </a>

                  <!-- Task Button -->
                  <a 
                    v-else 
                    :href="mat.link"
                    class="px-3.5 py-2 bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-800 rounded-xl text-xs font-semibold flex items-center gap-2 transition shadow-xs"
                  >
                    <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <span>{{ mat.name }}</span>
                  </a>

                </template>
              </div>

            </div>
          </div>

        </div>

      </div>

    </main>
  </div>
</template>

<style scoped>
/* Menghilangkan scrollbar untuk navigasi tab horizontal */
.scrollbar-none::-webkit-scrollbar {
  display: none;
}
.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>