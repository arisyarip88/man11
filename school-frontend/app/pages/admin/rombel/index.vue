<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import AppDataTable from '~/components/ui/AppDataTable.vue'
import AppModal from '~/components/ui/AppModal.vue'
import AppButton from '~/components/ui/AppButton.vue'
import AppBadge from '~/components/ui/AppBadge.vue'

definePageMeta({
  layout: 'admin'
})

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie('auth_token')

const rombelList = ref([])
const semestersList = ref([])
const classNamesList = ref([])
const teachersList = ref([])

const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const selectedSemester = ref('')
const loading = ref(false)
const actionLoading = ref(false)

const showModal = ref(false)
const isEditing = ref(false)
const currentId = ref(null)

// Delete confirmation modal state
const showDeleteModal = ref(false)
const deleteTargetItem = ref(null)

// User feedback message
const feedbackMessage = ref({ text: '', type: '' })

const form = reactive({
  semester_id: '',
  class_name_id: '',
  homeroom_teacher_id: '',
  capacity: 36,
  room_number: ''
})

const errors = ref({})

const tableColumns = [
  { key: 'class_name', label: 'Nama Kelas' },
  { key: 'semester', label: 'Tahun Ajaran / Semester' },
  { key: 'homeroom_teacher', label: 'Wali Kelas' },
  { key: 'room_number', label: 'Ruangan' },
  { key: 'students_count', label: 'Siswa Terdaftar' },
  { key: 'actions', label: 'Aksi', align: 'right' },
]

const showFeedback = (text, type = 'success') => {
  feedbackMessage.value = { text, type }
  setTimeout(() => {
    feedbackMessage.value = { text: '', type: '' }
  }, 4000)
}

const fetchOptions = async () => {
  try {
    const [semRes, clsRes, tchRes] = await Promise.all([
      $fetch(`${apiBase}/semesters?paginate=false`, { headers: { Authorization: `Bearer ${token.value}` } }),
      $fetch(`${apiBase}/classes?paginate=false`, { headers: { Authorization: `Bearer ${token.value}` } }),
      $fetch(`${apiBase}/teachers?paginate=false`, { headers: { Authorization: `Bearer ${token.value}` } })
    ])

    semestersList.value = semRes.data || semRes || []
    classNamesList.value = clsRes.data || clsRes || []
    teachersList.value = tchRes.data || tchRes || []

    const activeSem = semestersList.value.find(s => s.is_active)
    if (activeSem && !selectedSemester.value) {
      selectedSemester.value = activeSem.id
    }
  } catch (err) {
    console.error('Fetch options error:', err)
  }
}

const fetchRombel = async (page = 1) => {
  loading.value = true
  try {
    const res = await $fetch(`${apiBase}/rombel`, {
      params: {
        page,
        search: search.value,
        semester_id: selectedSemester.value
      },
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })

    rombelList.value = res.data || []
    pagination.value = {
      current_page: res.current_page || 1,
      last_page: res.last_page || 1,
      total: res.total || 0
    }
  } catch (err) {
    console.error('Fetch rombel error:', err)
  } finally {
    loading.value = false
  }
}

watch([search, selectedSemester], () => {
  fetchRombel(1)
})

onMounted(async () => {
  await fetchOptions()
  fetchRombel()
})

const resetForm = () => {
  Object.assign(form, {
    semester_id: selectedSemester.value || (semestersList.value[0]?.id || ''),
    class_name_id: '',
    homeroom_teacher_id: '',
    capacity: 36,
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
    semester_id: item.semester_id,
    class_name_id: item.class_name_id,
    homeroom_teacher_id: item.homeroom_teacher_id || '',
    capacity: item.capacity || 36,
    room_number: item.room_number || ''
  })

  showModal.value = true
}

const handleSubmit = async () => {
  errors.value = {}
  actionLoading.value = true

  const endpoint = isEditing.value
    ? `${apiBase}/rombel/${currentId.value}`
    : `${apiBase}/rombel`

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
    showFeedback(isEditing.value ? 'Rombel berhasil diperbarui.' : 'Rombel baru berhasil dibuat.', 'success')
    fetchRombel(pagination.value.current_page)
  } catch (err) {
    if (err.data && err.data.errors) {
      errors.value = err.data.errors
    } else {
      showFeedback(err.data?.message || 'Terjadi kesalahan saat menyimpan data.', 'error')
    }
  } finally {
    actionLoading.value = false
  }
}

const promptDelete = (item) => {
  deleteTargetItem.value = item
  showDeleteModal.value = true
}

const executeDelete = async () => {
  if (!deleteTargetItem.value) return
  actionLoading.value = true

  try {
    await $fetch(`${apiBase}/rombel/${deleteTargetItem.value.id}`, {
      method: 'DELETE',
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })
    showDeleteModal.value = false
    deleteTargetItem.value = null
    showFeedback('Rombel berhasil dihapus.', 'success')
    fetchRombel(pagination.value.current_page)
  } catch (err) {
    showFeedback(err.data?.message || 'Gagal menghapus Rombel.', 'error')
  } finally {
    actionLoading.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Feedback Notification Banner -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="feedbackMessage.text"
        :class="[
          'p-4 rounded-2xl text-xs font-bold flex items-center justify-between border shadow-xs',
          feedbackMessage.type === 'error'
            ? 'bg-rose-50 border-rose-200 text-rose-800'
            : 'bg-brand-50 border-brand-200 text-brand-900'
        ]"
      >
        <span>{{ feedbackMessage.text }}</span>
        <button @click="feedbackMessage.text = ''" class="text-slate-400 hover:text-slate-600 font-normal ml-3">✕</button>
      </div>
    </Transition>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Manajemen Rombel (Penetapan Kelas)</h1>
        <p class="text-xs text-slate-500 mt-1">Kelola Rombongan Belajar, Wali Kelas, dan Kuota Siswa per Semester</p>
      </div>
      <AppButton
        @click="openAddModal"
        variant="primary"
        class="gap-2"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        <span>Buka Rombel Baru</span>
      </AppButton>
    </div>

    <!-- Filter & Search Card -->
    <div class="bg-white rounded-2xl p-4 shadow-xs border border-slate-200/90 flex flex-col md:flex-row gap-4 justify-between items-center">
      <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
        <div class="relative w-full sm:w-64">
          <input 
            v-model="search"
            type="text" 
            placeholder="Cari kelas/wali/ruangan..."
            class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 transition"
          >
          <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <select 
          v-model="selectedSemester"
          class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 text-slate-700"
        >
          <option value="">-- Semua Semester --</option>
          <option v-for="sem in semestersList" :key="sem.id" :value="sem.id">
            T.A {{ sem.academic_year }} ({{ sem.semester_type === 'odd' ? 'Ganjil' : 'Genap' }}) {{ sem.is_active ? '★ Aktif' : '' }}
          </option>
        </select>
      </div>

      <div class="text-xs text-slate-500">
        Total: <span class="font-bold text-slate-800">{{ pagination.total }}</span> Rombel
      </div>
    </div>

    <!-- Data Table Primitives -->
    <AppDataTable
      :columns="tableColumns"
      :rows="rombelList"
      :loading="loading"
      empty-title="Belum ada data rombel"
      empty-description="Silakan buat rombel baru atau sesuaikan filter pencarian semester."
    >
      <!-- Slot cell: class_name -->
      <template #cell-class_name="{ row }">
        <div>
          <NuxtLink :to="`/admin/rombel/${row.id}`" class="font-bold text-brand-700 hover:underline">
            {{ row.class_name?.name || '-' }}
          </NuxtLink>
          <div class="text-[11px] text-slate-400 mt-0.5">
            Tingkat {{ row.class_name?.grade_level || '-' }} ({{ row.class_name?.major || 'Umum' }})
          </div>
        </div>
      </template>

      <!-- Slot cell: semester -->
      <template #cell-semester="{ row }">
        <div class="font-medium text-slate-800">
          <span>T.A {{ row.semester?.academic_year || '-' }}</span>
          <AppBadge
            :variant="row.semester?.semester_type === 'odd' ? 'warning' : 'brand'"
            size="xs"
            class="ml-1.5"
          >
            {{ row.semester?.semester_type === 'odd' ? 'Ganjil' : 'Genap' }}
          </AppBadge>
        </div>
      </template>

      <!-- Slot cell: homeroom_teacher -->
      <template #cell-homeroom_teacher="{ row }">
        <span class="text-slate-700 font-medium">
          {{ row.homeroom_teacher?.name || 'Belum diatur' }}
        </span>
      </template>

      <!-- Slot cell: room_number -->
      <template #cell-room_number="{ row }">
        <span class="font-mono text-xs text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">
          {{ row.room_number || '-' }}
        </span>
      </template>

      <!-- Slot cell: students_count -->
      <template #cell-students_count="{ row }">
        <span class="font-bold text-slate-900">{{ row.students_count || 0 }}</span>
        <span class="text-slate-400"> / {{ row.capacity }} Siswa</span>
      </template>

      <!-- Slot cell: actions -->
      <template #cell-actions="{ row }">
        <div class="flex items-center justify-end gap-1.5">
          <NuxtLink 
            :to="`/admin/rombel/${row.id}`"
            class="px-2.5 py-1.5 bg-brand-50 text-brand-800 hover:bg-brand-100 rounded-lg text-xs font-semibold transition"
          >
            Anggota
          </NuxtLink>
          <button 
            type="button"
            @click="openEditModal(row)"
            class="px-2.5 py-1.5 bg-amber-50 text-amber-700 hover:bg-amber-100 rounded-lg text-xs font-semibold transition cursor-pointer"
          >
            Edit
          </button>
          <button 
            type="button"
            @click="promptDelete(row)"
            class="px-2.5 py-1.5 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-lg text-xs font-semibold transition cursor-pointer"
          >
            Hapus
          </button>
        </div>
      </template>

      <!-- Slot Footer: Pagination -->
      <template #footer>
        <span class="text-xs text-slate-500">
          Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}
        </span>
        <div class="flex gap-2">
          <AppButton
            variant="outline"
            size="xs"
            :disabled="pagination.current_page <= 1"
            @click="fetchRombel(pagination.current_page - 1)"
          >
            Sebelumnya
          </AppButton>
          <AppButton
            variant="outline"
            size="xs"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="fetchRombel(pagination.current_page + 1)"
          >
            Selanjutnya
          </AppButton>
        </div>
      </template>
    </AppDataTable>

    <!-- Modal Form Tambah / Edit Rombel -->
    <AppModal
      v-model="showModal"
      :title="isEditing ? 'Edit Rombel' : 'Buka Rombel Baru'"
      :description="isEditing ? 'Perbarui data penetapan kelas rombel.' : 'Tentukan kelas, semester, dan wali kelas rombel baru.'"
      max-width="md"
    >
      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Tahun Ajaran / Semester *</label>
          <select 
            v-model="form.semester_id"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600"
            required
          >
            <option value="">-- Pilih Semester --</option>
            <option v-for="sem in semestersList" :key="sem.id" :value="sem.id">
              T.A {{ sem.academic_year }} ({{ sem.semester_type === 'odd' ? 'Ganjil' : 'Genap' }})
            </option>
          </select>
          <p v-if="errors.semester_id" class="text-xs text-rose-600 mt-1">{{ errors.semester_id[0] }}</p>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Master Kelas *</label>
          <select 
            v-model="form.class_name_id"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600"
            required
          >
            <option value="">-- Pilih Master Kelas --</option>
            <option v-for="cls in classNamesList" :key="cls.id" :value="cls.id">
              {{ cls.name }} (Tingkat {{ cls.grade_level }})
            </option>
          </select>
          <p v-if="errors.class_name_id" class="text-xs text-rose-600 mt-1">{{ errors.class_name_id[0] }}</p>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Wali Kelas</label>
          <select 
            v-model="form.homeroom_teacher_id"
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600"
          >
            <option value="">-- Pilih Wali Kelas (Opsional) --</option>
            <option v-for="tch in teachersList" :key="tch.id" :value="tch.id">
              {{ tch.name }} (NIP: {{ tch.nip }})
            </option>
          </select>
          <p v-if="errors.homeroom_teacher_id" class="text-xs text-rose-600 mt-1">{{ errors.homeroom_teacher_id[0] }}</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Kapasitas Siswa *</label>
            <input 
              v-model.number="form.capacity"
              type="number" 
              min="1"
              max="100"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600"
              required
            >
            <p v-if="errors.capacity" class="text-xs text-rose-600 mt-1">{{ errors.capacity[0] }}</p>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Nomor Ruangan</label>
            <input 
              v-model="form.room_number"
              type="text" 
              placeholder="Misal: R.101"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600"
            >
            <p v-if="errors.room_number" class="text-xs text-rose-600 mt-1">{{ errors.room_number[0] }}</p>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
          <AppButton 
            type="button" 
            variant="outline"
            @click="showModal = false"
          >
            Batal
          </AppButton>
          <AppButton 
            type="submit" 
            variant="primary"
            :loading="actionLoading"
          >
            Simpan Rombel
          </AppButton>
        </div>
      </form>
    </AppModal>

    <!-- Modal Konfirmasi Hapus Rombel (Menggantikan window.confirm) -->
    <AppModal
      v-model="showDeleteModal"
      title="Hapus Rombongan Belajar?"
      description="Tindakan ini tidak dapat dibatalkan. Pastikan kelas ini tidak memiliki data keterikatan aktif."
      max-width="sm"
    >
      <div class="space-y-4">
        <p class="text-xs text-slate-600">
          Apakah Anda yakin ingin menghapus rombel 
          <b class="text-slate-900">{{ deleteTargetItem?.class_name?.name || 'ini' }}</b>?
        </p>
        <div class="flex justify-end gap-2 pt-2">
          <AppButton 
            variant="outline" 
            @click="showDeleteModal = false"
          >
            Batal
          </AppButton>
          <AppButton 
            variant="danger" 
            :loading="actionLoading"
            @click="executeDelete"
          >
            Ya, Hapus
          </AppButton>
        </div>
      </div>
    </AppModal>
  </div>
</template>
