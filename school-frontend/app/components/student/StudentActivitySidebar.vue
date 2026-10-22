<script setup lang="ts">
import { ref, computed } from 'vue'
import AppSkeleton from '~/components/ui/AppSkeleton.vue'

const props = withDefaults(defineProps<{
  meet?: any
  subjectName?: string
  teacherName?: string
  className?: string
  courseId?: string | number
  currentCategory?: string
  loading?: boolean
  metaText?: string
}>(), {
  meet: null,
  subjectName: 'Mata Pelajaran',
  teacherName: 'Guru Pengampu',
  className: 'Kelas',
  courseId: '',
  currentCategory: 'quiz',
  loading: false,
  metaText: ''
})

const isSidebarOpen = useState('isStudentSidebarOpen', () => true)
const sidebarSearch = ref('')

const { isActivityDone } = useStudentActivityProgress()

const getMeetPretests = computed(() => (props.meet?.quizzes || []).filter((q: any) => q.activity_category === 'pretest'))
const getMeetMaterials = computed(() => props.meet?.materials || [])
const getMeetDiscussions = computed(() => props.meet?.discussions || [])
const getMeetQuizzes = computed(() => (props.meet?.quizzes || []).filter((q: any) => q.activity_category === 'quiz' || (!q.activity_category && (q.quiz_type === 'multiple_choice' || q.quiz_type === 'quiz'))))
const getMeetAssignments = computed(() => (props.meet?.quizzes || []).filter((q: any) => q.activity_category === 'assignment' || (!q.activity_category && (q.quiz_type === 'assignment' || q.quiz_type === 'essay'))))
const getMeetPosttests = computed(() => (props.meet?.quizzes || []).filter((q: any) => q.activity_category === 'posttest'))

const activityMenuItems = computed(() => [
  {
    key: 'pretest',
    label: 'Pretest',
    isDone: isActivityDone(props.meet, 'pretest'),
    active: props.currentCategory === 'pretest',
    url: props.currentCategory === 'pretest' ? '#' : (getMeetPretests.value.length > 0 ? `/student/quizzes/${getMeetPretests.value[0].id}/onboarding` : `/elearning/courses/${props.courseId}?meet=${props.meet?.meeting_number}&activity=pretest`)
  },
  {
    key: 'material',
    label: 'Modul & Bahan Ajar',
    isDone: isActivityDone(props.meet, 'material'),
    active: props.currentCategory === 'material',
    url: props.currentCategory === 'material' ? '#' : (getMeetMaterials.value.length > 0 ? `/student/materials/${getMeetMaterials.value[0].id}` : `/elearning/courses/${props.courseId}?meet=${props.meet?.meeting_number}&activity=material`)
  },
  {
    key: 'discussion',
    label: 'Ruang Diskusi',
    isDone: isActivityDone(props.meet, 'discussion'),
    active: props.currentCategory === 'discussion',
    url: props.currentCategory === 'discussion' ? '#' : `/student/discussions/${props.meet?.id}`
  },
  {
    key: 'quiz',
    label: 'Kuis Formatif',
    isDone: isActivityDone(props.meet, 'quiz'),
    active: props.currentCategory === 'quiz',
    url: props.currentCategory === 'quiz' ? '#' : (getMeetQuizzes.value.length > 0 ? `/student/quizzes/${getMeetQuizzes.value[0].id}/onboarding` : `/elearning/courses/${props.courseId}?meet=${props.meet?.meeting_number}&activity=quiz`)
  },
  {
    key: 'assignment',
    label: 'Tugas Mandiri',
    isDone: isActivityDone(props.meet, 'assignment'),
    active: props.currentCategory === 'assignment',
    url: props.currentCategory === 'assignment' ? '#' : (getMeetAssignments.value.length > 0 ? `/student/assignments/${getMeetAssignments.value[0].id}` : `/elearning/courses/${props.courseId}?meet=${props.meet?.meeting_number}&activity=assignment`)
  },
  {
    key: 'posttest',
    label: 'Posttest',
    isDone: isActivityDone(props.meet, 'posttest'),
    active: props.currentCategory === 'posttest',
    url: props.currentCategory === 'posttest' ? '#' : (getMeetPosttests.value.length > 0 ? `/student/quizzes/${getMeetPosttests.value[0].id}/onboarding` : `/elearning/courses/${props.courseId}?meet=${props.meet?.meeting_number}&activity=posttest`)
  }
])
</script>

<template>
  <aside
    class="bg-white border-r border-slate-200 transition-all duration-300 ease-in-out shrink-0 overflow-y-auto"
    :class="isSidebarOpen ? 'w-full lg:w-72 p-4' : 'w-0 p-0 overflow-hidden border-r-0'"
  >
    <div v-show="isSidebarOpen" class="space-y-4">
      <!-- Search Input -->
      <div class="relative">
        <input
          v-model="sidebarSearch"
          type="text"
          placeholder="Pencarian aktivitas..."
          class="w-full pl-8 pr-3 py-2 text-xs border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:border-brand-600 transition font-medium text-slate-800 placeholder-slate-400"
        />
        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>

      <!-- Sesi Pertemuan Active Info Card -->
      <div v-if="loading" class="space-y-2 py-2">
        <AppSkeleton height="h-20" class="rounded-xl" />
      </div>
      <div v-else-if="meet" class="p-3 bg-brand-50/60 rounded-xl border border-brand-200/60 space-y-1.5">
        <div class="text-[10px] font-black uppercase tracking-wider text-brand-800">
          {{ subjectName }}
        </div>
        <div class="text-xs font-extrabold text-slate-900 leading-tight">
          Pertemuan {{ meet.meeting_number }}: {{ meet.title }}
        </div>
        <div class="text-[11px] text-slate-600 font-medium">
          {{ className }} • {{ teacherName }}
        </div>
        <div class="text-[10px] text-slate-400 pt-1 border-t border-brand-100 flex items-center justify-between">
          <span>{{ metaText || 'Modul Pembelajaran' }}</span>
          <span class="text-brand-700 font-bold">● Terhubung</span>
        </div>
      </div>

      <!-- 6 Aktivitas Belajar Standard LMS Dropdown / Menu -->
      <div v-if="meet" class="space-y-1.5 pt-1">
        <div class="flex items-center justify-between px-1 mb-1">
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
            Aktivitas Belajar
          </span>
          <span class="text-[10px] text-slate-500 font-semibold">P{{ meet.meeting_number }}</span>
        </div>

        <div class="space-y-1">
          <template v-for="act in activityMenuItems" :key="act.key">
            <div
              v-if="act.active"
              class="p-2 rounded-xl border flex items-center justify-between text-xs font-bold transition"
              :class="act.isDone ? 'border-2 border-brand-500 bg-brand-50/70 text-brand-950 shadow-xs' : 'bg-brand-50 border-brand-200 text-brand-900'"
            >
              <span class="flex items-center gap-2 truncate">
                <span
                  class="w-5 h-5 rounded-md flex items-center justify-center shrink-0 border transition"
                  :class="act.isDone ? 'bg-brand-100 text-brand-800 border-brand-200' : 'bg-brand-700 text-white border-transparent'"
                >
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </span>
                <span class="truncate">{{ act.label }}</span>
              </span>
              <span class="text-[10px] px-1.5 py-0.5 rounded-full font-extrabold uppercase shrink-0" :class="act.isDone ? 'bg-brand-100 text-brand-800' : 'bg-brand-200/60 text-brand-950'">
                {{ act.isDone ? 'Selesai' : 'Aktif' }}
              </span>
            </div>

            <NuxtLink
              v-else
              :to="act.url"
              class="p-2 rounded-xl border border-transparent hover:border-slate-200 hover:bg-slate-50 flex items-center justify-between text-xs font-semibold text-slate-600 hover:text-slate-900 transition group"
            >
              <span class="flex items-center gap-2 truncate">
                <span
                  class="w-5 h-5 rounded-md flex items-center justify-center shrink-0 border transition"
                  :class="act.isDone ? 'bg-brand-50 text-brand-700 border-brand-200' : 'bg-slate-100 text-slate-400 border-slate-200'"
                >
                  <svg v-if="act.isDone" class="w-3 h-3 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span v-else class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                </span>
                <span class="truncate">{{ act.label }}</span>
              </span>
            </NuxtLink>
          </template>
        </div>
      </div>
    </div>
  </aside>
</template>
