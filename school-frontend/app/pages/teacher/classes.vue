<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ClassroomCockpit from '~/components/classroom/ClassroomCockpit.vue'

definePageMeta({ layout: 'teacher', middleware: 'auth' })

const route = useRoute()
const router = useRouter()
const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const loading = ref(true)
const courses = ref<any[]>([])

// Selected Course for Single-Screen Cockpit
const selectedCourseId = ref<number | null>(null)

const subjectName = (course: any) => course?.subject?.name || 'Mata pelajaran'
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

onMounted(async () => {
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
})

watch(() => route.query.course_id, (newId) => {
  if (newId) {
    selectedCourseId.value = Number(newId)
  }
})
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
      <!-- Back to Dashboard / Cockpit active header -->
      <div class="flex items-center justify-between pb-4">
        <NuxtLink to="/teacher/dashboard" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition flex items-center gap-1.5">
          <span>←</span>
          <span>Kembali ke Dashboard Guru</span>
        </NuxtLink>

        <span v-if="selectedCourseId" class="text-xs text-slate-400 font-medium">
          Mode Cockpit Aktif
        </span>
      </div>

      <!-- ACTIVE SINGLE-SCREEN CLASSROOM COCKPIT -->
      <div v-if="selectedCourseId" class="mt-2">
        <ClassroomCockpit
          :course-id="selectedCourseId"
          @close="closeCockpit"
        />
      </div>

      <!-- CLASS LIST (SELECTION VIEW) -->
      <div v-else>
        <div class="mt-2 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
          <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Kelas Pengampuan Saya</h1>
            <p class="mt-1 text-sm text-slate-500">Pilih salah satu kelas untuk mengelola materi, presensi, CBT, dan nilai dalam satu layar kerja terpadu (Cockpit).</p>
          </div>
        </div>

        <div v-if="loading" class="mt-6 rounded-3xl bg-white p-12 text-center text-sm text-slate-400 shadow-xs border border-slate-100">
          Memuat daftar kelas pengampuan...
        </div>
        <div v-else-if="courses.length" class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="course in courses"
            :key="course.id"
            class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xs flex flex-col justify-between hover:border-emerald-400 hover:shadow-md transition group"
          >
            <div>
              <div class="flex items-center justify-between gap-2">
                <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-800 border border-emerald-200/60">
                  Kelas Pengampuan
                </span>
                <span class="text-[11px] font-mono font-bold text-slate-400">
                  {{ course.meets_count || 0 }} Pertemuan
                </span>
              </div>
              <h2 class="mt-3 text-lg font-black text-slate-900 group-hover:text-emerald-700 transition">
                {{ subjectName(course) }}
              </h2>
              <p class="mt-1 text-xs text-slate-600 font-bold flex items-center gap-1.5">
                <span>🏫</span>
                <span>{{ className(course) }}</span>
              </p>
              <p class="mt-2 text-xs text-slate-400">
                Akses materi modul, presensi 1-klik, dan buku nilai kurikulum merdeka.
              </p>
            </div>

            <div class="mt-6 border-t border-slate-100 pt-4 flex flex-col gap-2">
              <button
                type="button"
                class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-xs"
                @click="openCockpit(course.id)"
              >
                <span>🚀</span>
                <span>Buka Cockpit Kelas (1 Layar)</span>
              </button>
              <div class="flex items-center justify-between gap-2 text-[11px]">
                <NuxtLink
                  :to="`/elearning/courses/${course.id}`"
                  class="flex-1 text-center py-1.5 text-slate-500 hover:text-slate-900 hover:underline"
                >
                  Lihat Detail Kursus
                </NuxtLink>
                <NuxtLink
                  :to="`/teacher/grades/${course.id}`"
                  class="flex-1 text-center py-1.5 text-emerald-700 hover:text-emerald-900 font-bold hover:underline"
                >
                  Buku Nilai
                </NuxtLink>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="mt-6 rounded-3xl border border-slate-100 bg-white p-12 text-center shadow-xs">
          <p class="font-bold text-slate-700">Belum ada kelas pengampuan.</p>
          <p class="mt-1 text-sm text-slate-500">Admin perlu memasangkan mata pelajaran kepada akun guru ini terlebih dahulu.</p>
        </div>
      </div>
    </div>
  </main>
</template>

