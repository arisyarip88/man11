<script setup>
import { computed, ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'

definePageMeta({
  layout: 'default'
})

const route = useRoute()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const storageBase = config.public.storageBase || 'http://localhost:8000/storage'
const token = useCookie('auth_token')
const { getUserRole } = useAuth()
const canManageLearning = computed(() => ['admin', 'super_admin', 'teacher', 'guru'].includes(getUserRole()))

const activeTab = ref('materials') // 'materials', 'quizzes', 'discussions'
const meet = ref(null)
const quizzes = ref([])
const discussions = ref([])
const loading = ref(true)

// Modals
const showMaterialModal = ref(false)
const showQuizModal = ref(false)
const showSubmitModal = ref(false)
const showGradeModal = ref(false)

const selectedQuiz = ref(null)
const selectedSubmission = ref(null)

const materialForm = reactive({
  title: '',
  type: 'file',
  content: '',
  external_url: '',
  file: null
})

const quizForm = reactive({
  title: '',
  instructions: '',
  quiz_type: 'assignment',
  due_date: '',
  max_score: 100
})

const submitForm = reactive({
  submission_text: '',
  file: null
})

const gradeForm = reactive({
  score: 100,
  teacher_feedback: ''
})

const discussionMessage = ref('')
const replyingToId = ref(null)

const fetchMeetDetail = async () => {
  loading.value = true
  try {
    const res = await $fetch(`${apiBase}/meets/${route.params.id}`, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    meet.value = res
  } catch (err) {
    console.error('Fetch meet detail error:', err)
  } finally {
    loading.value = false
  }
}

const fetchQuizzes = async () => {
  try {
    const res = await $fetch(`${apiBase}/quizzes?meet_id=${route.params.id}`, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    quizzes.value = res.data || res || []
  } catch (err) {
    console.error('Fetch quizzes error:', err)
  }
}

const fetchDiscussions = async () => {
  try {
    const res = await $fetch(`${apiBase}/discussions?meet_id=${route.params.id}`, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    discussions.value = res.data || res || []
  } catch (err) {
    console.error('Fetch discussions error:', err)
  }
}

onMounted(async () => {
  await fetchMeetDetail()
  await fetchQuizzes()
  await fetchDiscussions()
})

const handleMaterialFileChange = (e) => {
  materialForm.file = e.target.files[0] || null
}

const handleSubmitFileChange = (e) => {
  submitForm.file = e.target.files[0] || null
}

const openAddMaterialModal = () => {
  Object.assign(materialForm, { title: '', type: 'file', content: '', external_url: '', file: null })
  showMaterialModal.value = true
}

const openAddQuizModal = () => {
  Object.assign(quizForm, { title: '', instructions: '', quiz_type: 'assignment', due_date: '', max_score: 100 })
  showQuizModal.value = true
}

const openSubmitModal = (quiz) => {
  selectedQuiz.value = quiz
  Object.assign(submitForm, { submission_text: '', file: null })
  showSubmitModal.value = true
}

const openGradeModal = (sub) => {
  selectedSubmission.value = sub
  Object.assign(gradeForm, { score: sub.score || 100, teacher_feedback: sub.teacher_feedback || '' })
  showGradeModal.value = true
}

const handleSaveMaterial = async () => {
  const formData = new FormData()
  formData.append('meet_id', route.params.id)
  formData.append('title', materialForm.title)
  formData.append('type', materialForm.type)
  if (materialForm.content) formData.append('content', materialForm.content)
  if (materialForm.external_url) formData.append('external_url', materialForm.external_url)
  if (materialForm.file) formData.append('file', materialForm.file)

  try {
    await $fetch(`${apiBase}/materials`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: formData
    })
    showMaterialModal.value = false
    await fetchMeetDetail()
  } catch (err) {
    alert(err.data?.message || 'Gagal menyimpan materi.')
  }
}

const handleSaveQuiz = async () => {
  try {
    await $fetch(`${apiBase}/quizzes`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: { ...quizForm, meet_id: route.params.id }
    })
    showQuizModal.value = false
    await fetchQuizzes()
  } catch (err) {
    alert(err.data?.message || 'Gagal menyimpan kuis.')
  }
}

const handleSubmitTask = async () => {
  const formData = new FormData()
  formData.append('quiz_id', selectedQuiz.value.id)
  if (submitForm.submission_text) formData.append('submission_text', submitForm.submission_text)
  if (submitForm.file) formData.append('file', submitForm.file)

  try {
    await $fetch(`${apiBase}/quizzes/submit`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: formData
    })
    showSubmitModal.value = false
    await fetchQuizzes()
    alert('Tugas Anda berhasil dikumpulkan!')
  } catch (err) {
    alert(err.data?.message || 'Gagal mengumpulkan tugas.')
  }
}

const handleSaveGrade = async () => {
  try {
    await $fetch(`${apiBase}/submissions/${selectedSubmission.value.id}/grade`, {
      method: 'PUT',
      headers: { Authorization: `Bearer ${token.value}` },
      body: gradeForm
    })
    showGradeModal.value = false
    await fetchQuizzes()
  } catch (err) {
    alert(err.data?.message || 'Gagal menyimpan nilai.')
  }
}

const handleSendDiscussion = async (parentId = null) => {
  const text = discussionMessage.value.trim()
  if (!text) return

  try {
    await $fetch(`${apiBase}/discussions`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: {
        meet_id: route.params.id,
        parent_id: parentId,
        message: text
      }
    })
    discussionMessage.value = ''
    replyingToId.value = null
    await fetchDiscussions()
  } catch (err) {
    alert(err.data?.message || 'Gagal mengirim pesan diskusi')
  }
}

const getEmbedYoutubeUrl = (url) => {
  if (!url) return ''
  const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/
  const match = url.match(regExp)
  return (match && match[2].length === 11) ? `https://www.youtube.com/embed/${match[2]}` : url
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-sans text-slate-800 pb-12">
    <!-- Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <NuxtLink 
          :to="meet ? `/elearning/courses/${meet.course_id}` : '/elearning'" 
          class="flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-emerald-600 transition"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Kembali ke Daftar Pertemuan
        </NuxtLink>
        <span class="text-xs font-bold text-emerald-800 bg-emerald-100 px-3 py-1 rounded-full">
          Pertemuan {{ meet?.meeting_number || '-' }}
        </span>
      </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
      <div v-if="loading" class="bg-white rounded-2xl p-12 text-center text-gray-400">
        Memuat sesi pertemuan...
      </div>

      <template v-else-if="meet">
        <!-- Banner Header Pertemuan -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-100 mb-6">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
            <div>
              <span class="text-xs font-bold text-emerald-600 uppercase tracking-wider">
                {{ meet.course?.subject?.name }} ({{ meet.course?.class?.class_name?.name }})
              </span>
              <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1">
                {{ meet.title }}
              </h1>
            </div>

            <div v-if="meet.type === 'online' && meet.meeting_link">
              <a 
                :href="meet.meeting_link" 
                target="_blank"
                class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition shadow-sm flex items-center gap-2"
              >
                Gabung Virtual Meet (Zoom/GMeet)
              </a>
            </div>
          </div>

          <p class="text-sm text-slate-600 leading-relaxed mt-4">
            {{ meet.description || 'Tidak ada instruksi khusus.' }}
          </p>

          <!-- Tab Selector Header -->
          <div class="flex gap-2 pt-6 border-t border-slate-100 mt-6">
            <button 
              @click="activeTab = 'materials'"
              class="px-4 py-2 rounded-xl font-bold text-xs transition"
              :class="activeTab === 'materials' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            >
              📁 Modul & Materi ({{ meet.materials?.length || 0 }})
            </button>
            <button 
              @click="activeTab = 'quizzes'"
              class="px-4 py-2 rounded-xl font-bold text-xs transition"
              :class="activeTab === 'quizzes' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            >
              📝 Kuis & Tugas ({{ quizzes.length }})
            </button>
            <button 
              @click="activeTab = 'discussions'"
              class="px-4 py-2 rounded-xl font-bold text-xs transition"
              :class="activeTab === 'discussions' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            >
              💬 Forum Diskusi ({{ discussions.length }})
            </button>
          </div>
        </div>

        <!-- TAB 1: MATERI -->
        <div v-if="activeTab === 'materials'" class="space-y-4">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-bold text-slate-900">Materi Pembelajaran</h3>
            <button v-if="canManageLearning" @click="openAddMaterialModal" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition">
              + Unggah Materi Baru
            </button>
          </div>

          <div v-if="!meet.materials || meet.materials.length === 0" class="bg-white rounded-2xl p-8 text-center text-slate-400">
            Belum ada materi pada pertemuan ini.
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="mat in meet.materials" :key="mat.id" class="bg-white p-5 rounded-2xl border border-slate-100 shadow-xs space-y-3">
              <span class="px-2.5 py-1 text-[10px] font-bold rounded-lg uppercase" :class="mat.type === 'file' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700'">
                {{ mat.type }}
              </span>
              <h4 class="font-bold text-slate-900 text-base">{{ mat.title }}</h4>
              <p v-if="mat.content" class="text-xs text-slate-600 line-clamp-3">{{ mat.content }}</p>

              <div v-if="mat.type === 'video' && mat.external_url" class="aspect-video w-full rounded-xl overflow-hidden bg-black">
                <iframe :src="getEmbedYoutubeUrl(mat.external_url)" class="w-full h-full" frameborder="0" allowfullscreen></iframe>
              </div>

              <div v-if="mat.file_path" class="pt-2">
                <a :href="`${storageBase}/${mat.file_path}`" target="_blank" class="px-4 py-2 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-xl inline-block">
                  Unduh Dokumen PDF / Modul 📥
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 2: KUIS & TUGAS -->
        <div v-else-if="activeTab === 'quizzes'" class="space-y-4">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-bold text-slate-900">Daftar Kuis & Tugas Mandiri</h3>
            <button v-if="canManageLearning" @click="openAddQuizModal" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition">
              + Buat Tugas / Kuis Baru
            </button>
          </div>

          <div v-if="quizzes.length === 0" class="bg-white rounded-2xl p-8 text-center text-slate-400">
            Belum ada tugas atau kuis yang dibuat untuk pertemuan ini.
          </div>

          <div v-else class="space-y-4">
            <div v-for="q in quizzes" :key="q.id" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-4">
              <div class="flex justify-between items-start">
                <div>
                  <span class="px-2.5 py-1 bg-amber-100 text-amber-800 text-[10px] font-bold uppercase rounded-lg">
                    {{ q.quiz_type }}
                  </span>
                  <h4 class="text-lg font-bold text-slate-900 mt-1">{{ q.title }}</h4>
                  <p class="text-xs text-slate-500 mt-1">Batas Pengumpulan: <span class="font-semibold text-rose-600">{{ q.due_date }}</span> • Max Nilai: {{ q.max_score }}</p>
                </div>
                <button @click="openSubmitModal(q)" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition">
                  Kumpulkan Tugas
                </button>
              </div>

              <p class="text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-100">
                {{ q.instructions || 'Tidak ada instruksi khusus.' }}
              </p>

              <!-- Submissions List (For Teacher / Review) -->
              <div v-if="canManageLearning && q.submissions && q.submissions.length > 0" class="pt-3 border-t border-slate-100 space-y-2">
                <h5 class="text-xs font-bold text-slate-700">Daftar Kiriman Tugas Siswa ({{ q.submissions.length }}):</h5>
                <div v-for="sub in q.submissions" :key="sub.id" class="flex justify-between items-center p-3 bg-slate-50 rounded-xl text-xs">
                  <div>
                    <span class="font-bold text-slate-900">{{ sub.student?.name || 'Siswa' }}</span>
                    <span class="ml-2 text-slate-400">({{ sub.submitted_at }})</span>
                    <div v-if="sub.score" class="text-emerald-600 font-bold mt-0.5">Nilai: {{ sub.score }} / {{ q.max_score }}</div>
                  </div>
                  <button @click="openGradeModal(sub)" class="px-3 py-1.5 bg-amber-100 text-amber-800 font-bold rounded-lg hover:bg-amber-200 transition">
                    Beri Nilai & Feedback
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 3: FORUM DISKUSI -->
        <div v-else-if="activeTab === 'discussions'" class="space-y-4">
          <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-4">
            <h3 class="text-lg font-bold text-slate-900">Forum Diskusi Interaktif</h3>

            <!-- New Discussion Input -->
            <div class="space-y-2">
              <textarea 
                v-model="discussionMessage"
                rows="3"
                placeholder="Tuliskan pertanyaan atau pendapat Anda di forum ini..."
                class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              ></textarea>
              <div class="flex justify-end">
                <button @click="handleSendDiscussion(null)" class="px-5 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition">
                  Kirim Pesan
                </button>
              </div>
            </div>

            <!-- Threaded Comments List -->
            <div v-if="discussions.length === 0" class="py-8 text-center text-xs text-slate-400">
              Belum ada diskusi pada pertemuan ini. Jadilah yang pertama bertanya!
            </div>

            <div v-else class="space-y-4 pt-4 border-t border-slate-100">
              <div v-for="disc in discussions" :key="disc.id" class="p-4 bg-slate-50 rounded-2xl space-y-2 border border-slate-100">
                <div class="flex justify-between items-center text-xs">
                  <div class="flex items-center gap-2">
                    <span class="font-bold text-slate-900">{{ disc.teacher?.name || disc.student?.name || 'Pengguna' }}</span>
                    <span class="px-2 py-0.5 text-[9px] font-bold rounded-full" :class="disc.teacher ? 'bg-purple-100 text-purple-700' : 'bg-emerald-100 text-emerald-700'">
                      {{ disc.teacher ? 'GURU' : 'SISWA' }}
                    </span>
                  </div>
                  <span class="text-slate-400 text-[10px]">{{ disc.created_at }}</span>
                </div>

                <p class="text-xs text-slate-700 leading-relaxed">{{ disc.message }}</p>

                <!-- Replies List -->
                <div v-if="disc.replies && disc.replies.length > 0" class="pl-4 border-l-2 border-emerald-300 space-y-2 pt-2">
                  <div v-for="reply in disc.replies" :key="reply.id" class="p-3 bg-white rounded-xl text-xs space-y-1">
                    <div class="flex items-center justify-between">
                      <span class="font-bold text-slate-900">{{ reply.teacher?.name || reply.student?.name || 'Pengguna' }}</span>
                      <span class="text-slate-400 text-[9px]">{{ reply.created_at }}</span>
                    </div>
                    <p class="text-slate-600">{{ reply.message }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- MODAL FORM MATERIAL -->
    <div v-if="canManageLearning && showMaterialModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <h3 class="text-lg font-bold text-slate-900">Unggah Materi Baru</h3>
        <form @submit.prevent="handleSaveMaterial" class="space-y-3 text-xs">
          <input v-model="materialForm.title" type="text" placeholder="Judul Materi" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl" required>
          <select v-model="materialForm.type" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
            <option value="file">File PDF / Dokumen</option>
            <option value="video">Video YouTube</option>
          </select>
          <input v-if="materialForm.type === 'file'" type="file" @change="handleMaterialFileChange" class="w-full">
          <input v-if="materialForm.type === 'video'" v-model="materialForm.external_url" type="url" placeholder="https://youtube.com/..." class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
          <textarea v-model="materialForm.content" placeholder="Rangkuman teks..." class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl" rows="2"></textarea>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showMaterialModal = false" class="px-4 py-2 border rounded-xl">Batal</button>
            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white font-bold rounded-xl">Simpan</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL FORM QUIZ -->
    <div v-if="canManageLearning && showQuizModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <h3 class="text-lg font-bold text-slate-900">Buat Tugas / Kuis Baru</h3>
        <form @submit.prevent="handleSaveQuiz" class="space-y-3 text-xs">
          <input v-model="quizForm.title" type="text" placeholder="Judul Tugas / Kuis" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl" required>
          <select v-model="quizForm.quiz_type" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
            <option value="assignment">Tugas Mandiri</option>
            <option value="quiz">Kuis Harian</option>
            <option value="exam">Ujian</option>
          </select>
          <input v-model="quizForm.due_date" type="datetime-local" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl" required>
          <textarea v-model="quizForm.instructions" placeholder="Instruksi pengerjaan..." class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl" rows="3"></textarea>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showQuizModal = false" class="px-4 py-2 border rounded-xl">Batal</button>
            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white font-bold rounded-xl">Simpan Tugas</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL SUBMIT TASK (SISWA) -->
    <div v-if="showSubmitModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <h3 class="text-lg font-bold text-slate-900">Kumpulkan Tugas: {{ selectedQuiz?.title }}</h3>
        <form @submit.prevent="handleSubmitTask" class="space-y-3 text-xs">
          <textarea v-model="submitForm.submission_text" placeholder="Tuliskan jawaban atau keterangan pengumpulan..." class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl" rows="4"></textarea>
          <div>
            <label class="block font-bold mb-1">Lampirkan File PDF / Lampiran (Opsional)</label>
            <input type="file" @change="handleSubmitFileChange" class="w-full">
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showSubmitModal = false" class="px-4 py-2 border rounded-xl">Batal</button>
            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white font-bold rounded-xl">Kirim Jawaban</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL GRADE SUBMISSION (GURU) -->
    <div v-if="canManageLearning && showGradeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <h3 class="text-lg font-bold text-slate-900">Beri Nilai & Ulasan Feedback</h3>
        <form @submit.prevent="handleSaveGrade" class="space-y-3 text-xs">
          <div>
            <label class="block font-bold mb-1">Nilai (0 - 100)</label>
            <input v-model.number="gradeForm.score" type="number" min="0" max="100" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl" required>
          </div>
          <div>
            <label class="block font-bold mb-1">Catatan / Feedback Guru</label>
            <textarea v-model="gradeForm.teacher_feedback" placeholder="Ulasan Pengerjaan..." class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl" rows="3"></textarea>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showGradeModal = false" class="px-4 py-2 border rounded-xl">Batal</button>
            <button type="submit" class="px-4 py-2 bg-amber-600 text-white font-bold rounded-xl">Simpan Nilai</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
