<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppSkeleton from '~/components/ui/AppSkeleton.vue'

definePageMeta({ layout: 'student', middleware: 'auth' })

const route = useRoute()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie<string | null>('auth_token')
const { user } = useAuth()

// Sidebar open/collapse synced with student layout header hamburger
const isSidebarOpen = useState('isStudentSidebarOpen', () => true)

const meetId = computed(() => route.params.meetId as string)
const loading = ref(true)
const meet = ref<any>(null)
const discussions = ref<any[]>([])
const newMessage = ref('')
const replyingTo = ref<number | null>(null)
const replyMessage = ref('')
const sending = ref(false)
const sidebarSearch = ref('')

const api = <T>(path: string, opts?: any) =>
  $fetch<T>(`${apiBase}${path}`, {
    headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
    ...opts
  })

const subjectName = computed(() => meet.value?.course?.subject?.name || 'Mata Pelajaran')
const className = computed(() =>
  meet.value?.course?.class?.class_name?.name ||
  meet.value?.course?.class?.className?.name || 'Kelas'
)
const teacherName = computed(() => meet.value?.course?.teacher?.name || 'Guru Pengampu')
const courseId = computed(() => meet.value?.course_id || meet.value?.course?.id)

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

const fetchData = async () => {
  loading.value = true
  try {
    const meetRes = await api<any>(`/meets/${meetId.value}`)
    meet.value = meetRes

    const discRes = await api<any>(`/discussions?meet_id=${meetId.value}`)
    discussions.value = Array.isArray(discRes) ? discRes : (discRes?.data || [])
  } catch (err) {
    console.error('Gagal memuat data forum diskusi:', err)
  } finally {
    loading.value = false
  }
}

const sendMessage = async () => {
  const text = newMessage.value.trim()
  if (!text) return
  sending.value = true
  try {
    await api('/discussions', {
      method: 'POST',
      body: { meet_id: meetId.value, message: text }
    })
    newMessage.value = ''
    await fetchData()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal mengirim pesan.')
  } finally {
    sending.value = false
  }
}

const sendReply = async (parentId: number) => {
  const text = replyMessage.value.trim()
  if (!text) return
  sending.value = true
  try {
    await api('/discussions', {
      method: 'POST',
      body: { meet_id: meetId.value, parent_id: parentId, message: text }
    })
    replyMessage.value = ''
    replyingTo.value = null
    await fetchData()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal mengirim balasan.')
  } finally {
    sending.value = false
  }
}

const toggleReply = (discId: number) => {
  if (replyingTo.value === discId) {
    replyingTo.value = null
    replyMessage.value = ''
  } else {
    replyingTo.value = discId
    replyMessage.value = ''
  }
}

// Filtered discussions based on search
const filteredDiscussions = computed(() => {
  if (!sidebarSearch.value.trim()) return discussions.value
  const q = sidebarSearch.value.toLowerCase().trim()
  return discussions.value.filter(d => {
    const msg = (d.message || '').toLowerCase()
    const sender = (d.student?.name || d.teacher?.name || '').toLowerCase()
    return msg.includes(q) || sender.includes(q)
  })
})

// -------------------------------------------------------------------
// 6 Kategori Aktivitas Belajar Siswa (Dropdown Sidebar LMS)
// -------------------------------------------------------------------
const getMeetPretests = computed(() => (meet.value?.quizzes || []).filter((q: any) => q.activity_category === 'pretest'))
const getMeetMaterials = computed(() => meet.value?.materials || [])
const getMeetDiscussions = computed(() => discussions.value || [])
const getMeetQuizzes = computed(() => (meet.value?.quizzes || []).filter((q: any) => q.activity_category === 'quiz' || (!q.activity_category && (q.quiz_type === 'multiple_choice' || q.quiz_type === 'quiz'))))
const getMeetAssignments = computed(() => (meet.value?.quizzes || []).filter((q: any) => q.activity_category === 'assignment' || (!q.activity_category && (q.quiz_type === 'assignment' || q.quiz_type === 'essay'))))
const getMeetPosttests = computed(() => (meet.value?.quizzes || []).filter((q: any) => q.activity_category === 'posttest'))

const activityMenuItems = computed(() => [
  {
    key: 'pretest',
    number: 1,
    label: 'Pretest',
    count: getMeetPretests.value.length,
    active: false,
    url: getMeetPretests.value.length > 0 ? `/student/quizzes/${getMeetPretests.value[0].id}/onboarding` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=pretest`
  },
  {
    key: 'material',
    number: 2,
    label: 'Modul & Bahan Ajar',
    count: getMeetMaterials.value.length,
    active: false,
    url: getMeetMaterials.value.length > 0 ? `/student/materials/${getMeetMaterials.value[0].id}` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=material`
  },
  {
    key: 'discussion',
    number: 3,
    label: 'Ruang Diskusi',
    count: getMeetDiscussions.value.length,
    active: true,
    url: '#'
  },
  {
    key: 'quiz',
    number: 4,
    label: 'Kuis Formatif',
    count: getMeetQuizzes.value.length,
    active: false,
    url: getMeetQuizzes.value.length > 0 ? `/student/quizzes/${getMeetQuizzes.value[0].id}/onboarding` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=quiz`
  },
  {
    key: 'assignment',
    number: 5,
    label: 'Tugas Mandiri',
    count: getMeetAssignments.value.length,
    active: false,
    url: getMeetAssignments.value.length > 0 ? `/student/assignments/${getMeetAssignments.value[0].id}` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=assignment`
  },
  {
    key: 'posttest',
    number: 6,
    label: 'Posttest',
    count: getMeetPosttests.value.length,
    active: false,
    url: getMeetPosttests.value.length > 0 ? `/student/quizzes/${getMeetPosttests.value[0].id}/onboarding` : `/elearning/courses/${courseId.value}?meet=${meet.value?.meeting_number}&activity=posttest`
  },
])

onMounted(fetchData)
</script>

<template>
  <div class="flex min-h-[calc(100vh-64px)] w-full bg-slate-50/50">
    
    <!-- 1. LEFT SIDEBAR: IDENTICAL TO DASHBOARD & COURSE PAGE -->
    <aside
      v-show="isSidebarOpen"
      class="w-64 sm:w-72 border-r border-slate-200 bg-white shrink-0 p-4 space-y-4 flex flex-col justify-between transition-all"
    >
      <div class="space-y-3">
        <!-- Sidebar Header Title matching Dashboard Courses List -->
        <div class="flex items-center justify-between">
          <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">
            Courses List
          </h2>
          <span
            v-if="meet"
            class="text-[10px] font-black px-2 py-0.5 rounded-full bg-blue-50 text-[#1565c0] border border-blue-200 shadow-2xs"
          >
            P{{ meet.meeting_number }}
          </span>
        </div>

        <!-- Search Input Sidebar identical to dashboard -->
        <div class="relative">
          <input
            v-model="sidebarSearch"
            type="text"
            placeholder="Pencarian diskusi..."
            class="w-full pl-8 pr-3 py-2 text-xs border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:border-blue-600 transition font-medium text-slate-800 placeholder-slate-400"
          />
          <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <!-- Sesi Pertemuan Active Info Card -->
        <div v-if="loading" class="space-y-2 py-2">
          <AppSkeleton height="h-20" class="rounded-xl" />
        </div>
        <div v-else-if="meet" class="p-3 bg-blue-50/60 rounded-xl border border-blue-200/60 space-y-1.5">
          <div class="text-[10px] font-black uppercase tracking-wider text-blue-700">
            {{ subjectName }}
          </div>
          <div class="text-xs font-extrabold text-slate-900 leading-tight">
            Pertemuan {{ meet.meeting_number }}: {{ meet.title }}
          </div>
          <div class="text-[11px] text-slate-600 font-medium">
            {{ className }} • {{ teacherName }}
          </div>
          <div class="text-[10px] text-slate-400 pt-1 border-t border-blue-100 flex items-center justify-between">
            <span>{{ discussions.length }} Topik Diskusi</span>
            <span class="text-emerald-700 font-bold">● Terhubung</span>
          </div>
        </div>

        <!-- 6 Aktivitas Belajar Standard LMS Dropdown / Menu -->
        <div v-if="meet" class="space-y-1.5 pt-1">
          <div class="flex items-center justify-between px-1 mb-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
              Aktivitas Belajar
            </span>
            <span class="text-[10px] text-slate-500 font-semibold">P{{ meet.meeting_number }}</span>
          </div>

          <div class="space-y-1">
            <template v-for="act in activityMenuItems" :key="act.key">
              <div
                v-if="act.active"
                class="p-2 rounded-xl bg-blue-50 border border-blue-200 flex items-center justify-between text-xs font-bold text-[#1565c0]"
              >
                <span class="flex items-center gap-2 truncate">
                  <span class="w-4 h-4 rounded text-[10px] font-bold flex items-center justify-center shrink-0 bg-[#1565c0] text-white">
                    {{ act.number }}
                  </span>
                  <span class="truncate">{{ act.label }}</span>
                </span>
                <span class="text-[10px] bg-blue-100 text-[#1565c0] px-1.5 py-0.5 rounded-md shrink-0 font-semibold">Aktif</span>
              </div>

              <NuxtLink
                v-else
                :to="act.url"
                class="p-2 rounded-xl hover:bg-slate-50 border border-transparent hover:border-slate-200 flex items-center justify-between text-xs font-medium text-slate-700 transition group"
              >
                <span class="flex items-center gap-2 truncate">
                  <span class="w-4 h-4 rounded text-[10px] font-bold flex items-center justify-center shrink-0 bg-slate-200 text-slate-700 group-hover:bg-blue-100 group-hover:text-blue-800">
                    {{ act.number }}
                  </span>
                  <span class="truncate">{{ act.label }}</span>
                </span>
                <span v-if="act.count > 0" class="text-[10px] text-slate-400 bg-slate-100 px-1.5 py-0.2 rounded-md shrink-0 font-semibold">
                  {{ act.count }}
                </span>
                <span v-else class="text-[10px] text-slate-300 group-hover:text-[#1565c0]">➔</span>
              </NuxtLink>
            </template>
          </div>
        </div>
      </div>

      <!-- Bottom of Sidebar: Navigasi Kembali ke Kursus & Dashboard -->
      <div class="pt-3 border-t border-slate-100 space-y-2">
        <NuxtLink
          :to="courseId ? `/elearning/courses/${courseId}` : '/student/dashboard'"
          class="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border border-blue-200 hover:border-blue-300 bg-blue-50 hover:bg-blue-100 text-[#1565c0] text-xs font-bold transition shadow-2xs group"
        >
          <span class="group-hover:-translate-x-0.5 transition-transform">←</span>
          <span>Kembali ke Kursus</span>
        </NuxtLink>

        <NuxtLink
          to="/student/dashboard"
          class="w-full flex items-center justify-center gap-2 px-3 py-1.5 text-slate-500 hover:text-slate-800 text-xs font-medium transition"
        >
          <span>Dashboard Utama</span>
        </NuxtLink>
      </div>
    </aside>

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
            <span class="text-xs font-bold text-[#1565c0]">
              RUANG DISKUSI INTERAKTIF
            </span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight uppercase">
            {{ subjectName }}
          </h1>
          <p class="text-xs sm:text-sm font-semibold text-slate-600 mt-1">
            {{ meet?.title || 'Ruang Tanya Jawab & Diskusi' }} • {{ className }} • {{ teacherName }}
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

      <!-- B. MAIN DISCUSSION BODY -->
      <div class="space-y-6">
        
        <!-- Input Topik Baru -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-xs space-y-3">
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
            Mulai Diskusi atau Tanya Jawab Baru
          </label>
          <div class="flex flex-col sm:flex-row gap-3">
            <textarea
              v-model="newMessage"
              rows="2"
              placeholder="Tuliskan pertanyaan materi atau topik diskusi untuk guru dan teman sekelas..."
              class="flex-1 p-3.5 text-xs rounded-xl border border-slate-200 focus:outline-hidden focus:ring-2 focus:ring-blue-500 bg-slate-50 focus:bg-white resize-none font-medium"
            ></textarea>
            <button
              type="button"
              :disabled="sending || !newMessage.trim()"
              @click="sendMessage"
              class="px-6 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white text-xs font-bold transition shadow-xs flex items-center justify-center gap-2 shrink-0 cursor-pointer"
            >
              <span v-if="sending" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <span>Kirim Pertanyaan</span>
            </button>
          </div>
        </div>

        <!-- Thread List -->
        <div v-if="loading" class="space-y-4">
          <AppSkeleton v-for="i in 3" :key="i" height="h-28" class="rounded-2xl" />
        </div>

        <div v-else-if="filteredDiscussions.length === 0" class="bg-white rounded-2xl border border-dashed border-slate-200 p-12 text-center space-y-2">
          <span class="text-4xl block">💬</span>
          <h3 class="text-sm font-bold text-slate-700">Belum Ada Topik Diskusi</h3>
          <p class="text-xs text-slate-400 max-w-sm mx-auto">
            Jadilah yang pertama mengajukan pertanyaan atau tanggapan terkait materi pertemuan ini.
          </p>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="disc in filteredDiscussions"
            :key="disc.id"
            class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden"
          >
            <!-- Parent Discussion Card -->
            <div class="p-5 sm:p-6 space-y-3" :class="disc.teacher_id ? 'bg-amber-50/20' : ''">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div
                    class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-xs uppercase"
                    :class="disc.teacher_id ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'"
                  >
                    {{ (disc.teacher?.name || disc.student?.name || 'S')[0] }}
                  </div>
                  <div>
                    <div class="flex items-center gap-2">
                      <span class="text-xs font-extrabold text-slate-900">
                        {{ disc.teacher?.name || disc.student?.name || 'Siswa' }}
                      </span>
                      <span
                        v-if="disc.teacher_id"
                        class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200"
                      >
                        Guru Pengampu
                      </span>
                    </div>
                    <span class="text-[11px] text-slate-400">
                      {{ formatDate(disc.created_at) }}
                    </span>
                  </div>
                </div>

                <button
                  type="button"
                  @click="toggleReply(disc.id)"
                  class="px-3 py-1.5 rounded-lg border border-slate-200 hover:border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition shadow-2xs flex items-center gap-1.5"
                >
                  <span>💬 Balas</span>
                  <span v-if="disc.replies?.length" class="text-[10px] text-slate-400">({{ disc.replies.length }})</span>
                </button>
              </div>

              <p class="text-xs sm:text-sm text-slate-700 leading-relaxed whitespace-pre-line pl-12 font-normal">
                {{ disc.message }}
              </p>
            </div>

            <!-- Nested Replies Stream -->
            <div v-if="disc.replies && disc.replies.length > 0" class="bg-slate-50/70 border-t border-slate-100 p-4 sm:p-5 pl-10 sm:pl-16 space-y-3">
              <div
                v-for="reply in disc.replies"
                :key="reply.id"
                class="bg-white p-3.5 rounded-xl border border-slate-200/70 space-y-1.5 shadow-2xs"
                :class="reply.teacher_id ? 'border-amber-200 bg-amber-50/30' : ''"
              >
                <div class="flex items-center justify-between text-xs">
                  <div class="flex items-center gap-2">
                    <span class="font-bold text-slate-900">
                      {{ reply.teacher?.name || reply.student?.name || 'Siswa' }}
                    </span>
                    <span
                      v-if="reply.teacher_id"
                      class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800"
                    >
                      Guru
                    </span>
                  </div>
                  <span class="text-[10px] text-slate-400">
                    {{ formatDate(reply.created_at) }}
                  </span>
                </div>
                <p class="text-xs text-slate-600 leading-relaxed whitespace-pre-line font-normal">
                  {{ reply.message }}
                </p>
              </div>
            </div>

            <!-- Reply Input Box (when active) -->
            <div v-if="replyingTo === disc.id" class="p-4 bg-blue-50/30 border-t border-blue-100 flex items-center gap-2">
              <input
                v-model="replyMessage"
                type="text"
                placeholder="Tulis balasan untuk diskusi ini..."
                @keyup.enter="sendReply(disc.id)"
                class="flex-1 px-3.5 py-2 text-xs rounded-xl border border-blue-200 bg-white focus:outline-hidden focus:ring-2 focus:ring-blue-500 font-medium"
              />
              <button
                type="button"
                :disabled="sending || !replyMessage.trim()"
                @click="sendReply(disc.id)"
                class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white text-xs font-bold transition shadow-xs shrink-0 cursor-pointer"
              >
                Kirim
              </button>
            </div>
          </div>
        </div>

      </div>

    </div>

  </div>
</template>
