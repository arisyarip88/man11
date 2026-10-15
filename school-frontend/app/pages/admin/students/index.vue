<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'

definePageMeta({
  layout: 'admin'
})

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie('auth_token')

const students = ref([])
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const activeFilter = ref('')
const loading = ref(false)

const showModal = ref(false)
const isEditing = ref(false)
const currentId = ref(null)
const picturePreview = ref(null)
const pictureFile = ref(null)

const form = reactive({
  nisn: '',
  nis: '',
  name: '',
  gender: 'male',
  email: '',
  phone: '',
  address: '',
  is_active: 'active'
})

const errors = ref({})

const fetchStudents = async (page = 1) => {
  loading.value = true
  try {
    const res = await $fetch(`${apiBase}/students`, {
      params: {
        page,
        search: search.value,
        is_active: activeFilter.value
      },
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })

    students.value = res.data || (Array.isArray(res) ? res : [])
    
    pagination.value = {
      current_page: res.current_page || 1,
      last_page: res.last_page || 1,
      total: res.total || students.value.length
    }
  } catch (err) {
    console.error('Fetch error:', err)
  } finally {
    loading.value = false
  }
}

watch([search, activeFilter], () => {
  fetchStudents(1)
})

onMounted(() => {
  fetchStudents()
})

const openAddModal = () => {
  isEditing.value = false
  currentId.value = null
  resetForm()
  showModal.value = true
}

const openEditModal = (student) => {
  isEditing.value = true
  currentId.value = student.id
  resetForm()
  
  Object.assign(form, {
    nisn: student.nisn || '',
    nis: student.nis || '',
    name: student.name || '',
    gender: student.gender || 'male',
    email: student.email || '',
    phone: student.phone || '',
    address: student.address || '',
    is_active: student.is_active||'active',
  })
  
  picturePreview.value = student.picture_url
  showModal.value = true
}

const resetForm = () => {
  Object.assign(form, {
    nisn: '',
    nis: '',
    name: '',
    gender: 'male',
    email: '',
    phone: '',
    address: '',
    is_active: 'active'
  })
  pictureFile.value = null
  picturePreview.value = null
  errors.value = {}
}

const handleFileUpload = (e) => {
  const file = e.target.files[0]
  if (file) {
    pictureFile.value = file
    picturePreview.value = URL.createObjectURL(file)
  }
}

const handleSubmit = async () => {
  errors.value = {}
  
  const formData = new FormData()
  
  Object.keys(form).forEach(key => {
    if (form[key] !== null && form[key] !== undefined) {
      formData.append(key, form[key])
    }
  })

  if (pictureFile.value) {
    formData.append('picture', pictureFile.value)
  }

  if (isEditing.value) {
    formData.append('_method', 'PUT')
  }

  const endpoint = isEditing.value 
    ? `${apiBase}/students/${currentId.value}`
    : `${apiBase}/students`

  try {
    await $fetch(endpoint, {
      method: 'POST',
      body: formData,
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })
    
    showModal.value = false
    search.value = ''
    activeFilter.value = ''
    await fetchStudents(1)
  } catch (err) {
    if (err.data && err.data.errors) {
      errors.value = err.data.errors
    } else {
      alert('Terjadi kesalahan saat menyimpan data.')
    }
  }
}

const handleDelete = async (id) => {
  if (!confirm('Apakah Anda yakin ingin menghapus data siswa ini?')) return

  try {
    await $fetch(`${apiBase}/students/${id}`, {
      method: 'DELETE',
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })
    fetchStudents(pagination.value.current_page)
  } catch (err) {
    alert('Gagal menghapus data')
  }
}

// Reset Sesi Perangkat (A7)
const resettingSessionId = ref(null)
const handleResetSession = async (student) => {
  if (!confirm(`Reset sesi perangkat untuk siswa "${student.name}" (NIS: ${student.nis || '-'})?\n\nSiswa akan dapat login kembali di perangkat baru.`)) return

  resettingSessionId.value = student.id
  try {
    const res = await $fetch(`${apiBase}/students/${student.id}/reset-session`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` }
    })
    alert(res.message || 'Sesi perangkat siswa berhasil direset.')
  } catch (err) {
    alert(err.data?.message || 'Gagal mereset sesi perangkat.')
  } finally {
    resettingSessionId.value = null
  }
}

// Import Siswa Excel (B8 & B9)
const showImportModal = ref(false)
const importStep = ref(1) // 1: Upload, 2: Preview, 3: Result
const importFile = ref(null)
const importFileName = ref('')
const importLoading = ref(false)
const importPreviewData = ref(null)
const importResultData = ref(null)
const previewFilter = ref('all') // 'all', 'valid', 'error'

const openImportModal = () => {
  importStep.value = 1
  importFile.value = null
  importFileName.value = ''
  importPreviewData.value = null
  importResultData.value = null
  previewFilter.value = 'all'
  showImportModal.value = true
}

const handleImportFileSelect = (e) => {
  const f = e.target.files?.[0]
  if (f) {
    importFile.value = f
    importFileName.value = f.name
  }
}

const downloadTemplate = async () => {
  try {
    const res = await $fetch(`${apiBase}/students/import/template`, {
      responseType: 'blob',
      headers: { Authorization: `Bearer ${token.value}` }
    })
    const url = window.URL.createObjectURL(new Blob([res]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'Template_Import_Siswa_MAN11.xlsx')
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (err) {
    alert('Gagal mengunduh template Excel.')
  }
}

const handlePreviewFile = async () => {
  if (!importFile.value) {
    alert('Silakan pilih file Excel/CSV terlebih dahulu.')
    return
  }

  importLoading.value = true
  try {
    const fd = new FormData()
    fd.append('file', importFile.value)

    const res = await $fetch(`${apiBase}/students/import?preview=1`, {
      method: 'POST',
      body: fd,
      headers: { Authorization: `Bearer ${token.value}` }
    })

    importPreviewData.value = res
    importStep.value = 2
  } catch (err) {
    alert(err.data?.message || 'Gagal membaca dan memvalidasi file Excel.')
  } finally {
    importLoading.value = false
  }
}

const handleExecuteImport = async () => {
  if (!importFile.value) return

  importLoading.value = true
  try {
    const fd = new FormData()
    fd.append('file', importFile.value)

    const res = await $fetch(`${apiBase}/students/import`, {
      method: 'POST',
      body: fd,
      headers: { Authorization: `Bearer ${token.value}` }
    })

    importResultData.value = res
    importStep.value = 3
    fetchStudents(1)
  } catch (err) {
    alert(err.data?.message || 'Gagal menyimpan hasil impor siswa.')
  } finally {
    importLoading.value = false
  }
}

const downloadFailedReport = async () => {
  try {
    const res = await $fetch(`${apiBase}/students/import/failed-report`, {
      responseType: 'blob',
      headers: { Authorization: `Bearer ${token.value}` }
    })
    const url = window.URL.createObjectURL(new Blob([res]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'Laporan_Gagal_Import_Siswa.xlsx')
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (err) {
    alert('Gagal mengunduh laporan gagal.')
  }
}

const filteredPreviewRows = computed(() => {
  if (!importPreviewData.value?.rows) return []
  if (previewFilter.value === 'valid') {
    return importPreviewData.value.rows.filter(r => r.status === 'valid')
  }
  if (previewFilter.value === 'error') {
    return importPreviewData.value.rows.filter(r => r.status === 'error')
  }
  return importPreviewData.value.rows
})

const getInitials = (name) => {
  return name ? name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() : 'ST'
}

// Mengatur warna badge berdasarkan nilai enum DB
const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'active':
      return 'bg-emerald-50 text-emerald-600 border border-emerald-200'
    case 'graduated':
      return 'bg-blue-50 text-blue-600 border border-blue-200'
    case 'transferred':
      return 'bg-amber-50 text-amber-600 border border-amber-200'
    default:
      return 'bg-slate-50 text-slate-600 border border-slate-200'
  }
}
</script>

<template>
  <div class="space-y-6 relative">
    
    <!-- FLOATING ANIMATED LOADING BADGE -->
    <Transition
      enter-active-class="transform transition ease-out duration-300"
      enter-from-class="translate-y-4 opacity-0 scale-95"
      enter-to-class="translate-y-0 opacity-100 scale-100"
      leave-active-class="transform transition ease-in duration-200"
      leave-from-class="translate-y-0 opacity-100 scale-100"
      leave-to-class="translate-y-4 opacity-0 scale-95"
    >
      <div 
        v-if="loading"
        class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 bg-slate-900/90 text-white rounded-2xl shadow-2xl backdrop-blur-md border border-slate-700/50"
      >
        <svg class="animate-spin h-4 w-4 text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="text-xs font-medium tracking-wide">Memuat data...</span>
      </div>
    </Transition>

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Data Siswa</h1>
        <p class="text-xs text-slate-500 mt-1">Kelola data seluruh peserta didik, sesi perangkat aktif, dan impor data massal.</p>
      </div>
      <div class="flex items-center gap-2">
        <button 
          @click="openImportModal"
          class="px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 hover:border-emerald-300 text-slate-700 rounded-xl text-xs font-semibold shadow-xs transition flex items-center justify-center gap-2 group"
        >
          <svg class="w-4 h-4 text-emerald-600 group-hover:scale-110 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
          <span>Import Siswa (Excel)</span>
        </button>
        <button 
          @click="openAddModal"
          class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center justify-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          <span>Tambah Siswa</span>
        </button>
      </div>
    </div>

    <!-- Filter & Search -->
    <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex flex-col md:flex-row gap-4 items-center justify-between">
      <div class="w-full md:w-72 relative">
        <input 
          v-model="search"
          type="text" 
          placeholder="Cari Nama, NISN, NIS, Email..." 
          class="w-full pl-9 pr-4 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
        />
        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>

      <div class="w-full md:w-auto flex items-center gap-3">
        <select 
          v-model="activeFilter"
          class="w-full md:w-44 px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 text-slate-600"
        >
          <option value="">Semua Status</option>
          <option value="active">Aktif</option>
          <option value="graduated">Lulus</option>
          <option value="transferred">Pindah</option>
        </select>
      </div>
    </div>

    <!-- Data Table Card -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50/70 border-b border-slate-100 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
              <th class="py-3.5 px-4">Siswa</th>
              <th class="py-3.5 px-4">NISN / NIS</th>
              <th class="py-3.5 px-4">Gender</th>
              <th class="py-3.5 px-4">Kontak</th>
              <th class="py-3.5 px-4">Alamat</th>
              <th class="py-3.5 px-4">Status</th>
              <th class="py-3.5 px-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
            <tr v-if="!loading && students.length === 0">
              <td colspan="7" class="py-8 text-center text-slate-400">Belum ada data siswa.</td>
            </tr>
            <tr v-for="student in students" :key="student.id" class="hover:bg-slate-50/50 transition">
              <td class="py-3.5 px-4">
                <div class="flex items-center space-x-3">
                  <img 
                    v-if="student.picture_url" 
                    :src="student.picture_url" 
                    :alt="student.name"
                    class="w-9 h-9 rounded-full object-cover border border-emerald-400"
                  />
                  <div 
                    v-else 
                    class="w-9 h-9 rounded-full bg-emerald-100 text-emerald-800 font-bold text-xs flex items-center justify-center border border-emerald-300"
                  >
                    {{ getInitials(student.name) }}
                  </div>
                  <div>
                    <div class="font-bold text-slate-800">{{ student.name }}</div>
                    <div class="text-[10px] text-slate-400">{{ student.email || '-' }}</div>
                  </div>
                </div>
              </td>
              <td class="py-3.5 px-4 font-mono">
                <div>{{ student.nisn }}</div>
                <div class="text-[10px] text-slate-400">NIS: {{ student.nis || '-' }}</div>
              </td>
              <td class="py-3.5 px-4 capitalize">
                <span :class="student.gender === 'male' ? 'text-blue-600' : 'text-pink-600'">
                  {{ student.gender }}
                </span>
              </td>
              <td class="py-3.5 px-4">{{ student.phone || '-' }}</td>
              <td class="py-3.5 px-4 max-w-xs truncate">{{ student.address || '-' }}</td>
              <td class="py-3.5 px-4">
               <span 
                    class="px-2.5 py-1 text-[10px] font-semibold rounded-full capitalize"
                    :class="getStatusBadgeClass(student.is_active)"
                  >
                    {{student.is_active||'-'}}
                  </span>
              </td>
              <td class="py-3.5 px-4 text-right">
                <div class="flex items-center justify-end space-x-1.5">
                  <button 
                    @click="handleResetSession(student)" 
                    :disabled="resettingSessionId === student.id"
                    class="p-1.5 hover:bg-amber-50 text-slate-500 hover:text-amber-600 rounded-lg transition disabled:opacity-50"
                    title="Reset Sesi Perangkat (Izinkan Siswa Login di Device Baru)"
                  >
                    <svg class="w-4 h-4" :class="{ 'animate-spin': resettingSessionId === student.id }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                  </button>
                  <button 
                    @click="openEditModal(student)" 
                    class="p-1.5 hover:bg-slate-100 text-slate-600 hover:text-emerald-600 rounded-lg transition"
                    title="Edit"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  <button 
                    @click="handleDelete(student.id)" 
                    class="p-1.5 hover:bg-rose-50 text-slate-600 hover:text-rose-600 rounded-lg transition"
                    title="Hapus"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
        <span>Total: {{ pagination.total }} Siswa</span>
        <div class="flex items-center space-x-1">
          <button 
            :disabled="pagination.current_page === 1"
            @click="fetchStudents(pagination.current_page - 1)"
            class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 transition"
          >
            Prev
          </button>
          <span class="px-3 py-1.5 font-semibold text-slate-700">
            {{ pagination.current_page }} / {{ pagination.last_page }}
          </span>
          <button 
            :disabled="pagination.current_page === pagination.last_page"
            @click="fetchStudents(pagination.current_page + 1)"
            class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 transition"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL FORM -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4 overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden my-8">
        
        <div class="px-6 py-4 bg-emerald-700 text-white flex justify-between items-center">
          <h3 class="text-sm font-bold">{{ isEditing ? 'Edit Data Siswa' : 'Tambah Siswa Baru' }}</h3>
          <button @click="showModal = false" class="text-emerald-100 hover:text-white">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            <!-- NISN -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">NISN *</label>
              <input v-model="form.nisn" type="text" maxlength="10" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none" />
              <span v-if="errors.nisn" class="text-[10px] text-rose-500">{{ errors.nisn[0] }}</span>
            </div>

            <!-- NIS -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">NIS</label>
              <input v-model="form.nis" type="text" maxlength="20" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none" />
              <span v-if="errors.nis" class="text-[10px] text-rose-500">{{ errors.nis[0] }}</span>
            </div>

            <!-- Name -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap *</label>
              <input v-model="form.name" type="text" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none" />
              <span v-if="errors.name" class="text-[10px] text-rose-500">{{ errors.name[0] }}</span>
            </div>

            <!-- Email -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Email</label>
              <input v-model="form.email" type="email" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none" />
              <span v-if="errors.email" class="text-[10px] text-rose-500">{{ errors.email[0] }}</span>
            </div>

            <!-- Gender -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Kelamin *</label>
              <select v-model="form.gender" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                <option value="male">Male (Laki-laki)</option>
                <option value="female">Female (Perempuan)</option>
              </select>
            </div>

            <!-- Phone -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">No. HP / WhatsApp</label>
              <input v-model="form.phone" type="text" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none" />
            </div>

            <!-- Status Active -->
            <div class="md:col-span-2">
              <label class="block text-xs font-semibold text-slate-700 mb-1">Status Keaktifan *</label>
              <select v-model="form.is_active" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                <option value="active">Active</option>
                <option value="graduated">Graduated</option>
                <option value="transferred">Transferred</option>
              </select>
            </div>

            <!-- Address -->
            <div class="md:col-span-2">
              <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Lengkap</label>
              <textarea v-model="form.address" rows="2" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
            </div>

            <!-- Upload Picture -->
            <div class="md:col-span-2">
              <label class="block text-xs font-semibold text-slate-700 mb-1">Foto Profil (Picture)</label>
              <div class="flex items-center space-x-4">
                <img 
                  v-if="picturePreview" 
                  :src="picturePreview" 
                  class="w-12 h-12 rounded-full object-cover border border-emerald-500"
                />
                <input 
                  type="file" 
                  accept="image/*" 
                  @change="handleFileUpload" 
                  class="text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
                />
              </div>
              <span v-if="errors.picture" class="text-[10px] text-rose-500">{{ errors.picture[0] }}</span>
            </div>

          </div>

          <div class="pt-4 border-t border-slate-100 flex justify-end space-x-3">
            <button 
              type="button" 
              @click="showModal = false"
              class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-50 transition"
            >
              Batal
            </button>
            <button 
              type="submit"
              class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition"
            >
              {{ isEditing ? 'Simpan Perubahan' : 'Tambah Data' }}
            </button>
          </div>
        </form>

      </div>
    </div>

    <!-- MODAL IMPORT SISWA EXCEL (B8 & B9) -->
    <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4 overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden my-8 border border-slate-100 transition-all">
        
        <!-- Modal Header -->
        <div class="px-6 py-4 bg-emerald-700 text-white flex justify-between items-center">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-emerald-600 flex items-center justify-center">
              <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
            </div>
            <div>
              <h3 class="text-sm font-bold">Import Siswa Massal via Excel</h3>
              <p class="text-[11px] text-emerald-100">Gunakan template standar sekolah untuk pendaftaran idempoten</p>
            </div>
          </div>
          <button @click="showImportModal = false" class="text-emerald-100 hover:text-white p-1 rounded-lg hover:bg-emerald-600/50 transition">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Step Indicator -->
        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200/80 flex items-center justify-between text-xs">
          <div class="flex items-center gap-2" :class="importStep === 1 ? 'font-bold text-emerald-700' : 'text-slate-500'">
            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]" :class="importStep === 1 ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600'">1</span>
            <span>Unggah & Template</span>
          </div>
          <div class="w-8 h-px bg-slate-300"></div>
          <div class="flex items-center gap-2" :class="importStep === 2 ? 'font-bold text-emerald-700' : 'text-slate-500'">
            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]" :class="importStep === 2 ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600'">2</span>
            <span>Pratinjau & Validasi Baris</span>
          </div>
          <div class="w-8 h-px bg-slate-300"></div>
          <div class="flex items-center gap-2" :class="importStep === 3 ? 'font-bold text-emerald-700' : 'text-slate-500'">
            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]" :class="importStep === 3 ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600'">3</span>
            <span>Hasil & Laporan</span>
          </div>
        </div>

        <!-- STEP 1: UPLOAD & TEMPLATE -->
        <div v-if="importStep === 1" class="p-6 space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Box Panduan & Unduh Template -->
            <div class="bg-emerald-50/70 border border-emerald-200/80 rounded-xl p-4 space-y-3">
              <div class="flex items-center gap-2 text-emerald-800 font-bold text-xs">
                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Format Template Sekolah</span>
              </div>
              <p class="text-[11px] text-emerald-900 leading-relaxed">
                File Excel harus menyertakan kolom: <b>NIS, NISN, Nama Lengkap, Jenis Kelamin (L/P), Tempat & Tgl Lahir, Email, No HP, Alamat, Kelas</b>.
              </p>
              <ul class="text-[11px] text-emerald-800 list-disc list-inside space-y-1">
                <li>Akun User otomatis dibuat (Username: NIS).</li>
                <li>Kata sandi awal aman tanpa dicatat di log publik.</li>
                <li>Impor bersifat idempoten (NIS sama akan diupdate).</li>
              </ul>
              <button 
                type="button"
                @click="downloadTemplate"
                class="w-full mt-2 py-2 px-3 bg-white hover:bg-emerald-100 border border-emerald-300 text-emerald-700 rounded-lg text-xs font-semibold flex items-center justify-center gap-2 shadow-xs transition"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Unduh Template Excel (.xlsx)</span>
              </button>
            </div>

            <!-- Box Upload File -->
            <div class="border-2 border-dashed border-slate-200 hover:border-emerald-400 rounded-xl p-6 flex flex-col items-center justify-center text-center transition bg-slate-50/50">
              <svg class="w-10 h-10 text-slate-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              <label class="cursor-pointer">
                <span class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-xs transition inline-block">
                  Pilih File Excel / CSV
                </span>
                <input 
                  type="file" 
                  accept=".xlsx,.xls,.csv" 
                  class="hidden" 
                  @change="handleImportFileSelect" 
                />
              </label>
              <p class="text-[11px] text-slate-500 mt-2">Mendukung format .xlsx, .xls, .csv (Maksimal 10MB)</p>
              <div v-if="importFileName" class="mt-3 px-3 py-1.5 bg-emerald-100/70 text-emerald-800 rounded-lg text-xs font-medium flex items-center gap-1.5 border border-emerald-200">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="truncate max-w-[200px]">{{ importFileName }}</span>
              </div>
            </div>
          </div>

          <div class="pt-4 border-t border-slate-100 flex justify-end gap-2">
            <button 
              type="button" 
              @click="showImportModal = false"
              class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-50 transition"
            >
              Batal
            </button>
            <button 
              type="button" 
              :disabled="!importFile || importLoading"
              @click="handlePreviewFile"
              class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-xl text-xs font-semibold shadow-xs transition flex items-center gap-2"
            >
              <svg v-if="importLoading" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ importLoading ? 'Memvalidasi File...' : 'Lanjut: Pratinjau Data' }}</span>
            </button>
          </div>
        </div>

        <!-- STEP 2: PRATINJAU & VALIDASI -->
        <div v-else-if="importStep === 2" class="p-6 space-y-4">
          <!-- Metric Summary Cards -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
              <div class="text-slate-500 text-[11px]">Total Baris</div>
              <div class="text-lg font-bold text-slate-800">{{ importPreviewData?.summary?.total || 0 }}</div>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3">
              <div class="text-emerald-700 text-[11px]">Siap Diimpor (Valid)</div>
              <div class="text-lg font-bold text-emerald-700">{{ importPreviewData?.summary?.valid || 0 }}</div>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3">
              <div class="text-amber-700 text-[11px]">NIS Duplikat File</div>
              <div class="text-lg font-bold text-amber-700">{{ importPreviewData?.summary?.duplicates || 0 }}</div>
            </div>
            <div class="bg-rose-50 border border-rose-200 rounded-xl p-3">
              <div class="text-rose-700 text-[11px]">Baris Bermasalah</div>
              <div class="text-lg font-bold text-rose-700">{{ importPreviewData?.summary?.errors || 0 }}</div>
            </div>
          </div>

          <!-- Filter Tabs -->
          <div class="flex items-center justify-between pt-1">
            <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl text-xs">
              <button 
                type="button" 
                @click="previewFilter = 'all'" 
                :class="previewFilter === 'all' ? 'bg-white shadow-xs font-semibold text-slate-800' : 'text-slate-500 hover:text-slate-700'"
                class="px-3 py-1 rounded-lg transition"
              >
                Semua Baris ({{ importPreviewData?.summary?.total || 0 }})
              </button>
              <button 
                type="button" 
                @click="previewFilter = 'valid'" 
                :class="previewFilter === 'valid' ? 'bg-white shadow-xs font-semibold text-emerald-700' : 'text-slate-500 hover:text-emerald-700'"
                class="px-3 py-1 rounded-lg transition"
              >
                Hanya Valid ({{ importPreviewData?.summary?.valid || 0 }})
              </button>
              <button 
                type="button" 
                @click="previewFilter = 'error'" 
                :class="previewFilter === 'error' ? 'bg-white shadow-xs font-semibold text-rose-700' : 'text-slate-500 hover:text-rose-700'"
                class="px-3 py-1 rounded-lg transition"
              >
                Bermasalah ({{ importPreviewData?.summary?.errors || 0 }})
              </button>
            </div>
            <span class="text-[11px] text-slate-400">Menampilkan {{ filteredPreviewRows.length }} baris</span>
          </div>

          <!-- Preview Table -->
          <div class="border border-slate-200 rounded-xl overflow-hidden max-h-72 overflow-y-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 sticky top-0 z-10 text-[11px]">
                <tr>
                  <th class="py-2.5 px-3 w-12 text-center">Baris</th>
                  <th class="py-2.5 px-3">NIS</th>
                  <th class="py-2.5 px-3">Nama Lengkap</th>
                  <th class="py-2.5 px-3">L/P</th>
                  <th class="py-2.5 px-3">Kelas Rombel</th>
                  <th class="py-2.5 px-3">Status</th>
                  <th class="py-2.5 px-3">Keterangan / Validasi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr 
                  v-for="row in filteredPreviewRows" 
                  :key="row.row_number" 
                  :class="row.status === 'error' ? 'bg-rose-50/60' : 'hover:bg-slate-50/60'"
                  class="transition"
                >
                  <td class="py-2 px-3 text-center text-slate-500 font-mono text-[11px]">{{ row.row_number }}</td>
                  <td class="py-2 px-3 font-semibold text-slate-800 font-mono text-[11px]">{{ row.nis || '-' }}</td>
                  <td class="py-2 px-3 text-slate-800">{{ row.nama || '-' }}</td>
                  <td class="py-2 px-3 text-slate-600 uppercase">{{ row.gender === 'male' ? 'L' : (row.gender === 'female' ? 'P' : row.gender) }}</td>
                  <td class="py-2 px-3 text-slate-700 font-medium">{{ row.kelas || '-' }}</td>
                  <td class="py-2 px-3">
                    <span 
                      class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                      :class="row.status === 'valid' ? (row.is_update ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700') : 'bg-rose-100 text-rose-700'"
                    >
                      {{ row.status === 'valid' ? (row.is_update ? 'Update' : 'Baru') : 'Error' }}
                    </span>
                  </td>
                  <td class="py-2 px-3">
                    <div v-if="row.errors && row.errors.length > 0" class="space-y-0.5">
                      <span v-for="(err, idx) in row.errors" :key="idx" class="inline-block px-1.5 py-0.5 bg-rose-100/80 text-rose-800 text-[10px] rounded mr-1">
                        {{ err }}
                      </span>
                    </div>
                    <span v-else class="text-emerald-600 text-[10px] flex items-center gap-1">
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                      </svg>
                      Siap diproses
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Bottom Actions -->
          <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
            <button 
              type="button" 
              @click="importStep = 1"
              class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-50 transition"
            >
              ← Ganti File
            </button>
            <div class="flex items-center gap-2">
              <button 
                type="button" 
                @click="showImportModal = false"
                class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-50 transition"
              >
                Batal
              </button>
              <button 
                type="button" 
                :disabled="importLoading || (importPreviewData?.summary?.valid || 0) === 0"
                @click="handleExecuteImport"
                class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-xl text-xs font-semibold shadow-xs transition flex items-center gap-2"
              >
                <svg v-if="importLoading" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ importLoading ? 'Menyimpan...' : `Konfirmasi & Simpan Impor (${importPreviewData?.summary?.valid || 0} Siswa)` }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- STEP 3: HASIL IMPOR & LAPORAN -->
        <div v-else-if="importStep === 3" class="p-8 text-center space-y-5">
          <div class="w-14 h-14 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>

          <div>
            <h4 class="text-base font-bold text-slate-800">Proses Impor Siswa Selesai!</h4>
            <p class="text-xs text-slate-500 mt-1">Data siswa dan akun user terkait telah diperbarui di sistem secara idempoten.</p>
          </div>

          <div class="max-w-md mx-auto grid grid-cols-2 gap-3 text-xs bg-slate-50 p-4 rounded-xl border border-slate-200">
            <div>
              <span class="text-slate-500 block text-[11px]">Siswa Baru Dibuat:</span>
              <span class="text-base font-bold text-emerald-600">{{ importResultData?.summary?.created || 0 }}</span>
            </div>
            <div>
              <span class="text-slate-500 block text-[11px]">Siswa Diperbarui:</span>
              <span class="text-base font-bold text-blue-600">{{ importResultData?.summary?.updated || 0 }}</span>
            </div>
          </div>

          <!-- Laporan Gagal Baris Jika Ada -->
          <div v-if="(importResultData?.summary?.errors || 0) > 0 || importResultData?.has_failed_report" class="max-w-md mx-auto p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 flex items-center justify-between">
            <div class="text-left">
              <span class="font-bold block">Ada {{ importResultData?.summary?.errors }} Baris yang Dilewati</span>
              <span class="text-[11px] text-rose-700">Unduh laporan detail untuk memperbaiki data siswa yang bermasalah.</span>
            </div>
            <button 
              type="button" 
              @click="downloadFailedReport"
              class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-semibold shadow-xs transition shrink-0 ml-3"
            >
              Unduh Laporan (.xlsx)
            </button>
          </div>

          <div class="pt-4 border-t border-slate-100">
            <button 
              type="button" 
              @click="showImportModal = false"
              class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-xs transition"
            >
              Selesai & Muat Ulang Siswa
            </button>
          </div>
        </div>

      </div>
    </div>

  </div>
</template>