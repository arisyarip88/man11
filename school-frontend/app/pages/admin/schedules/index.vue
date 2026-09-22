<script setup>
import { ref, reactive, onMounted, watch } from 'vue'

definePageMeta({
  layout: 'admin'
})

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie('auth_token')

const schedulesList = ref([])
const rombelList = ref([])
const coursesList = ref([])
const semestersList = ref([])

const selectedSemester = ref('')
const selectedRombel = ref('')
const selectedDay = ref('')
const loading = ref(false)

const showModal = ref(false)
const isEditing = ref(false)
const currentId = ref(null)

const daysMap = {
  monday: 'Senin',
  tuesday: 'Selasa',
  wednesday: 'Rabu',
  thursday: 'Kamis',
  friday: 'Jumat',
  saturday: 'Sabtu'
}

const form = reactive({
  course_id: '',
  day: 'monday',
  start_time: '07:30',
  end_time: '09:00',
  room_number: ''
})

const errors = ref({})

const fetchOptions = async () => {
  try {
    const [semRes, rmbRes, crsRes] = await Promise.all([
      $fetch(`${apiBase}/semesters?paginate=false`, { headers: { Authorization: `Bearer ${token.value}` } }),
      $fetch(`${apiBase}/rombel?paginate=false`, { headers: { Authorization: `Bearer ${token.value}` } }),
      $fetch(`${apiBase}/courses?paginate=false`, { headers: { Authorization: `Bearer ${token.value}` } })
    ])

    semestersList.value = semRes.data || semRes || []
    rombelList.value = rmbRes.data || rmbRes || []
    coursesList.value = crsRes.data || crsRes || []

    const activeSem = semestersList.value.find(s => s.is_active)
    if (activeSem && !selectedSemester.value) {
      selectedSemester.value = activeSem.id
    }
  } catch (err) {
    console.error('Fetch options error:', err)
  }
}

const fetchSchedules = async () => {
  loading.value = true
  try {
    const res = await $fetch(`${apiBase}/schedules?paginate=false`, {
      params: {
        semester_id: selectedSemester.value,
        class_id: selectedRombel.value,
        day: selectedDay.value
      },
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })

    schedulesList.value = res.data || res || []
  } catch (err) {
    console.error('Fetch schedules error:', err)
  } finally {
    loading.value = false
  }
}

watch([selectedSemester, selectedRombel, selectedDay], () => {
  fetchSchedules()
})

onMounted(async () => {
  await fetchOptions()
  fetchSchedules()
})

const resetForm = () => {
  Object.assign(form, {
    course_id: '',
    day: 'monday',
    start_time: '07:30',
    end_time: '09:00',
    room_number: ''
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
    course_id: item.course_id,
    day: item.day,
    start_time: item.start_time ? item.start_time.substring(0, 5) : '07:30',
    end_time: item.end_time ? item.end_time.substring(0, 5) : '09:00',
    room_number: item.room_number || ''
  })

  showModal.value = true
}

const handleSubmit = async () => {
  errors.value = {}
  loading.value = true

  const endpoint = isEditing.value
    ? `${apiBase}/schedules/${currentId.value}`
    : `${apiBase}/schedules`

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
    fetchSchedules()
  } catch (err) {
    if (err.data && err.data.errors) {
      errors.value = err.data.errors
    } else {
      alert(err.data?.message || 'Terjadi kesalahan saat menyimpan jadwal.')
    }
  } finally {
    loading.value = false
  }
}

const handleDelete = async (id) => {
  if (!confirm('Apakah Anda yakin ingin menghapus jadwal ini?')) return

  try {
    await $fetch(`${apiBase}/schedules/${id}`, {
      method: 'DELETE',
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })
    fetchSchedules()
  } catch (err) {
    alert(err.data?.message || 'Gagal menghapus jadwal')
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Manajemen Jadwal Pelajaran (Schedule)</h1>
        <p class="text-sm text-gray-500">Susun Jadwal KBM Mingguan (Senin–Sabtu) untuk Kelas & Guru</p>
      </div>
      <button 
        @click="openAddModal"
        class="inline-flex items-center justify-center px-4 py-2.5 bg-emerald-600 text-white font-medium text-sm rounded-xl hover:bg-emerald-700 transition shadow-sm gap-2"
      >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Tambah Jadwal Pelajaran
      </button>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex flex-col md:flex-row gap-4 justify-between items-center">
      <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
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

        <select 
          v-model="selectedDay"
          class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
        >
          <option value="">-- Semua Hari --</option>
          <option v-for="(label, key) in daysMap" :key="key" :value="key">{{ label }}</option>
        </select>
      </div>

      <div class="text-xs text-gray-500">
        Total: <span class="font-bold text-gray-700">{{ schedulesList.length }}</span> Sesi Jadwal
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
              <th class="py-4 px-6">Hari & Jam</th>
              <th class="py-4 px-6">Mata Pelajaran</th>
              <th class="py-4 px-6">Rombel Kelas</th>
              <th class="py-4 px-6">Guru Pengampu</th>
              <th class="py-4 px-6">Ruangan</th>
              <th class="py-4 px-6 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
            <tr v-if="loading && schedulesList.length === 0">
              <td colspan="6" class="py-8 text-center text-gray-400">Memuat data jadwal pelajaran...</td>
            </tr>
            <tr v-else-if="schedulesList.length === 0">
              <td colspan="6" class="py-8 text-center text-gray-400">Tidak ada data jadwal ditemukan.</td>
            </tr>
            <tr v-for="item in schedulesList" :key="item.id" class="hover:bg-gray-50/50 transition">
              <td class="py-4 px-6">
                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 font-bold text-xs rounded-lg uppercase">
                  {{ daysMap[item.day] || item.day }}
                </span>
                <div class="text-xs font-mono font-semibold text-gray-600 mt-1">
                  {{ item.start_time?.substring(0, 5) }} - {{ item.end_time?.substring(0, 5) }} WIB
                </div>
              </td>
              <td class="py-4 px-6 font-bold text-gray-900">
                {{ item.course?.subject?.name || '-' }}
              </td>
              <td class="py-4 px-6 font-semibold text-gray-800">
                {{ item.course?.class?.class_name?.name || '-' }}
              </td>
              <td class="py-4 px-6 text-gray-700">
                {{ item.course?.teacher?.name || '-' }}
              </td>
              <td class="py-4 px-6 font-mono text-gray-600">
                {{ item.room_number || item.course?.class?.room_number || '-' }}
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
    </div>

    <!-- Modal Form -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-100 space-y-4">
        <div class="flex justify-between items-center border-b border-gray-100 pb-3">
          <h3 class="text-lg font-bold text-gray-900">
            {{ isEditing ? 'Edit Jadwal Pelajaran' : 'Tambah Jadwal Pelajaran Baru' }}
          </h3>
          <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Pengalokasian Course (Mapel & Rombel) *</label>
            <select 
              v-model="form.course_id"
              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
              required
            >
              <option value="">-- Pilih Course --</option>
              <option v-for="crs in coursesList" :key="crs.id" :value="crs.id">
                {{ crs.subject?.name }} - {{ crs.class?.class_name?.name }} (Guru: {{ crs.teacher?.name }})
              </option>
            </select>
            <p v-if="errors.course_id" class="text-xs text-rose-500 mt-1">{{ errors.course_id[0] }}</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Hari *</label>
            <select 
              v-model="form.day"
              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
              required
            >
              <option v-for="(label, key) in daysMap" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Jam Mulai *</label>
              <input 
                v-model="form.start_time"
                type="time" 
                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                required
              >
            </div>

            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Jam Selesai *</label>
              <input 
                v-model="form.end_time"
                type="time" 
                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                required
              >
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Nomor Ruangan (Opsional)</label>
            <input 
              v-model="form.room_number"
              type="text" 
              placeholder="Misal: R.101"
              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
            >
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
