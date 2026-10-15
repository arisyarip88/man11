<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ClassroomCockpit from '~/components/classroom/ClassroomCockpit.vue'
import AppButton from '~/components/ui/AppButton.vue'
import AppBadge from '~/components/ui/AppBadge.vue'
import AppCard from '~/components/ui/AppCard.vue'
import AppSkeleton from '~/components/ui/AppSkeleton.vue'
import AppEmptyState from '~/components/ui/AppEmptyState.vue'

definePageMeta({ layout: 'teacher', middleware: 'auth' })

const route = useRoute()
const router = useRouter()
const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const loading = ref(true)
const courses = ref<any[]>([])

const selectedCourseId = ref<number | null>(null)

// Search & Filter State
const searchQuery = ref('')
const gradeFilter = ref('') // '', '10', '11', '12'

// Duplication Modal State
const duplicateModalOpen = ref(false)
const duplicatingCourse = ref<any>(null)
const selectedTargetClassId = ref<string | number>('')
const duplicateLoading = ref(false)
const duplicateError = ref('')
const availableRombels = ref<any[]>([])
const fetchingRombels = ref(false)

const subjectName = (course: any) => course?.subject?.name || 'Mata Pelajaran'
const className = (course: any) => course?.class?.class_name?.name || course?.class?.className?.name || 'Kelas'

const openCockpit = (id: number) => {
  selectedCourseId.value = id
  router.replace({ query: { ...route.query, course_id: String(id) } })
}

const closeCockpit = () => {
  selectedCourseId.value = null
  const q = { ...route.query }
  delete q.course_id
  router.replace({ query: q })
}

const fetchCourses = async () => {
  loading.value = true
  try {
    const response: any = await $fetch(`${apiBase}/courses?my_courses=true&all=true`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    courses.value = response?.data || response || []

    if (route.query.course_id) {
      selectedCourseId.value = Number(route.query.course_id)
    }
  } catch (error) {
    console.error('Gagal memuat kelas pengampuan:', error)
  } finally {
    loading.value = false
  }
}

// Filtered Courses
const filteredCourses = computed(() => {
  let list = courses.value

  if (gradeFilter.value) {
    list = list.filter(c => {
      const cls = className(c).toUpperCase()
      if (gradeFilter.value === '10') return cls.includes('X-') || cls.includes('10') || cls.startsWith('X ')
      if (gradeFilter.value === '11') return cls.includes('XI-') || cls.includes('11') || cls.startsWith('XI ')
      if (gradeFilter.value === '12') return cls.includes('XII-') || cls.includes('12') || cls.startsWith('XII ')
      return true
    })
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter(c =>
      subjectName(c).toLowerCase().includes(q) ||
      className(c).toLowerCase().includes(q) ||
      (c.class?.room_number && c.class.room_number.toLowerCase().includes(q))
    )
  }

  return list
})

// Statistics Counters
const totalStudents = computed(() => {
  return courses.value.reduce((acc, c) => acc + (c.class?.capacity || c.class?.students_count || 36), 0)
})

const totalMeets = computed(() => {
  return courses.value.reduce((acc, c) => acc + (c.meets_count || 0), 0)
})

const activeCourse = computed(() => {
  return courses.value.find(c => c.id === selectedCourseId.value) || null
})

// Duplication Logic
const openDuplicateModal = async (course: any) => {
  duplicatingCourse.value = course
  selectedTargetClassId.value = ''
  duplicateError.value = ''
  duplicateModalOpen.value = true

  if (availableRombels.value.length === 0) {
    fetchingRombels.value = true
    try {
      const res: any = await $fetch(`${apiBase}/rombel?all=true`, {
        headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
      })
      availableRombels.value = res?.data || res || []
    } catch (e) {
      console.error('Gagal mengambil daftar rombel:', e)
    } finally {
      fetchingRombels.value = false
    }
  }
}

const executeDuplicateCourse = async () => {
  if (!selectedTargetClassId.value || !duplicatingCourse.value) return
  duplicateLoading.value = true
  duplicateError.value = ''

  try {
    const res: any = await $fetch(`${apiBase}/courses/${duplicatingCourse.value.id}/duplicate`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
      body: { class_id: selectedTargetClassId.value }
    })
    alert(res.message || 'Pembelajaran dan seluruh pertemuannya berhasil diduplikasi ke kelas target!')
    duplicateModalOpen.value = false
    await fetchCourses()
  } catch (err: any) {
    if (err?.data?.error_code === 'course_already_exists') {
      duplicateError.value = 'Mata pelajaran ini sudah ada di kelas/rombel tujuan. Silakan pilih kelas lain.'
    } else {
      duplicateError.value = err?.data?.message || 'Gagal menduplikat kelas. Silakan coba lagi.'
    }
  } finally {
    duplicateLoading.value = false
  }
}

onMounted(async () => {
  await fetchCourses()
})

watch(() => route.query.course_id, (newId) => {
  if (newId) {
    selectedCourseId.value = Number(newId)
  }
})
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8 font-sans">
    <div class="mx-auto max-w-7xl space-y-5">
      <!-- ============================================================== -->
      <!-- VIEW A: ACTIVE COCKPIT WORKSPACE (DETAIL KELAS TERPADU)        -->
      <!-- ============================================================== -->
      <div v-if="selectedCourseId" class="space-y-3">
        <!-- Quick Back Link -->
        <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
          <button
            type="button"
            class="hover:text-emerald-700 transition flex items-center gap-1.5"
            @click="closeCockpit"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span>&larr; Kembali ke Daftar Kelas</span>
          </button>
          <div v-if="activeCourse" class="text-slate-400 font-medium">
            {{ subjectName(activeCourse) }} &bull; {{ className(activeCourse) }}
          </div>
        </div>

        <!-- The Full Classroom Cockpit Component (Unified & Self-Contained) -->
        <ClassroomCockpit
          :course-id="selectedCourseId"
          @close="closeCockpit"
        />
      </div>

      <!-- ============================================================== -->
      <!-- VIEW B: DAFTAR KELAS (MINIMALIS, BERSIH, MUDAH DIPAHAMI)       -->
      <!-- ============================================================== -->
      <div v-else class="space-y-5">
        <!-- Clean Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200/80">
          <div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
              Kelas Pengampuan
            </h1>
            <p class="mt-0.5 text-xs text-slate-500">
              Pilih kelas untuk mengelola materi, tugas, dan presensi tatap muka.
            </p>
          </div>

          <!-- Presensi Global Shortcut -->
          <NuxtLink
            to="/teacher/attendance"
            class="self-start sm:self-auto px-3 py-1.5 rounded-xl border border-emerald-200 bg-emerald-50/80 hover:bg-emerald-100 text-emerald-800 text-xs font-bold transition shadow-2xs flex items-center gap-1.5"
          >
            <svg class="w-3.5 h-3.5 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
            </svg>
            <span>Menu Presensi &rarr;</span>
          </NuxtLink>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="relative flex-1 max-w-sm">
            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </span>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari mata pelajaran atau kelas..."
              class="w-full pl-9 pr-3 py-2 text-xs rounded-xl bg-white border border-slate-200 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition shadow-2xs"
            />
          </div>

          <!-- Tingkat Filter Pills -->
          <div class="flex items-center gap-1.5 overflow-x-auto">
            <button
              type="button"
              class="px-3 py-1.5 rounded-xl text-xs transition whitespace-nowrap"
              :class="gradeFilter === '' ? 'bg-slate-900 text-white font-bold' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 font-medium'"
              @click="gradeFilter = ''"
            >
              Semua ({{ courses.length }})
            </button>
            <button
              type="button"
              class="px-3 py-1.5 rounded-xl text-xs transition whitespace-nowrap"
              :class="gradeFilter === '10' ? 'bg-emerald-600 text-white font-bold' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 font-medium'"
              @click="gradeFilter = '10'"
            >
              Kelas 10
            </button>
            <button
              type="button"
              class="px-3 py-1.5 rounded-xl text-xs transition whitespace-nowrap"
              :class="gradeFilter === '11' ? 'bg-emerald-600 text-white font-bold' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 font-medium'"
              @click="gradeFilter = '11'"
            >
              Kelas 11
            </button>
            <button
              type="button"
              class="px-3 py-1.5 rounded-xl text-xs transition whitespace-nowrap"
              :class="gradeFilter === '12' ? 'bg-emerald-600 text-white font-bold' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 font-medium'"
              @click="gradeFilter = '12'"
            >
              Kelas 12
            </button>
          </div>
        </div>

        <!-- Skeleton Loader -->
        <div v-if="loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <AppSkeleton v-for="i in 3" :key="i" height="h-44" class="rounded-2xl" />
        </div>

        <!-- Class Cards Grid (Clean, Minimalist, High UX) -->
        <div v-else-if="filteredCourses.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="course in filteredCourses"
            :key="course.id"
            class="group bg-white border border-slate-200/90 hover:border-emerald-400 rounded-2xl p-4 sm:p-5 shadow-2xs hover:shadow-md transition-all flex flex-col justify-between"
          >
            <!-- Card Upper Section -->
            <div>
              <!-- Rombel & Room Badges -->
              <div class="flex items-center justify-between gap-2">
                <span class="px-2.5 py-0.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200/80 text-xs font-bold">
                  {{ className(course) }}
                </span>
                <span class="text-[11px] font-medium text-slate-400">
                  {{ course.class?.room_number ? `Ruang ${course.class.room_number}` : 'Ruang KBM' }}
                </span>
              </div>

              <!-- Subject Title -->
              <h2 class="mt-3 text-base sm:text-lg font-bold text-slate-900 group-hover:text-emerald-800 transition leading-snug">
                {{ subjectName(course) }}
              </h2>

              <p class="mt-0.5 text-xs text-slate-500 font-medium">
                Semester {{ course.class?.semester?.semester_type || 'Ganjil' }} &bull; Kurikulum Merdeka
              </p>

              <!-- Quick Info Badge Row -->
              <div class="mt-3 flex items-center gap-3 text-xs text-slate-500">
                <span class="flex items-center gap-1.5 font-medium">
                  <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                  </svg>
                  {{ course.class?.capacity || course.class?.students_count || 36 }} Siswa
                </span>
                <span class="text-slate-300">&bull;</span>
                <span class="flex items-center gap-1.5 font-medium">
                  <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  {{ course.meets_count || 0 }} Sub-bab
                </span>
              </div>
            </div>

            <!-- Card Action Footer: Exactly 1 Primary Action + 2 Clean Shortcuts -->
            <div class="mt-4 pt-3.5 border-t border-slate-100 flex items-center gap-2">
              <button
                type="button"
                class="flex-1 py-2 px-3.5 rounded-xl bg-slate-900 hover:bg-emerald-600 text-white text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-2xs group-hover:bg-emerald-600"
                @click="openCockpit(course.id)"
              >
                <span>Buka Kelas</span>
                <svg class="w-3.5 h-3.5 transition group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
              </button>

              <!-- Presensi Shortcut Icon -->
              <NuxtLink
                :to="`/teacher/attendance?course_id=${course.id}`"
                class="p-2 rounded-xl border border-slate-200 hover:border-emerald-300 hover:bg-emerald-50 text-slate-600 hover:text-emerald-800 transition shadow-2xs"
                title="Presensi Kelas"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                </svg>
              </NuxtLink>

              <!-- Duplicate Shortcut Icon -->
              <button
                type="button"
                class="p-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-500 hover:text-slate-800 transition shadow-2xs"
                title="Salin RPP/Materi ke Rombel Lain"
                @click="openDuplicateModal(course)"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <AppEmptyState
          v-else
          title="Tidak ada kelas yang cocok"
          description="Coba ubah kata kunci pencarian atau filter tingkat kelas."
        />
      </div>
    </div>

    <!-- MODAL DUPLIKAT KELAS KE ROMBEL LAIN -->
    <div
      v-if="duplicateModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 overflow-y-auto"
      @click.self="duplicateModalOpen = false"
    >
      <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <div class="flex items-center gap-2.5">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-black text-slate-900">Duplikat ke Kelas Lain</h3>
              <p class="text-xs text-slate-500 font-medium">Salin struktur bab, modul, dan kuis ke rombel baru</p>
            </div>
          </div>
          <button
            type="button"
            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition"
            @click="duplicateModalOpen = false"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div v-if="duplicatingCourse" class="mt-4 space-y-4">
          <!-- Info Sumber -->
          <div class="rounded-xl bg-slate-50 p-3.5 border border-slate-200 text-xs">
            <p class="text-slate-500 font-semibold">Sumber Pengajaran:</p>
            <p class="text-sm font-bold text-slate-900 mt-0.5">
              {{ subjectName(duplicatingCourse) }}
            </p>
            <div class="flex items-center gap-3 mt-1.5 text-slate-600 font-medium">
              <span>{{ className(duplicatingCourse) }}</span>
              <span>&bull;</span>
              <span class="font-mono font-bold text-slate-900">{{ duplicatingCourse.meets_count || 0 }} Pertemuan</span>
            </div>
          </div>

          <!-- Pilihan Kelas Tujuan -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">
              Pilih Rombel / Kelas Tujuan <span class="text-rose-500">*</span>
            </label>
            <div v-if="fetchingRombels" class="py-3 text-center text-xs text-slate-400">
              Memuat daftar rombel...
            </div>
            <select
              v-else
              v-model="selectedTargetClassId"
              class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20"
            >
              <option value="" disabled>-- Pilih Rombel Tujuan --</option>
              <option
                v-for="r in availableRombels"
                :key="r.id"
                :value="r.id"
                :disabled="r.id === duplicatingCourse.class_id"
              >
                {{ r.className?.name || r.name }} {{ r.room_number ? `(${r.room_number})` : '' }} - {{ r.semester?.name || '' }}
                {{ r.id === duplicatingCourse.class_id ? ' (Kelas saat ini)' : '' }}
              </option>
            </select>
          </div>

          <!-- Note Keamanan -->
          <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-xs text-slate-700 flex items-start gap-2">
            <svg class="w-5 h-5 text-slate-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="leading-relaxed font-medium">
              <strong>Info Duplikasi:</strong> Seluruh pertemuan beserta modul dan kuis akan disalin. Data siswa (presensi, jawaban kuis, dan nilai) tidak akan ikut tersalin.
            </div>
          </div>

          <!-- Alert Error -->
          <div v-if="duplicateError" class="rounded-xl bg-rose-50 border border-rose-200 p-3 text-xs text-rose-800 flex items-start gap-2">
            <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="font-medium leading-relaxed">
              {{ duplicateError }}
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
            <AppButton
              variant="outline"
              size="sm"
              @click="duplicateModalOpen = false"
            >
              Batal
            </AppButton>
            <AppButton
              variant="primary"
              size="sm"
              :disabled="duplicateLoading || !selectedTargetClassId"
              @click="executeDuplicateCourse"
            >
              <span>{{ duplicateLoading ? 'Menduplikasi...' : 'Mulai Duplikasi' }}</span>
            </AppButton>
          </div>
        </div>
      </div>
    </div>
  </main>
</template>
