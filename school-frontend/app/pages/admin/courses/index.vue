<script setup>
import { ref, reactive, onMounted, watch } from 'vue'

definePageMeta({
  layout: 'admin'
})

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie('auth_token')

const coursesList = ref([])
const rombelList = ref([])
const subjectsList = ref([])
const teachersList = ref([])
const semestersList = ref([])

const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const selectedSemester = ref('')
const selectedRombel = ref('')
const loading = ref(false)

const showModal = ref(false)
const isEditing = ref(false)
const currentId = ref(null)

const form = reactive({
  class_id: '',
  subject_id: '',
  teacher_id: '',
  credit_hours: 2
})

const errors = ref({})

const fetchOptions = async () => {
  try {
    const [semRes, rmbRes, sbjRes, tchRes] = await Promise.all([
      $fetch(`${apiBase}/semesters?paginate=false`, { headers: { Authorization: `Bearer ${token.value}` } }),
      $fetch(`${apiBase}/rombel?paginate=false`, { headers: { Authorization: `Bearer ${token.value}` } }),
      $fetch(`${apiBase}/subjects?paginate=false`, { headers: { Authorization: `Bearer ${token.value}` } }),
      $fetch(`${apiBase}/teachers?paginate=false`, { headers: { Authorization: `Bearer ${token.value}` } })
    ])

    semestersList.value = semRes.data || semRes || []
    rombelList.value = rmbRes.data || rmbRes || []
    subjectsList.value = sbjRes.data || sbjRes || []
    teachersList.value = tchRes.data || tchRes || []

    const activeSem = semestersList.value.find(s => s.is_active)
    if (activeSem && !selectedSemester.value) {
      selectedSemester.value = activeSem.id
    }
  } catch (err) {
    console.error('Fetch options error:', err)
  }
}

const fetchCourses = async (page = 1) => {
  loading.value = true
  try {
    const res = await $fetch(`${apiBase}/courses`, {
      params: {
        page,
        search: search.value,
        semester_id: selectedSemester.value,
        class_id: selectedRombel.value
      },
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })

    coursesList.value = res.data || []
    pagination.value = {
      current_page: res.current_page || 1,
      last_page: res.last_page || 1,
      total: res.total || 0
    }
  } catch (err) {
    console.error('Fetch courses error:', err)
  } finally {
    loading.value = false
  }
}

watch([search, selectedSemester, selectedRombel], () => {
  fetchCourses(1)
})

onMounted(async () => {
  await fetchOptions()
  fetchCourses()
})

const resetForm = () => {
  Object.assign(form, {
    class_id: '',
    subject_id: '',
    teacher_id: '',
    credit_hours: 2
  })
  errors.value = {}
}

const openAddModal = () => {
  isEditing.value = false
  currentId.value = null
  resetForm()
  showModal.value = true
}

const openEditModal = (item) => {
  isEditing.value = true
  currentId.value = item.id
  resetForm()

  Object.assign(form, {
    class_id: item.class_id,
    subject_id: item.subject_id,
    teacher_id: item.teacher_id,
    credit_hours: item.credit_hours || 2
  })

  showModal.value = true
}

const handleSubmit = async () => {
  errors.value = {}
  loading.value = true

  const endpoint = isEditing.value
    ? `${apiBase}/courses/${currentId.value}`
    : `${apiBase}/courses`

  const method = isEditing.value ? 'PUT' : 'POST'

  try {
    await $fetch(endpoint, {
      method,
      headers: {
        Authorization: `Bearer ${token.value}`
      },
      body: form
    })

    showModal.value = false
    fetchCourses(pagination.value.current_page)
  } catch (err) {
    if (err.data && err.data.errors) {
      errors.value = err.data.errors
    } else {
      alert(err.data?.message || 'Terjadi kesalahan saat menyimpan data.')
    }
  } finally {
    loading.value = false
  }
}

const handleDelete = async (id) => {
  if (!confirm('Apakah Anda yakin ingin menghapus plot pengajaran ini?')) return

  try {
    await $fetch(`${apiBase}/courses/${id}`, {
      method: 'DELETE',
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })
    fetchCourses(pagination.value.current_page)
  } catch (err) {
    alert(err.data?.message || 'Gagal menghapus data')
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Plotting Pengajaran (Course)</h1>
        <p class="text-sm text-gray-500">Alokasikan Mata Pelajaran, Rombel Kelas, dan Guru Pengampu KBM</p>
      </div>
      <button 
        @click="openAddModal"
        class="inline-flex items-center justify-center px-4 py-2.5 bg-emerald-600 text-white font-medium text-sm rounded-xl hover:bg-emerald-700 transition shadow-sm gap-2"
      >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Tambah Plot Pengajaran
      </button>
    </div>

    <!-- Filter & Search Card -->
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex flex-col md:flex-row gap-4 justify-between items-center">
      <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
        <div class="relative w-full sm:w-64">
          <input 
            v-model="search"
            type="text" 
            placeholder="Cari mapel / guru / kelas..."
            class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
          >
          <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <select 
          v-model="selectedSemester"
          class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
        >
          <option value="">-- Semua Semester --</option>
          <option v-for="sem in semestersList" :key="sem.id" :value="sem.id">
            T.A {{ sem.academic_year }} ({{ sem.semester_type === 'odd' ? 'Ganjil' : 'Genap' }})
          </option>
        </select>

        <select 
          v-model="selectedRombel"
          class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
        >
          <option value="">-- Semua Rombel --</option>
          <option v-for="rmb in rombelList" :key="rmb.id" :value="rmb.id">
            {{ rmb.class_name?.name }}
          </option>
        </select>
      </div>

      <div class="text-xs text-gray-500">
        Total: <span class="font-bold text-gray-700">{{ pagination.total }}</span> Pengajaran
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
              <th class="py-4 px-6">Mata Pelajaran</th>
              <th class="py-4 px-6">Rombel Kelas</th>
              <th class="py-4 px-6">Guru Pengampu</th>
              <th class="py-4 px-6">Beban JP</th>
              <th class="py-4 px-6">Pertemuan</th>
              <th class="py-4 px-6 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
            <tr v-if="loading && coursesList.length === 0">
              <td colspan="6" class="py-8 text-center text-gray-400">Memuat data pengajaran...</td>
            </tr>
            <tr v-else-if="coursesList.length === 0">
              <td colspan="6" class="py-8 text-center text-gray-400">Tidak ada data plotting pengajaran ditemukan.</td>
            </tr>
            <tr v-for="item in coursesList" :key="item.id" class="hover:bg-gray-50/50 transition">
              <td class="py-4 px-6">
                <div class="font-bold text-gray-900">{{ item.subject?.name }}</div>
                <div class="text-xs font-mono text-emerald-600">{{ item.subject?.code }}</div>
              </td>
              <td class="py-4 px-6">
                <span class="font-semibold text-gray-800">{{ item.class?.class_name?.name || '-' }}</span>
                <div class="text-xs text-gray-400">T.A {{ item.class?.semester?.academic_year || '-' }}</div>
              </td>
              <td class="py-4 px-6">
                <div class="font-medium text-gray-800">{{ item.teacher?.name || '-' }}</div>
                <div class="text-xs text-gray-400">NIP: {{ item.teacher?.nip || '-' }}</div>
              </td>
              <td class="py-4 px-6">
                <span class="px-2.5 py-1 bg-blue-5 text-blue-700 font-semibold text-xs rounded-lg border border-blue-100">
                  {{ item.credit_hours }} JP
                </span>
              </td>
              <td class="py-4 px-6 text-gray-600">
                {{ item.meets_count || 0 }} Pertemuan
              </td>
              <td class="py-4 px-6 text-right space-x-2">
                <button 
                  @click="openEditModal(item)"
                  class="px-3 py-1.5 bg-amber-50 text-amber-600 hover:bg-amber-100 rounded-lg text-xs font-semibold transition"
                >
                  Edit
                </button>
                <button 
                  @click="handleDelete(item.id)"
                  class="px-3 py-1.5 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-lg text-xs font-semibold transition"
                >
                  Hapus
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
        <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
        <div class="flex gap-2">
          <button 
            :disabled="pagination.current_page <= 1"
            @click="fetchCourses(pagination.current_page - 1)"
            class="px-3 py-1.5 border border-gray-200 rounded-lg disabled:opacity-40 hover:bg-gray-50 transition"
          >
            Sebelumnya
          </button>
          <button 
            :disabled="pagination.current_page >= pagination.last_page"
            @click="fetchCourses(pagination.current_page + 1)"
            class="px-3 py-1.5 border border-gray-200 rounded-lg disabled:opacity-40 hover:bg-gray-50 transition"
          >
            Selanjutnya
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-100 space-y-4">
        <div class="flex justify-between items-center border-b border-gray-100 pb-3">
          <h3 class="text-lg font-bold text-gray-900">
            {{ isEditing ? 'Edit Plot Pengajaran' : 'Tambah Plot Pengajaran Baru' }}
          </h3>
          <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Rombel Kelas *</label>
            <select 
              v-model="form.class_id"
              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
              required
            >
              <option value="">-- Pilih Rombel --</option>
              <option v-for="rmb in rombelList" :key="rmb.id" :value="rmb.id">
                {{ rmb.class_name?.name }} (T.A {{ rmb.semester?.academic_year }})
              </option>
            </select>
            <p v-if="errors.class_id" class="text-xs text-rose-500 mt-1">{{ errors.class_id[0] }}</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Mata Pelajaran *</label>
            <select 
              v-model="form.subject_id"
              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
              required
            >
              <option value="">-- Pilih Mata Pelajaran --</option>
              <option v-for="sbj in subjectsList" :key="sbj.id" :value="sbj.id">
                {{ sbj.code }} - {{ sbj.name }}
              </option>
            </select>
            <p v-if="errors.subject_id" class="text-xs text-rose-500 mt-1">{{ errors.subject_id[0] }}</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Guru Pengampu *</label>
            <select 
              v-model="form.teacher_id"
              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
              required
            >
              <option value="">-- Pilih Guru --</option>
              <option v-for="tch in teachersList" :key="tch.id" :value="tch.id">
                {{ tch.name }} (NIP: {{ tch.nip }})
              </option>
            </select>
            <p v-if="errors.teacher_id" class="text-xs text-rose-500 mt-1">{{ errors.teacher_id[0] }}</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Beban Jam Pelajaran (JP) *</label>
            <input 
              v-model.number="form.credit_hours"
              type="number" 
              min="1"
              max="10"
              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
              required
            >
            <p v-if="errors.credit_hours" class="text-xs text-rose-500 mt-1">{{ errors.credit_hours[0] }}</p>
          </div>

          <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
            <button 
              type="button" 
              @click="showModal = false"
              class="px-4 py-2 border border-gray-200 text-gray-600 rounded-xl text-xs font-semibold hover:bg-gray-50 transition"
            >
              Batal
            </button>
            <button 
              type="submit" 
              :disabled="loading"
              class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-semibold hover:bg-emerald-700 transition disabled:opacity-50"
            >
              {{ loading ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
