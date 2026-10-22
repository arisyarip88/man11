<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppSkeleton from '~/components/ui/AppSkeleton.vue'
import StudentActivitySidebar from '~/components/student/StudentActivitySidebar.vue'

definePageMeta({ layout: 'student', middleware: 'auth' })

const route = useRoute()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie<string | null>('auth_token')

// Sidebar open/collapse synced with student layout header hamburger
const isSidebarOpen = useState('isStudentSidebarOpen', () => true)

const materialId = computed(() => route.params.id as string)
const loading = ref(true)
const material = ref<any>(null)
const sidebarSearch = ref('')

const api = <T>(path: string, opts?: any) =>
  $fetch<T>(`${apiBase}${path}`, {
    headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
    ...opts
  })

const meet = computed(() => material.value?.meet)
const courseId = computed(() => meet.value?.course_id || meet.value?.course?.id)
const subjectName = computed(() => meet.value?.course?.subject?.name || 'Mata Pelajaran')
const teacherName = computed(() => meet.value?.course?.teacher?.name || 'Guru Pengampu')
const className = computed(() =>
  meet.value?.course?.class?.class_name?.name ||
  meet.value?.course?.class?.className?.name || 'Kelas'
)

const isPdf = computed(() => {
  if (material.value?.type !== 'file') return false
  const path = (material.value?.file_path || material.value?.file_url || '').toLowerCase()
  return path.endsWith('.pdf')
})

const isVideo = computed(() => material.value?.type === 'video')
const isLink = computed(() => material.value?.type === 'link')
const isText = computed(() => material.value?.type === 'text')

// Format YouTube embed URL if applicable
const embedVideoUrl = computed(() => {
  if (!isVideo.value) return null
  const url = material.value?.external_url || ''
  if (!url) return null

  const ytMatch = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/)
  if (ytMatch && ytMatch[1]) {
    return `https://www.youtube.com/embed/${ytMatch[1]}`
  }
  return url
})

const formatDate = (dtStr?: string) => {
  if (!dtStr) return '-'
  try {
    const d = new Date(dtStr)
    return d.toLocaleDateString('id-ID', {
      day: 'numeric', month: 'short', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    }) + ' WIB'
  } catch { return dtStr }
}

const { isActivityDone, isMaterialDownloaded, markMaterialDownloaded } = useStudentActivityProgress()

const fetchData = async () => {
  loading.value = true
  try {
    const res = await api<any>(`/materials/${materialId.value}`)
    material.value = res
    if (res?.id) {
      markMaterialDownloaded(res.id)
    }
  } catch (err) {
    console.error('Gagal memuat materi pembelajaran:', err)
  } finally {
    loading.value = false
  }
}

// -------------------------------------------------------------------
// 6 Kategori Aktivitas Belajar Siswa (Dropdown Sidebar LMS)
// -------------------------------------------------------------------
const getMeetPretests = computed(() => (meet.value?.quizzes || []).filter((q: any) => q.activity_category === 'pretest'))
const getMeetMaterials = computed(() => meet.value?.materials || [])
const getMeetDiscussions = computed(() => meet.value?.discussions || [])
const getMeetQuizzes = computed(() => (meet.value?.quizzes || []).filter((q: any) => q.activity_category === 'quiz' || (!q.activity_category && (q.quiz_type === 'multiple_choice' || q.quiz_type === 'quiz'))))
const getMeetAssignments = computed(() => (meet.value?.quizzes || []).filter((q: any) => q.activity_category === 'assignment' || (!q.activity_category && (q.quiz_type === 'assignment' || q.quiz_type === 'essay'))))
const getMeetPosttests = computed(() => (meet.value?.quizzes || []).filter((q: any) => q.activity_category === 'posttest'))

const activityMenuItems = computed(() => [
  {
    key: 'pretest',
    label: 'Pretest',
    isDone: isActivityDone(meet.value, 'pretest'),
    active: false,
    url: getMeetPretests.value.length > 0 ? `/student/quizzes/${getMeetPretests.value[0].id}/onboarding` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=pretest`
  },
  {
    key: 'material',
    label: 'Modul & Bahan Ajar',
    isDone: isActivityDone(meet.value, 'material'),
    active: true,
    url: '#'
  },
  {
    key: 'discussion',
    label: 'Ruang Diskusi',
    isDone: isActivityDone(meet.value, 'discussion'),
    active: false,
    url: `/student/discussions/${meet.value?.id}`
  },
  {
    key: 'quiz',
    label: 'Kuis Formatif',
    isDone: isActivityDone(meet.value, 'quiz'),
    active: false,
    url: getMeetQuizzes.value.length > 0 ? `/student/quizzes/${getMeetQuizzes.value[0].id}/onboarding` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=quiz`
  },
  {
    key: 'assignment',
    label: 'Tugas Mandiri',
    isDone: isActivityDone(meet.value, 'assignment'),
    active: false,
    url: getMeetAssignments.value.length > 0 ? `/student/assignments/${getMeetAssignments.value[0].id}` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=assignment`
  },
  {
    key: 'posttest',
    label: 'Posttest',
    isDone: isActivityDone(meet.value, 'posttest'),
    active: false,
    url: getMeetPosttests.value.length > 0 ? `/student/quizzes/${getMeetPosttests.value[0].id}/onboarding` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=posttest`
  },
])

onMounted(fetchData)
</script>

<template>
  <div class="flex min-h-[calc(100vh-64px)] w-full bg-slate-50/50">
    
    <!-- 1. LEFT SIDEBAR COMPONENT -->
    <StudentActivitySidebar
      :meet="meet"
      :subject-name="subjectName"
      :teacher-name="teacherName"
      :class-name="className"
      :course-id="courseId"
      current-category="material"
      :loading="loading"
      :meta-text="`Tipe: ${material?.type || 'Dokumen'}`"
    />

    <!-- 2. RIGHT MAIN CONTENT AREA: IDENTICAL TO DASHBOARD MAIN AREA -->
    <div class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 overflow-x-hidden max-w-7xl w-full">
      
      <!-- A. HERO BANNER CARD IDENTICAL TO SCREENSHOT -->
      <div class="bg-[#dce9f8] rounded-2xl p-6 sm:p-7 border border-blue-200/60 flex items-center justify-between shadow-xs">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="text-xs font-bold uppercase tracking-wider text-blue-800">
              PERTEMUAN {{ meet?.meeting_number || '1' }}
            </span>
            <span class="text-blue-400">•</span>
            <span class="text-xs font-bold text-[#1565c0] uppercase">
              MODUL & BAHAN AJAR GURU
            </span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight uppercase">
            {{ subjectName }}
          </h1>
          <p class="text-xs sm:text-sm font-semibold text-slate-600 mt-1">
            {{ material?.title || 'Materi Pembelajaran' }} • {{ className }} • {{ teacherName }}
          </p>
        </div>

        <!-- Right Connected Nodes Emblem Icon identical to screenshot -->
        <div class="w-16 h-16 shrink-0 flex items-center justify-center text-[#1565c0]">
          <svg class="w-12 h-12" viewBox="0 0 48 48" fill="none">
            <circle cx="16" cy="16" r="5" stroke="currentColor" stroke-width="3" fill="none" />
            <circle cx="36" cy="20" r="4.5" stroke="currentColor" stroke-width="3" fill="none" />
            <circle cx="28" cy="36" r="5" stroke="currentColor" stroke-width="3" fill="none" />
            <path d="M21 17l10.5 2m-16 1.5l8.5 12.5m10-12.5l-3.5 10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
          </svg>
        </div>
      </div>

      <!-- B. MATERIAL VIEWER MAIN BODY -->
      <div v-if="loading" class="space-y-4">
        <AppSkeleton height="h-20" class="rounded-2xl" />
        <AppSkeleton height="h-96" class="rounded-2xl" />
      </div>

      <div
        v-else-if="material"
        class="bg-white rounded-2xl border p-6 sm:p-8 shadow-xs space-y-6 transition"
        :class="isMaterialDownloaded(material.id)
          ? 'border-2 border-emerald-500 shadow-md ring-2 ring-emerald-100/80 bg-emerald-50/10'
          : 'border-slate-200/80'"
      >
        <!-- Header Info -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-lg sm:text-xl font-extrabold text-slate-900">
                {{ material.title }}
              </h2>
              <span
                v-if="isMaterialDownloaded(material.id)"
                class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1 shadow-2xs"
              >
                <span>✓</span>
                <span>Modul Sudah Diunduh</span>
              </span>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">
              Diupload pada {{ formatDate(material.created_at) }}
            </p>
          </div>

          <a
            v-if="material.file_url"
            :href="material.file_url"
            target="_blank"
            download
            @click="markMaterialDownloaded(material.id)"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-2xs inline-flex items-center gap-1.5 shrink-0 self-start sm:self-auto cursor-pointer"
          >
            <span>Unduh Berkas</span>
            <span>↓</span>
          </a>
        </div>

        <!-- 1. Text Content -->
        <div v-if="isText || material.content" class="space-y-3">
          <div class="text-xs font-bold uppercase tracking-wider text-slate-400">
            Rangkuman & Isi Materi
          </div>
          <div class="prose prose-slate max-w-none text-xs sm:text-sm text-slate-800 leading-relaxed whitespace-pre-line bg-slate-50/70 p-5 sm:p-6 rounded-2xl border border-slate-200/70 font-normal">
            {{ material.content }}
          </div>
        </div>

        <!-- 2. PDF Preview Embed -->
        <div v-if="isPdf && material.file_url" class="space-y-3">
          <div class="text-xs font-bold uppercase tracking-wider text-slate-400">
            Pratinjau Dokumen PDF
          </div>
          <div class="w-full h-[650px] rounded-2xl overflow-hidden border border-slate-200 bg-slate-100">
            <iframe
              :src="material.file_url"
              class="w-full h-full border-0"
              title="PDF Viewer"
            ></iframe>
          </div>
        </div>

        <!-- 3. Non-PDF File (DOC, PPT, ZIP, dll) -->
        <div v-else-if="material.type === 'file' && material.file_url && !isPdf" class="p-6 bg-slate-50 rounded-2xl border border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="flex items-center gap-4">
            <span class="text-3xl">📁</span>
            <div>
              <span class="text-sm font-bold text-slate-900 block">
                {{ material.file_path?.split('/').pop() || 'Dokumen Materi Pembelajaran' }}
              </span>
              <span class="text-xs text-slate-500">Berkas telah siap diunduh untuk dipelajari di perangkat Anda.</span>
            </div>
          </div>

          <a
            :href="material.file_url"
            target="_blank"
            download
            class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition shadow-2xs shrink-0 inline-flex items-center gap-2"
          >
            <span>Unduh Berkas Materi</span>
            <span>↓</span>
          </a>
        </div>

        <!-- 4. Video Player -->
        <div v-if="isVideo" class="space-y-3">
          <div class="text-xs font-bold uppercase tracking-wider text-slate-400">
            Video Pembelajaran
          </div>

          <!-- YouTube Embed -->
          <div v-if="embedVideoUrl?.includes('youtube.com/embed')" class="aspect-video w-full rounded-2xl overflow-hidden border border-slate-200 bg-black">
            <iframe
              :src="embedVideoUrl"
              class="w-full h-full border-0"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen
              title="Video Pembelajaran"
            ></iframe>
          </div>

          <!-- Direct Video URL / File -->
          <div v-else-if="material.file_url || material.external_url" class="aspect-video w-full rounded-2xl overflow-hidden border border-slate-200 bg-black">
            <video
              controls
              class="w-full h-full"
              :src="material.file_url || material.external_url"
            >
              Browser Anda tidak mendukung pemutar video.
            </video>
          </div>
        </div>

        <!-- 5. External Link -->
        <div v-if="isLink && material.external_url" class="p-6 bg-blue-50/60 rounded-2xl border border-blue-200/70 flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="flex items-center gap-4">
            <span class="text-3xl">🔗</span>
            <div>
              <span class="text-xs font-bold text-blue-900 block uppercase tracking-wider">Tautan Referensi Eksternal</span>
              <span class="text-xs text-blue-700 block truncate max-w-md mt-0.5">{{ material.external_url }}</span>
            </div>
          </div>

          <a
            :href="material.external_url"
            target="_blank"
            rel="noopener noreferrer"
            class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition shadow-2xs shrink-0 inline-flex items-center gap-2"
          >
            <span>Kunjungi Tautan</span>
            <span>↗</span>
          </a>
        </div>

      </div>

      <div v-else class="bg-white rounded-2xl border border-dashed border-slate-200 p-12 text-center text-slate-400">
        Materi tidak ditemukan atau telah dihapus.
      </div>

    </div>

  </div>
</template>
