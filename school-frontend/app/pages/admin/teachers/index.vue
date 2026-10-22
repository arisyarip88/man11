<script setup>
import { ref, reactive, onMounted, watch } from 'vue'

definePageMeta({
  layout: 'admin'
})

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie('auth_token')

const teachers = ref([])
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const activeFilter = ref('')
const loading = ref(false)

const showModal = ref(false)
const isEditing = ref(false)
const currentId = ref(null)
const picturePreview = ref(null)
const pictureFile = ref(null)

// Added birth_date to reactive form
const form = reactive({
  nip: '',
  nik: '',
  name: '',
  gender: 'male',
  birth_date: '',
  email: '',
  phone: '',
  address: '',
  is_active: 1
})

const errors = ref({})

const fetchTeachers = async (page = 1) => {
  loading.value = true
  try {
    const res = await $fetch(`${apiBase}/teachers`, {
      params: {
        page,
        search: search.value,
        is_active: activeFilter.value
      },
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })

    teachers.value = res.data || (Array.isArray(res) ? res : [])
    
    pagination.value = {
      current_page: res.current_page || 1,
      last_page: res.last_page || 1,
      total: res.total || teachers.value.length
    }
  } catch (err) {
    console.error('Fetch error:', err)
  } finally {
    loading.value = false
  }
}

watch([search, activeFilter], () => {
  fetchTeachers(1)
})

onMounted(() => {
  fetchTeachers()
})

const openAddModal = () => {
  isEditing.value = false
  currentId.value = null
  resetForm()
  showModal.value = true
}

const openEditModal = (teacher) => {
  isEditing.value = true
  currentId.value = teacher.id
  resetForm()
  
  Object.assign(form, {
    nip: teacher.nip || '',
    nik: teacher.nik || '',
    name: teacher.name || '',
    gender: teacher.gender || 'male',
    birth_date: teacher.birth_date || '',
    email: teacher.email || '',
    phone: teacher.phone || '',
    address: teacher.address || '',
    is_active: teacher.is_active ? 1 : 0
  })
  
  picturePreview.value = teacher.picture_url
  showModal.value = true
}

const resetForm = () => {
  Object.assign(form, {
    nip: '',
    nik: '',
    name: '',
    gender: 'male',
    birth_date: '',
    email: '',
    phone: '',
    address: '',
    is_active: 1
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
    ? `${apiBase}/teachers/${currentId.value}`
    : `${apiBase}/teachers`

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
    await fetchTeachers(1)
  } catch (err) {
    if (err.data && err.data.errors) {
      errors.value = err.data.errors
    } else {
      alert('Terjadi kesalahan saat menyimpan data.')
    }
  }
}

const handleDelete = async (id) => {
  if (!confirm('Apakah Anda yakin ingin menghapus data guru ini?')) return

  try {
    await $fetch(`${apiBase}/teachers/${id}`, {
      method: 'DELETE',
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })
    fetchTeachers(pagination.value.current_page)
  } catch (err) {
    alert('Gagal menghapus data')
  }
}

const getInitials = (name) => {
  return name ? name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() : 'TC'
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
        <h1 class="text-2xl font-bold text-slate-800">Data Guru</h1>
        <p class="text-xs text-slate-500 mt-1">Kelola data tenaga pendidik sekolah.</p>
      </div>
      <button 
        @click="openAddModal"
        class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center justify-center gap-2"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        <span>Tambah Guru</span>
      </button>
    </div>

    <!-- Filter & Search -->
    <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex flex-col md:flex-row gap-4 items-center justify-between">
      <div class="w-full md:w-72 relative">
        <input 
          v-model="search"
          type="text" 
          placeholder="Cari Nama, NIP, NIK, Email..." 
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
          <option value="1">Aktif</option>
          <option value="0">Non-Aktif</option>
        </select>
      </div>
    </div>

    <!-- Data Table Card -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50/70 border-b border-slate-100 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
              <th class="py-3.5 px-4">Guru</th>
              <th class="py-3.5 px-4">NIP / NIK</th>
              <th class="py-3.5 px-4">Gender</th>
              <th class="py-3.5 px-4">Tgl Lahir</th>
              <th class="py-3.5 px-4">Kontak</th>
              <th class="py-3.5 px-4">Alamat</th>
              <th class="py-3.5 px-4">Status</th>
              <th class="py-3.5 px-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
            <tr v-if="!loading && teachers.length === 0">
              <td colspan="8" class="py-8 text-center text-slate-400">Belum ada data guru.</td>
            </tr>
            <tr v-for="teacher in teachers" :key="teacher.id" class="hover:bg-slate-50/50 transition">
              <td class="py-3.5 px-4">
                <div class="flex items-center space-x-3">
                  <img 
                    v-if="teacher.picture_url" 
                    :src="teacher.picture_url" 
                    :alt="teacher.name"
                    class="w-9 h-9 rounded-full object-cover border border-emerald-400"
                  />
                  <div 
                    v-else 
                    class="w-9 h-9 rounded-full bg-emerald-100 text-emerald-800 font-bold text-xs flex items-center justify-center border border-emerald-300"
                  >
                    {{ getInitials(teacher.name) }}
                  </div>
                  <div>
                    <div class="font-bold text-slate-800">{{ teacher.name }}</div>
                    <div class="text-[10px] text-slate-400">{{ teacher.email }}</div>
                  </div>
                </div>
              </td>
              <td class="py-3.5 px-4 font-mono">
                <div>{{ teacher.nip }}</div>
                <div class="text-[10px] text-slate-400">NIK: {{ teacher.nik || '-' }}</div>
              </td>
              <td class="py-3.5 px-4 capitalize">
                <span :class="teacher.gender === 'male' ? 'text-blue-600' : 'text-pink-600'">
                  {{ teacher.gender }}
                </span>
              </td>
              <td class="py-3.5 px-4">{{ teacher.birth_date || '-' }}</td>
              <td class="py-3.5 px-4">{{ teacher.phone || '-' }}</td>
              <td class="py-3.5 px-4 max-w-xs truncate">{{ teacher.address || '-' }}</td>
              <td class="py-3.5 px-4">
                <span 
                  class="px-2.5 py-1 text-[10px] font-semibold rounded-full capitalize"
                  :class="teacher.is_active ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200'"
                >
                  {{ teacher.is_active ? 'Aktif' : 'Non-Aktif' }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-right">
                <div class="flex items-center justify-end space-x-2">
                  <button 
                    @click="openEditModal(teacher)" 
                    class="p-1.5 hover:bg-slate-100 text-slate-600 hover:text-emerald-600 rounded-lg transition"
                    title="Edit"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  <button 
                    @click="handleDelete(teacher.id)" 
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
        <span>Total: {{ pagination.total }} Guru</span>
        <div class="flex items-center space-x-1">
          <button 
            :disabled="pagination.current_page === 1"
            @click="fetchTeachers(pagination.current_page - 1)"
            class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 transition"
          >
            Prev
          </button>
          <span class="px-3 py-1.5 font-semibold text-slate-700">
            {{ pagination.current_page }} / {{ pagination.last_page }}
          </span>
          <button 
            :disabled="pagination.current_page === pagination.last_page"
            @click="fetchTeachers(pagination.current_page + 1)"
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
          <h3 class="text-sm font-bold">{{ isEditing ? 'Edit Data Guru' : 'Tambah Guru Baru' }}</h3>
          <button @click="showModal = false" class="text-emerald-100 hover:text-white">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            <!-- NIP -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">NIP *</label>
              <input v-model="form.nip" type="text" maxlength="20" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none" />
              <span v-if="errors.nip" class="text-[10px] text-rose-500">{{ errors.nip[0] }}</span>
            </div>

            <!-- NIK -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">NIK</label>
              <input v-model="form.nik" type="text" maxlength="16" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none" />
              <span v-if="errors.nik" class="text-[10px] text-rose-500">{{ errors.nik[0] }}</span>
            </div>

            <!-- Name -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap *</label>
              <input v-model="form.name" type="text" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none" />
              <span v-if="errors.name" class="text-[10px] text-rose-500">{{ errors.name[0] }}</span>
            </div>

            <!-- Email -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Email *</label>
              <input v-model="form.email" type="email" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none" />
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

            <!-- Birth Date (Tanggal Lahir) -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Lahir</label>
              <input v-model="form.birth_date" type="date" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none" />
              <span v-if="errors.birth_date" class="text-[10px] text-rose-500">{{ errors.birth_date[0] }}</span>
            </div>

            <!-- Phone -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">No. HP / WhatsApp</label>
              <input v-model="form.phone" type="text" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none" />
            </div>

            <!-- Status Active -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Status Keaktifan *</label>
              <select v-model="form.is_active" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                <option :value="1">Aktif</option>
                <option :value="0">Non-Aktif</option>
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

  </div>
</template>