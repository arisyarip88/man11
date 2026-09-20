<script setup>
import { ref, reactive, onMounted, watch } from 'vue'

definePageMeta({
  layout: 'admin'
})

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie('auth_token')

const majors = ref([])
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const activeFilter = ref('')
const loading = ref(false)

const showModal = ref(false)
const isEditing = ref(false)
const currentId = ref(null)

const form = reactive({
  code: '',
  name: '',
  description: '',
  is_active: 1
})

const errors = ref({})

const fetchMajors = async (page = 1) => {
  loading.value = true
  try {
    const res = await $fetch(`${apiBase}/majors`, {
      params: {
        page,
        search: search.value,
        is_active: activeFilter.value
      },
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })

    majors.value = res.data || []
    pagination.value = {
      current_page: res.current_page || 1,
      last_page: res.last_page || 1,
      total: res.total || 0
    }
  } catch (err) {
    console.error('Fetch error:', err)
  } finally {
    loading.value = false
  }
}

watch([search, activeFilter], () => {
  fetchMajors(1)
})

onMounted(() => {
  fetchMajors()
})

const resetForm = () => {
  Object.assign(form, {
    code: '',
    name: '',
    description: '',
    is_active: 1
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
    code: item.code || '',
    name: item.name || '',
    description: item.description || '',
    is_active: item.is_active ? 1 : 0
  })

  showModal.value = true
}

const handleSubmit = async () => {
  errors.value = {}
  loading.value = true

  const endpoint = isEditing.value 
    ? `${apiBase}/majors/${currentId.value}`
    : `${apiBase}/majors`

  const method = isEditing.value ? 'PUT' : 'POST'

  try {
    await $fetch(endpoint, {
      method,
      body: form,
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })

    showModal.value = false
    await fetchMajors(pagination.value.current_page)
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
  if (!confirm('Apakah Anda yakin ingin menghapus jurusan ini?')) return

  try {
    await $fetch(`${apiBase}/majors/${id}`, {
      method: 'DELETE',
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })
    fetchMajors(pagination.value.current_page)
  } catch (err) {
    alert('Gagal menghapus data')
  }
}
</script>

<template>
  <div class="space-y-6 relative">
    
    <!-- Loading Badge -->
    <Transition
      enter-active-class="transform transition ease-out duration-300"
      enter-from-class="translate-y-4 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transform transition ease-in duration-200"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-4 opacity-0"
    >
      <div 
        v-if="loading"
        class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 bg-slate-900/90 text-white rounded-2xl shadow-2xl backdrop-blur-md border border-slate-700/50"
      >
        <svg class="animate-spin h-4 w-4 text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="text-xs font-medium">Memuat data...</span>
      </div>
    </Transition>

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Data Jurusan</h1>
        <p class="text-xs text-slate-500 mt-1">Kelola data program studi atau jurusan.</p>
      </div>
      <button 
        @click="openAddModal"
        class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center justify-center gap-2"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        <span>Tambah Jurusan</span>
      </button>
    </div>

    <!-- Filter & Search -->
    <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex flex-col md:flex-row gap-4 items-center justify-between">
      <div class="w-full md:w-72 relative">
        <input 
          v-model="search"
          type="text" 
          placeholder="Cari Kode atau Nama Jurusan..." 
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
          <option value="0">Tidak Aktif</option>
        </select>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50/70 border-b border-slate-100 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
              <th class="py-3.5 px-4">Kode</th>
              <th class="py-3.5 px-4">Nama Jurusan</th>
              <th class="py-3.5 px-4">Deskripsi</th>
              <th class="py-3.5 px-4">Status</th>
              <th class="py-3.5 px-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
            <tr v-if="!loading && majors.length === 0">
              <td colspan="5" class="py-8 text-center text-slate-400">Belum ada data jurusan.</td>
            </tr>
            <tr v-for="item in majors" :key="item.id" class="hover:bg-slate-50/50 transition">
              <td class="py-3.5 px-4 font-bold text-emerald-600">{{ item.code }}</td>
              <td class="py-3.5 px-4 font-semibold text-slate-800">{{ item.name }}</td>
              <td class="py-3.5 px-4 text-slate-500 truncate max-w-xs">{{ item.description || '-' }}</td>
              <td class="py-3.5 px-4 whitespace-nowrap">
                <span 
                  class="inline-flex items-center justify-center px-3 py-1 text-[11px] font-semibold rounded-full"
                  :class="item.is_active ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200'"
                >
                  {{ item.is_active ? 'Aktif' : 'Tidak Aktif' }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-right">
                <div class="flex items-center justify-end space-x-2">
                  <button 
                    @click="openEditModal(item)" 
                    class="p-1.5 hover:bg-slate-100 text-slate-600 hover:text-emerald-600 rounded-lg transition"
                    title="Edit"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  <button 
                    @click="handleDelete(item.id)" 
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

      <!-- Pagination -->
      <div class="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
        <span>Total: {{ pagination.total }} Jurusan</span>
        <div class="flex items-center space-x-1">
          <button 
            :disabled="pagination.current_page === 1"
            @click="fetchMajors(pagination.current_page - 1)"
            class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 transition"
          >
            Prev
          </button>
          <span class="px-3 py-1.5 font-semibold text-slate-700">
            {{ pagination.current_page }} / {{ pagination.last_page }}
          </span>
          <button 
            :disabled="pagination.current_page === pagination.last_page"
            @click="fetchMajors(pagination.current_page + 1)"
            class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 transition"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4 overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden my-8">
        
        <div class="px-6 py-4 bg-emerald-700 text-white flex justify-between items-center">
          <h3 class="text-sm font-bold">{{ isEditing ? 'Edit Jurusan' : 'Tambah Jurusan' }}</h3>
          <button @click="showModal = false" class="text-emerald-100 hover:text-white">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Kode Jurusan *</label>
            <input v-model="form.code" type="text" placeholder="Contoh: RPL, TKJ, AK" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none" />
            <span v-if="errors.code" class="text-[10px] text-rose-500">{{ errors.code[0] }}</span>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Jurusan *</label>
            <input v-model="form.name" type="text" placeholder="Contoh: Rekayasa Perangkat Lunak" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none" />
            <span v-if="errors.name" class="text-[10px] text-rose-500">{{ errors.name[0] }}</span>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Deskripsi</label>
            <textarea v-model="form.description" rows="3" placeholder="Deskripsi singkat jurusan..." class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
            <span v-if="errors.description" class="text-[10px] text-rose-500">{{ errors.description[0] }}</span>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Status Keaktifan *</label>
            <select v-model="form.is_active" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
              <option :value="1">Aktif</option>
              <option :value="0">Tidak Aktif</option>
            </select>
            <span v-if="errors.is_active" class="text-[10px] text-rose-500">{{ errors.is_active[0] }}</span>
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