<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

definePageMeta({ layout: 'teacher', middleware: 'auth' })

const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'

interface CourseItem {
  id: number
  subject?: { id: number; name: string; code: string }
  class?: { id: number; class_name?: { name: string }; className?: { name: string }; semester?: { academic_year: string; semester_type: string } }
  teacher?: { id: number; name: string }
  students_count?: number
  meets_count?: number
}

const loading = ref(true)
const courses = ref<CourseItem[]>([])
const search = ref('')

const api = async <T>(path: string) => {
  return await $fetch<T>(`${apiBase}${path}`, {
    headers: {
      Authorization: `Bearer ${token.value}`,
      Accept: 'application/json',
    },
  })
}

const fetchCourses = async () => {
  loading.value = true
  try {
    const res: any = await api('/courses?my_courses=true&all=true')
    courses.value = res.data || (Array.isArray(res) ? res : [])
  } catch (err) {
    console.error('Gagal mengambil daftar kelas guru:', err)
  } finally {
    loading.value = false
  }
}

const filteredCourses = computed(() => {
  if (!search.value.trim()) return courses.value
  const q = search.value.toLowerCase()
  return courses.value.filter(c => {
    const sName = c.subject?.name?.toLowerCase() || ''
    const cName = (c.class?.class_name?.name || c.class?.className?.name || '').toLowerCase()
    return sName.includes(q) || cName.includes(q)
  })
})

onMounted(() => {
  fetchCourses()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
            Kurikulum Merdeka
          </span>
          <span class="text-xs text-slate-400">TP, Asesmen Formatif & Sumatif</span>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">Buku Nilai Mata Pelajaran</h1>
        <p class="text-xs text-slate-500 mt-1">
          Pilih kelas pengampu untuk mengelola bobot penilaian, asesmen TP, dan rekap nilai akhir siswa.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <div class="relative w-full sm:w-64">
          <input
            v-model="search"
            type="text"
            placeholder="Cari mata pelajaran / kelas..."
            class="w-full pl-9 pr-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none transition shadow-sm"
          />
          <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div v-for="i in 3" :key="i" class="h-44 bg-white rounded-2xl border border-slate-100 p-6 animate-pulse"></div>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredCourses.length === 0" class="bg-white rounded-2xl border border-slate-100 p-12 text-center space-y-3">
      <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
        </svg>
      </div>
      <h3 class="text-sm font-bold text-slate-800">Tidak ada kelas ditemukan</h3>
      <p class="text-xs text-slate-500 max-w-sm mx-auto">
        Belum ada kelas plotting yang diampu atau pencarian Anda tidak cocok dengan mata pelajaran mana pun.
      </p>
    </div>

    <!-- Grid Courses -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="c in filteredCourses"
        :key="c.id"
        class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-md hover:border-emerald-200 transition flex flex-col justify-between group"
      >
        <div class="space-y-3">
          <div class="flex items-start justify-between">
            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-600 group-hover:bg-emerald-50 group-hover:text-emerald-700 transition">
              {{ c.class?.semester?.academic_year || '2025/2026' }} · {{ c.class?.semester?.semester_type === 'odd' ? 'Ganjil' : 'Genap' }}
            </span>
            <span class="text-[11px] font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md">
              Aktif
            </span>
          </div>

          <div>
            <h3 class="text-base font-bold text-slate-800 group-hover:text-emerald-700 transition">
              {{ c.subject?.name || 'Mata Pelajaran' }}
            </h3>
            <p class="text-xs text-slate-500 font-medium mt-0.5">
              Kelas: {{ c.class?.class_name?.name || c.class?.className?.name || 'Rombel' }}
            </p>
          </div>

          <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-50 text-[11px] text-slate-500">
            <div class="flex items-center gap-1.5">
              <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
              <span>{{ c.students_count || 0 }} Siswa</span>
            </div>
            <div class="flex items-center gap-1.5">
              <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <span>{{ c.meets_count || 0 }} Pertemuan</span>
            </div>
          </div>
        </div>

        <div class="pt-5 mt-3">
          <NuxtLink
            :to="`/teacher/grades/${c.id}`"
            class="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition group-hover:shadow"
          >
            <span>Buka Buku Nilai</span>
            <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>
