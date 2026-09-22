<script setup>
import { computed, ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'

definePageMeta({
  layout: 'default',
  middleware: 'auth'
})

const route = useRoute()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie('auth_token')
const { getUserRole } = useAuth()
const canManageLearning = computed(() => ['admin', 'super_admin', 'teacher', 'guru'].includes(getUserRole()))

const course = ref(null)
const meetsList = ref([])
const loading = ref(true)

const showMeetModal = ref(false)
const isEditingMeet = ref(false)
const currentMeetId = ref(null)

const meetForm = reactive({
  meeting_number: 1,
  title: '',
  description: '',
  start_time: '',
  end_time: '',
  type: 'offline',
  meeting_link: ''
})

const errors = ref({})

const fetchCourseDetail = async () => {
  loading.value = true
  try {
    const res = await $fetch(`${apiBase}/courses/${route.params.id}`, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    course.value = res
  } catch (err) {
    console.error('Fetch course error:', err)
  } finally {
    loading.value = false
  }
}

const fetchMeets = async () => {
  try {
    const res = await $fetch(`${apiBase}/meets?course_id=${route.params.id}&paginate=false`, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    meetsList.value = res.data || res || []
  } catch (err) {
    console.error('Fetch meets error:', err)
  }
}

onMounted(async () => {
  await fetchCourseDetail()
  await fetchMeets()
})

const openAddMeetModal = () => {
  isEditingMeet.value = false
  currentMeetId.value = null
  Object.assign(meetForm, {
    meeting_number: (meetsList.value.length || 0) + 1,
    title: '',
    description: '',
    start_time: '',
    end_time: '',
    type: 'offline',
    meeting_link: ''
  })
  errors.value = {}
  showMeetModal.value = true
}

const handleSaveMeet = async () => {
  errors.value = {}
  const endpoint = isEditingMeet.value
    ? `${apiBase}/meets/${currentMeetId.value}`
    : `${apiBase}/meets`

  const method = isEditingMeet.value ? 'PUT' : 'POST'

  try {
    await $fetch(endpoint, {
      method,
      headers: { Authorization: `Bearer ${token.value}` },
      body: {
        ...meetForm,
        course_id: route.params.id
      }
    })

    showMeetModal.value = false
    await fetchMeets()
  } catch (err) {
    if (err.data && err.data.errors) {
      errors.value = err.data.errors
    } else {
      alert(err.data?.message || 'Gagal menyimpan sesi pertemuan.')
    }
  }
}

const handleDeleteMeet = async (id) => {
  if (!confirm('Apakah Anda yakin ingin menghapus pertemuan ini?')) return

  try {
    await $fetch(`${apiBase}/meets/${id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${token.value}` }
    })
    await fetchMeets()
  } catch (err) {
    alert(err.data?.message || 'Gagal menghapus pertemuan')
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-sans text-slate-800 pb-12">
    <!-- Header Navigation -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <NuxtLink to="/elearning" class="flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-emerald-600 transition">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Kembali ke Portal E-Learning
        </NuxtLink>
        <span class="text-xs font-mono text-emerald-700 font-bold bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100">
          MAN 11 E-LEARNING
        </span>
      </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
      <div v-if="loading" class="bg-white rounded-2xl p-12 text-center text-gray-400">
        Memuat data mata pelajaran...
      </div>

      <div v-else-if="!course" class="bg-white rounded-2xl p-12 text-center text-rose-500">
        Mata pelajaran tidak ditemukan.
      </div>

      <template v-else>
        <!-- Hero Course Banner -->
        <div class="bg-gradient-to-r from-emerald-800 via-emerald-700 to-teal-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl mb-8 relative overflow-hidden">
          <div class="relative z-10 max-w-3xl">
            <div class="inline-block px-3 py-1 bg-white/20 backdrop-blur-md rounded-full text-xs font-semibold uppercase tracking-wider mb-3">
              {{ course.subject?.code }} • {{ course.credit_hours }} JP
            </div>
            <h1 class="text-2xl sm:text-4xl font-extrabold tracking-tight mb-2">
              {{ course.subject?.name }}
            </h1>
            <p class="text-emerald-100 text-sm mb-4">
              {{ course.subject?.description || 'Modul pengajaran resmi MAN 11 Jakarta' }}
            </p>
            <div class="flex flex-wrap gap-4 text-xs text-emerald-100/90 pt-2 border-t border-white/10">
              <div>Kelas: <span class="font-bold text-white">{{ course.class?.class_name?.name }}</span></div>
              <div>Guru: <span class="font-bold text-white">{{ course.teacher?.name }}</span></div>
              <div>Semester: <span class="font-bold text-white">T.A {{ course.class?.semester?.academic_year }}</span></div>
            </div>
          </div>
        </div>

        <!-- Section Title & Action Button -->
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-xl font-bold text-slate-900">Linimasa Pertemuan (Meets)</h2>
            <p class="text-xs text-slate-500">Daftar sesi pembelajaran dan materi ajar per pertemuan</p>
          </div>

          <button v-if="canManageLearning"
            @click="openAddMeetModal"
            class="px-4 py-2.5 bg-emerald-600 text-white rounded-xl text-xs font-semibold hover:bg-emerald-700 transition shadow-sm flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Tambah Pertemuan
          </button>
        </div>

        <!-- Timeline Meets Grid -->
        <div v-if="meetsList.length === 0" class="bg-white rounded-2xl p-12 text-center text-slate-400 border border-slate-100">
          Belum ada sesi pertemuan yang dibuat untuk mata pelajaran ini.
        </div>

        <div v-else class="space-y-4">
          <div 
            v-for="meet in meetsList" 
            :key="meet.id"
            class="bg-white rounded-2xl p-6 shadow-xs border border-slate-100 hover:border-emerald-200 transition flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
          >
            <div class="space-y-1 max-w-2xl">
              <div class="flex items-center gap-3">
                <span class="px-3 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-lg">
                  Pertemuan {{ meet.meeting_number }}
                </span>
                <span class="px-2.5 py-0.5 text-[10px] uppercase font-bold rounded-full" :class="meet.type === 'online' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-700'">
                  {{ meet.type }}
                </span>
              </div>
              <h3 class="text-lg font-bold text-slate-900 pt-1">
                {{ meet.title }}
              </h3>
              <p class="text-xs text-slate-500 line-clamp-2">
                {{ meet.description || 'Tidak ada deskripsi tambahan.' }}
              </p>
              <div class="flex gap-4 text-[11px] text-slate-400 pt-2">
                <span>📁 {{ meet.materials_count || 0 }} Materi</span>
                <span>📝 {{ meet.quizzes_count || 0 }} Tugas/Kuis</span>
                <span>💬 {{ meet.discussions_count || 0 }} Diskusi</span>
              </div>
            </div>

            <div class="flex items-center gap-3 w-full md:w-auto justify-between md:justify-end border-t md:border-0 pt-3 md:pt-0 border-slate-100">
              <NuxtLink 
                :to="`/elearning/meets/${meet.id}`"
                class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-semibold hover:bg-emerald-700 transition"
              >
                Buka Sesi Belajar →
              </NuxtLink>
              <button v-if="canManageLearning"
                @click="handleDeleteMeet(meet.id)"
                class="px-3 py-2 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-xl text-xs font-semibold transition"
              >
                Hapus
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Modal Form Pertemuan -->
    <div v-if="showMeetModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-100 space-y-4">
        <div class="flex justify-between items-center border-b border-gray-100 pb-3">
          <h3 class="text-lg font-bold text-gray-900">
            {{ isEditingMeet ? 'Edit Pertemuan' : 'Buat Sesi Pertemuan Baru' }}
          </h3>
          <button @click="showMeetModal = false" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="handleSaveMeet" class="space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Pertemuan Ke- *</label>
              <input 
                v-model.number="meetForm.meeting_number"
                type="number" 
                min="1"
                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                required
              >
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Tipe Sesi *</label>
              <select 
                v-model="meetForm.type"
                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                required
              >
                <option value="offline">Offline (Tatap Muka)</option>
                <option value="online">Online (Virtual Meet)</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Judul Pertemuan *</label>
            <input 
              v-model="meetForm.title"
              type="text" 
              placeholder="Contoh: Pertemuan 1 - Konsep Dasar Trigonometri"
              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
              required
            >
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Deskripsi Ringkas</label>
            <textarea 
              v-model="meetForm.description"
              rows="3"
              placeholder="Penjelasan singkat mengenai pembahasan di pertemuan ini..."
              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
            ></textarea>
          </div>

          <div v-if="meetForm.type === 'online'">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Link Google Meet / Zoom</label>
            <input 
              v-model="meetForm.meeting_link"
              type="url" 
              placeholder="https://meet.google.com/..."
              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
            >
          </div>

          <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
            <button 
              type="button" 
              @click="showMeetModal = false"
              class="px-4 py-2 border border-gray-200 text-gray-600 rounded-xl text-xs font-semibold hover:bg-gray-50 transition"
            >
              Batal
            </button>
            <button 
              type="submit" 
              class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-semibold hover:bg-emerald-700 transition"
            >
              Simpan Pertemuan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
