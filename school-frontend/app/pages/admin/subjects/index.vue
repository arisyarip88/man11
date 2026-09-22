<script setup>
import { ref, reactive, onMounted, watch } from 'vue'

definePageMeta({
  layout: 'admin'
})

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie('auth_token')

const subjectsList = ref([])
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const loading = ref(false)

const showModal = ref(false)
const isEditing = ref(false)
const currentId = ref(null)

const form = reactive({
  code: '',
  name: '',
  description: ''
})

const errors = ref({})

const fetchSubjects = async (page = 1) => {
  loading.value = true
  try {
    const res = await $fetch(`${apiBase}/subjects`, {
      params: {
        page,
        search: search.value
      },
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })

    subjectsList.value = res.data || []
    pagination.value = {
      current_page: res.current_page || 1,
      last_page: res.last_page || 1,
      total: res.total || 0
    }
  } catch (err) {
    console.error('Fetch subjects error:', err)
  } finally {
    loading.value = false
  }
}

watch(search, () => {
  fetchSubjects(1)
})

onMounted(() => {
  fetchSubjects()
})

const resetForm = () => {
  Object.assign(form, {
    code: '',
    name: '',
    description: ''
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
    description: item.description || ''
  })

  showModal.value = true
}

const handleSubmit = async () => {
  errors.value = {}
  loading.value = true

  const endpoint = isEditing.value
    ? `${apiBase}/subjects/${currentId.value}`
    : `${apiBase}/subjects`

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
    fetchSubjects(pagination.value.current_page)
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
  if (!confirm('Apakah Anda yakin ingin menghapus mata pelajaran ini?')) return

  try {
    await $fetch(`${apiBase}/subjects/${id}`, {
      method: 'DELETE',
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })
    fetchSubjects(pagination.value.current_page)
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
        <h1 class="text-2xl font-bold text-gray-900">Manajemen Mata Pelajaran</h1>
        <p class="text-sm text-gray-500">Kelola master data mata pelajaran (Subject) sekolah</p>
      </div>
      <button 
        @click="openAddModal"
        class="inline-flex items-center justify-center px-4 py-2.5 bg-emerald-600 text-white font-medium text-sm rounded-xl hover:bg-emerald-700 transition shadow-sm gap-2"
      >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Tambah Mata Pelajaran
      </button>
    </div>

    <!-- Filter & Search Card -->
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex flex-col sm:flex-row gap-4 justify-between items-center">
      <div class="relative w-full sm:w-80">
        <input 
          v-model="search"
          type="text" 
          placeholder="Cari kode atau nama mapel..."
          class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
        >
        <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
      <div class="text-xs text-gray-500">
        Total: <span class="font-bold text-gray-700">{{ pagination.total }}</span> Mata Pelajaran
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
              <th class="py-4 px-6">Kode Mapel</th>
              <th class="py-4 px-6">Nama Mata Pelajaran</th>
              <th class="py-4 px-6">Deskripsi</th>
              <th class="py-4 px-6 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
            <tr v-if="loading && subjectsList.length === 0">
              <td colspan="4" class="py-8 text-center text-gray-400">Memuat data mata pelajaran...</td>
            </tr>
            <tr v-else-if="subjectsList.length === 0">
              <td colspan="4" class="py-8 text-center text-gray-400">Tidak ada data mata pelajaran ditemukan.</td>
            </tr>
            <tr v-for="item in subjectsList" :key="item.id" class="hover:bg-gray-50/50 transition">
              <td class="py-4 px-6 font-mono font-bold text-emerald-700">{{ item.code }}</td>
              <td class="py-4 px-6 font-semibold text-gray-900">{{ item.name }}</td>
              <td class="py-4 px-6 text-gray-500 max-w-xs truncate">{{ item.description || '-' }}</td>
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

      <!-- Pagination Footer -->
      <div class="p-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
        <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
        <div class="flex gap-2">
          <button 
            :disabled="pagination.current_page <= 1"
            @click="fetchSubjects(pagination.current_page - 1)"
            class="px-3 py-1.5 border border-gray-200 rounded-lg disabled:opacity-40 hover:bg-gray-50 transition"
          >
            Sebelumnya
          </button>
          <button 
            :disabled="pagination.current_page >= pagination.last_page"
            @click="fetchSubjects(pagination.current_page + 1)"
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
            {{ isEditing ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran Baru' }}
          </h3>
          <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Kode Mapel *</label>
            <input 
              v-model="form.code"
              type="text" 
              placeholder="Contoh: MAT-X, BIO-XI"
              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
              required
            >
            <p v-if="errors.code" class="text-xs text-rose-500 mt-1">{{ errors.code[0] }}</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Mata Pelajaran *</label>
            <input 
              v-model="form.name"
              type="text" 
              placeholder="Contoh: Matematika Wajib"
              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
              required
            >
            <p v-if="errors.name" class="text-xs text-rose-500 mt-1">{{ errors.name[0] }}</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Deskripsi</label>
            <textarea 
              v-model="form.description"
              rows="3"
              placeholder="Keterangan mengenai mata pelajaran..."
              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
            ></textarea>
            <p v-if="errors.description" class="text-xs text-rose-500 mt-1">{{ errors.description[0] }}</p>
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
