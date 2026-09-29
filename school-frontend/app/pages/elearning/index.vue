<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

definePageMeta({
  layout: 'default',
  middleware: 'auth'
})

const route = useRoute()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const storageBase = config.public.storageBase || 'http://localhost:8000/storage'
const token = useCookie<string | null>('auth_token')
const { user, logout } = useAuth()

const loading = ref(true)
const meetsLoading = ref(false)
const courses = ref<any[]>([])
const activeCourseId = ref<number | null>(null)
const meetsList = ref<any[]>([])
const mySubmissions = ref<any[]>([])
const timelineFilter = ref<'all' | 'ongoing' | 'completed'>('all')

const studentName = computed(() => user.value?.name || user.value?.username || 'Siswa')
const studentEmail = computed(() => user.value?.email || `${user.value?.username || 'siswa'}@man11jakarta.sch.id`)

const activeCourse = computed(() => {
  return courses.value.find(c => c.id === activeCourseId.value) || courses.value[0] || null
})

const studentClass = computed(() => {
  if (activeCourse.value?.class?.className?.name) {
    return activeCourse.value.class.className.name
  }
  if (activeCourse.value?.class?.class_name?.name) {
    return activeCourse.value.class.class_name.name
  }
  return 'Kelas X IPA 1'
})

// Hitung persentase progress belajar pada mata pelajaran aktif
const courseProgress = computed(() => {
  if (!meetsList.value.length) return 0
  const allQuizzes = meetsList.value.flatMap(m => m.quizzes || [])
  if (!allQuizzes.length) return 100 // Jika belum ada kuis, anggap materi siap dipelajari
  const submittedQuizIds = new Set(mySubmissions.value.map(s => s.quiz_id))
  const completedCount = allQuizzes.filter(q => submittedQuizIds.has(q.id)).length
  return Math.round((completedCount / allQuizzes.length) * 100)
})

const fetchCourses = async () => {
  loading.value = true
  try {
    const res: any = await $fetch(`${apiBase}/courses?my_courses=true&all=true`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    courses.value = Array.isArray(res) ? res : res?.data || []

    // Pilih course dari query param jika ada, atau course pertama
    const queryCourseId = Number(route.query.course)
    if (queryCourseId && courses.value.some(c => c.id === queryCourseId)) {
      activeCourseId.value = queryCourseId
    } else if (courses.value.length > 0) {
      activeCourseId.value = courses.value[0].id
    }
  } catch (err) {
    console.error('Fetch courses error:', err)
  } finally {
    loading.value = false
  }
}

const fetchMeets = async () => {
  if (!activeCourseId.value) {
    meetsList.value = []
    return
  }
  meetsLoading.value = true
  try {
    const res: any = await $fetch(`${apiBase}/meets?course_id=${activeCourseId.value}&with_details=true&paginate=false`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    meetsList.value = Array.isArray(res) ? res : res?.data || []
  } catch (err) {
    console.error('Fetch meets error:', err)
  } finally {
    meetsLoading.value = false
  }
}

const fetchSubmissions = async () => {
  try {
    const res: any = await $fetch(`${apiBase}/submissions?my_submissions=true`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    mySubmissions.value = Array.isArray(res) ? res : res?.data || []
  } catch (err) {
    console.error('Fetch submissions error:', err)
  }
}

watch(activeCourseId, () => {
  fetchMeets()
})

onMounted(async () => {
  await Promise.all([fetchCourses(), fetchSubmissions()])
  if (activeCourseId.value) {
    await fetchMeets()
  }
})

const isQuizSubmitted = (quizId: number) => {
  return mySubmissions.value.some(s => s.quiz_id === quizId)
}

const getQuizSubmission = (quizId: number) => {
  return mySubmissions.value.find(s => s.quiz_id === quizId)
}

const filteredMeets = computed(() => {
  if (timelineFilter.value === 'all') return meetsList.value
  if (timelineFilter.value === 'completed') {
    // Sesi selesai jika semua kuis di dalamnya sudah dikerjakan
    return meetsList.value.filter(m => {
      const qz = m.quizzes || []
      return qz.length > 0 && qz.every((q: any) => isQuizSubmitted(q.id))
    })
  }
  if (timelineFilter.value === 'ongoing') {
    return meetsList.value.filter(m => {
      const qz = m.quizzes || []
      return qz.length === 0 || qz.some((q: any) => !isQuizSubmitted(q.id))
    })
  }
  return meetsList.value
})

const getMaterialLink = (mat: any) => {
  if (mat.file_url) return mat.file_url
  if (mat.file_path) return `${storageBase}/${mat.file_path.replace(/^\/+/, '')}`
  return mat.external_url || '#'
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased pb-16">
    
    <!-- Top Navigation Bar -->
    <header class="bg-white border-b border-slate-100 sticky top-0 z-30 shadow-xs">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        
        <!-- Brand Logo -->
        <NuxtLink to="/student/dashboard" class="flex items-center gap-3">
          <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-extrabold text-xl shadow-md shadow-emerald-600/20">
            M
          </div>
          <div>
            <span class="font-bold text-base text-slate-900 tracking-tight block">MAN 11 JAKARTA</span>
            <span class="text-[10px] text-emerald-600 font-semibold tracking-wider uppercase block">Portal E-Learning</span>
          </div>
        </NuxtLink>

        <!-- Navigation Links -->
        <nav class="hidden md:flex items-center gap-2">
          <NuxtLink to="/student/dashboard" class="flex items-center gap-2 px-3.5 py-2 text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium text-xs rounded-xl transition">
            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Dashboard
          </NuxtLink>
          <NuxtLink to="/elearning" class="flex items-center gap-2 px-3.5 py-2 bg-emerald-50 text-emerald-700 font-bold text-xs rounded-xl border border-emerald-100 transition">
            <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C20.832 18.477 19.247 18 17.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            Linimasa Belajar
          </NuxtLink>
        </nav>

        <!-- User Info & Logout -->
        <div class="flex items-center gap-3">
          <span class="px-2.5 py-1 bg-emerald-100/70 text-emerald-800 text-[10px] font-bold tracking-wider rounded-lg uppercase">
            SISWA
          </span>

          <div class="hidden sm:block text-right">
            <p class="text-xs font-bold text-slate-800 leading-tight">{{ studentName }}</p>
            <p class="text-[10px] text-slate-400">{{ studentEmail }}</p>
          </div>

          <div class="w-9 h-9 bg-emerald-100 text-emerald-800 font-bold text-sm rounded-full flex items-center justify-center border border-emerald-200">
            {{ studentName.charAt(0).toUpperCase() }}
          </div>

          <button 
            @click="logout"
            class="px-3 py-1.5 text-xs font-semibold text-slate-600 hover:text-rose-600 hover:bg-rose-50 border border-slate-200 hover:border-rose-200 rounded-xl transition"
          >
            Keluar
          </button>
        </div>

      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 space-y-6">

      <!-- Hero Welcome Card (Hijau Emerald Dark Gradient) -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-950 via-emerald-900 to-slate-900 text-white p-6 sm:p-8 shadow-xl">
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
              Portal Pembelajaran Siswa Aktif
            </span>
            
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
              Selamat Datang, {{ studentName }}!
            </h1>

            <p class="text-xs sm:text-sm text-emerald-100/80 font-normal">
              {{ studentClass }} <span class="mx-1.5">•</span> 
              Mata Pelajaran Aktif: <b class="text-white">{{ activeCourse?.subject?.name || 'Pilih Mata Pelajaran' }}</b> 
              <span v-if="activeCourse?.teacher?.name"> (Guru: {{ activeCourse.teacher.name }})</span>
            </p>
          </div>

          <!-- Progress Card Right -->
          <div class="bg-white/10 backdrop-blur-md border border-white/10 p-5 rounded-2xl flex flex-col items-center justify-center min-w-[200px]">
            <span class="text-[10px] uppercase tracking-wider font-semibold text-emerald-200 mb-1">Capaian Tugas Mapel</span>
            <span class="text-3xl font-black tracking-tight text-white mb-2">{{ courseProgress }}%</span>
            
            <div class="w-full bg-white/20 rounded-full h-1.5 overflow-hidden mb-3">
              <div class="bg-emerald-400 h-1.5 rounded-full transition-all duration-500" :style="{ width: `${courseProgress}%` }"></div>
            </div>

            <NuxtLink 
              v-if="activeCourseId" 
              :to="`/elearning/courses/${activeCourseId}`" 
              class="w-full py-2 px-3 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-xl shadow-xs transition text-center"
            >
              Lihat Detail Kursus →
            </NuxtLink>
          </div>
        </div>
      </div>

      <!-- Subject Tabs / Mata Pelajaran Terdaftar -->
      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Mata Pelajaran Kelas Anda:</span>
          <span class="text-xs text-slate-400">{{ courses.length }} Mata Pelajaran Terdaftar</span>
        </div>

        <div v-if="loading" class="flex gap-3 overflow-x-auto pb-2">
          <div v-for="i in 3" :key="i" class="h-11 w-44 bg-slate-200 animate-pulse rounded-2xl"></div>
        </div>

        <div v-else-if="courses.length === 0" class="p-6 bg-white rounded-2xl border border-slate-200 text-center text-xs text-slate-400">
          Belum ada mata pelajaran yang terdaftar untuk rombel kelas Anda.
        </div>

        <div v-else class="flex items-center gap-3 overflow-x-auto pb-2 scrollbar-none">
          <button 
            v-for="c in courses" 
            :key="c.id"
            @click="activeCourseId = c.id"
            class="px-4 py-2.5 rounded-2xl text-xs font-semibold transition flex items-center gap-2 border whitespace-nowrap shadow-xs cursor-pointer"
            :class="activeCourseId === c.id 
              ? 'bg-emerald-600 text-white border-emerald-600 shadow-emerald-600/20' 
              : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50 hover:text-slate-900'"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C20.832 18.477 19.247 18 17.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            <span>{{ c.subject?.name || 'Mata Pelajaran' }}</span>
            <span 
              class="px-2 py-0.5 rounded-full text-[10px] font-bold"
              :class="activeCourseId === c.id ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-500'"
            >
              {{ c.meets_count || 0 }} Pertemuan
            </span>
          </button>
        </div>
      </div>

      <!-- Timeline Box -->
      <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 sm:p-8 space-y-6">
        
        <!-- Section Header & Filters -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-5">
          <div>
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
              <h2 class="text-base font-bold text-slate-800">
                Linimasa Sesi Pertemuan: {{ activeCourse?.subject?.name || 'Mata Pelajaran' }}
              </h2>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">
              Urutan sesi belajar, modul materi ajar, dan tugas asesmen yang disusun oleh guru
            </p>
          </div>

          <!-- Filter Sesi -->
          <div class="flex items-center gap-2 self-start sm:self-auto bg-slate-50 p-1 rounded-xl border border-slate-200">
            <button 
              @click="timelineFilter = 'all'"
              class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
              :class="timelineFilter === 'all' ? 'bg-white text-emerald-700 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
            >
              Semua ({{ meetsList.length }})
            </button>
            <button 
              @click="timelineFilter = 'ongoing'"
              class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
              :class="timelineFilter === 'ongoing' ? 'bg-white text-emerald-700 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
            >
              Perlu Dikerjakan
            </button>
            <button 
              @click="timelineFilter = 'completed'"
              class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
              :class="timelineFilter === 'completed' ? 'bg-white text-emerald-700 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
            >
              Selesai
            </button>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="meetsLoading" class="py-12 text-center text-xs text-slate-400 space-y-3">
          <div class="w-8 h-8 border-3 border-emerald-600 border-t-transparent rounded-full animate-spin mx-auto"></div>
          <p>Memuat sesi pertemuan dan modul belajar...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredMeets.length === 0" class="py-12 text-center text-xs text-slate-400 space-y-2">
          <svg class="w-12 h-12 text-slate-300 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <p class="font-semibold text-slate-600">Belum ada sesi pertemuan pada kategori ini.</p>
          <p>Guru pengampu belum menerbitkan pertemuan untuk mata pelajaran ini.</p>
        </div>

        <!-- Timeline Items (Dynamic Real Data) -->
        <div v-else class="relative pl-6 sm:pl-8 space-y-8 before:absolute before:left-2.5 sm:before:left-3.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-slate-200">
          
          <div v-for="(item, index) in filteredMeets" :key="item.id" class="relative group">
            
            <!-- Timeline Indicator Circle -->
            <div 
              class="absolute -left-6 sm:-left-8 top-1.5 w-5 h-5 rounded-full border-4 border-white shadow-sm flex items-center justify-center transition"
              :class="index === 0 ? 'bg-emerald-600 ring-4 ring-emerald-100' : 'bg-slate-400'"
            ></div>

            <!-- Content Card -->
            <div class="bg-slate-50/70 hover:bg-slate-50 border border-slate-200/90 rounded-2xl p-5 space-y-4 transition shadow-xs">
              
              <!-- Card Header -->
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div class="flex items-center gap-2.5 flex-wrap">
                  <span class="px-2.5 py-1 bg-white border border-slate-200 text-slate-800 font-bold text-xs rounded-lg shadow-xs">
                    Pertemuan {{ item.meeting_number }}
                  </span>
                  
                  <span 
                    class="px-2.5 py-1 text-[11px] font-bold rounded-lg uppercase"
                    :class="item.type === 'online' ? 'bg-sky-100 text-sky-800' : 'bg-slate-200 text-slate-700'"
                  >
                    {{ item.type === 'online' ? '🌐 Tatap Muka Online' : '🏫 Tatap Muka di Kelas' }}
                  </span>

                  <!-- Status Presensi Badge -->
                  <span 
                    v-if="item.my_attendance" 
                    class="px-2.5 py-1 text-[10px] font-extrabold rounded-lg"
                    :class="item.my_attendance.status === 'H' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                  >
                    ✓ {{ item.my_attendance.status_label || 'HADIR' }}
                  </span>
                </div>

                <div class="flex items-center gap-2 text-xs text-slate-400">
                  <span v-if="item.start_time">{{ new Date(item.start_time).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) }}</span>
                  <span v-else>Jadwal Reguler</span>
                </div>
              </div>

              <!-- Title & Description -->
              <div>
                <h3 class="text-base font-bold text-slate-900">{{ item.title }}</h3>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ item.description || 'Tidak ada deskripsi pengantar sesi.' }}</p>
              </div>

              <!-- Online Meeting Button if Available -->
              <div v-if="item.type === 'online' && item.meeting_link" class="p-3 bg-sky-50 border border-sky-100 rounded-xl flex items-center justify-between">
                <div class="flex items-center gap-2 text-xs font-semibold text-sky-900">
                  <svg class="w-4 h-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                  </svg>
                  Tautan Video Conference Aktif
                </div>
                <a 
                  :href="item.meeting_link" 
                  target="_blank" 
                  rel="noopener noreferrer"
                  class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-lg shadow-xs transition"
                >
                  Gabung Pertemuan →
                </a>
              </div>

              <!-- Materials Section -->
              <div v-if="item.materials && item.materials.length > 0" class="space-y-2 pt-2 border-t border-slate-200/60">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Bahan Ajar & Modul Belajar:</p>
                <div class="flex flex-wrap gap-2">
                  <a 
                    v-for="mat in item.materials" 
                    :key="mat.id"
                    :href="getMaterialLink(mat)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-slate-200 hover:border-emerald-300 hover:bg-emerald-50/50 rounded-xl text-xs font-semibold text-slate-700 transition shadow-xs"
                  >
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>{{ mat.title }}</span>
                    <span class="text-[10px] text-slate-400 uppercase">({{ mat.type }})</span>
                  </a>
                </div>
              </div>

              <!-- Quizzes & Tasks Section -->
              <div v-if="item.quizzes && item.quizzes.length > 0" class="space-y-2 pt-2 border-t border-slate-200/60">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Tugas / Kuis Asesmen:</p>
                <div class="grid gap-2 sm:grid-cols-2">
                  <div 
                    v-for="q in item.quizzes" 
                    :key="q.id"
                    class="p-3 bg-white rounded-xl border border-slate-200 flex items-center justify-between gap-3 shadow-xs"
                  >
                    <div>
                      <p class="text-xs font-bold text-slate-800">{{ q.title }}</p>
                      <p class="text-[10px] text-slate-400">
                        {{ isQuizSubmitted(q.id) ? '✓ Sudah dikumpulkan' : 'Belum dikumpulkan' }}
                      </p>
                    </div>

                    <NuxtLink 
                      :to="`/elearning/meets/${item.id}?tab=quizzes`"
                      class="px-3 py-1.5 text-xs font-bold rounded-lg transition"
                      :class="isQuizSubmitted(q.id) 
                        ? 'bg-slate-100 text-slate-600 hover:bg-slate-200' 
                        : 'bg-emerald-600 text-white hover:bg-emerald-700 shadow-xs'"
                    >
                      {{ isQuizSubmitted(q.id) ? 'Lihat Nilai' : 'Kerjakan' }}
                    </NuxtLink>
                  </div>
                </div>
              </div>

              <!-- Footer Actions -->
              <div class="pt-3 border-t border-slate-200/60 flex items-center justify-between">
                <span class="text-xs text-slate-500 font-medium">
                  {{ item.materials?.length || 0 }} Materi · {{ item.quizzes?.length || 0 }} Kuis · {{ item.discussions_count || 0 }} Diskusi
                </span>

                <NuxtLink 
                  :to="`/elearning/meets/${item.id}`"
                  class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition"
                >
                  Buka Sesi Pertemuan →
                </NuxtLink>
              </div>

            </div>

          </div>

        </div>

      </div>

    </main>

  </div>
</template>