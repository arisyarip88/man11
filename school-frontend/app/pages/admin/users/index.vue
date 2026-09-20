<script setup>
import { ref, reactive, onMounted, watch } from 'vue'

definePageMeta({
  layout: 'admin'
})

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'

// Auth Cookie & Helper Header
const tokenCookie = useCookie('auth_token')
const getToken = () => tokenCookie.value || (process.client ? localStorage.getItem('auth_token') : '')
const getHeaders = () => ({
  Authorization: `Bearer ${getToken()}`,
  Accept: 'application/json'
})

// States
const users = ref([])
const roles = ref([])
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const roleFilter = ref('')
const loading = ref(false)
const errorMessage = ref('')

const showModal = ref(false)
const isEditing = ref(false)
const currentId = ref(null)

// Form State (Sesuai dengan kolom tabel)
const form = reactive({
  role_id: 3,
  username: '',
  email: '',
  password: ''
})

const errors = ref({})

// Fetch Roles
const fetchRoles = async () => {
  try {
    const res = await $fetch(`${apiBase}/roles`, { headers: getHeaders() })
    roles.value = Array.isArray(res) ? res : (res.data || [])
    if (roles.value.length > 0 && !isEditing.value) {
      form.role_id = roles.value[0].id
    }
  } catch (err) {
    console.error('Gagal mengambil data roles:', err)
  }
}

// Fetch Users (Index dengan Search, Filter, & Pagination)
const fetchUsers = async (page = 1) => {
  loading.value = true
  errorMessage.value = ''
  try {
    const res = await $fetch(`${apiBase}/users`, {
      params: { page, search: search.value, role_id: roleFilter.value },
      headers: getHeaders()
    })

    if (res.data && Array.isArray(res.data)) {
      users.value = res.data
      pagination.value = {
        current_page: res.current_page || 1,
        last_page: res.last_page || 1,
        total: res.total || res.data.length
      }
    } else if (Array.isArray(res)) {
      users.value = res
      pagination.value = { current_page: 1, last_page: 1, total: res.length }
    } else {
      users.value = []
    }
  } catch (err) {
    console.error('Fetch users error:', err)
    errorMessage.value = err.data?.message || 'Gagal mengambil data user.'
  } finally {
    loading.value = false
  }
}

watch([search, roleFilter], () => {
  fetchUsers(1)
})

onMounted(() => {
  fetchRoles()
  fetchUsers()
})

// Handlers Modal & Form
const openAddModal = () => {
  isEditing.value = false
  currentId.value = null
  resetForm()
  showModal.value = true
}

const openEditModal = (user) => {
  isEditing.value = true
  currentId.value = user.id
  errors.value = {}

  Object.assign(form, {
    role_id: user.role_id,
    username: user.username || '',
    email: user.email || '',
    password: ''
  })
  showModal.value = true
}

const resetForm = () => {
  Object.assign(form, {
    role_id: roles.value[0]?.id || 3,
    username: '',
    email: '',
    password: ''
  })
  errors.value = {}
}

const handleSubmit = async () => {
  errors.value = {}
  
  const payload = { ...form }
  if (isEditing.value && !payload.password) {
    delete payload.password
  }

  const endpoint = isEditing.value 
    ? `${apiBase}/users/${currentId.value}`
    : `${apiBase}/users`

  try {
    await $fetch(endpoint, {
      method: isEditing.value ? 'PUT' : 'POST',
      body: payload,
      headers: getHeaders()
    })
    
    showModal.value = false
    await fetchUsers(pagination.value.current_page)
  } catch (err) {
    if (err.data && err.data.errors) {
      errors.value = err.data.errors
    } else {
      alert(err.data?.message || 'Terjadi kesalahan saat menyimpan data.')
    }
  }
}

const handleDelete = async (id) => {
  if (!confirm('Apakah Anda yakin ingin menghapus user ini?')) return

  try {
    await $fetch(`${apiBase}/users/${id}`, {
      method: 'DELETE',
      headers: getHeaders()
    })
    fetchUsers(pagination.value.current_page)
  } catch (err) {
    alert(err.data?.message || 'Gagal menghapus data')
  }
}

const enrolling = ref(false)

const handleEnrolUsers = async () => {
  if (!confirm('Apakah Anda yakin ingin meng-enrole seluruh Student dan Teacher yang belum memiliki akun user?')) return

  enrolling.value = true
  try {
    const res = await $fetch(`${apiBase}/users/enrol`, {
      method: 'POST',
      headers: getHeaders()
    })

    alert(`Berhasil! ${res.total_enroled} user baru telah di-enrole. Password Default NIS/NIP#tahunbulantanggallahir `)
    await fetchUsers(1) // Refresh tabel
  } catch (err) {
    alert(err.data?.message || 'Gagal melakukan enrole user.')
  } finally {
    enrolling.value = false
  }
}
</script>

<template>
  <div class="space-y-6 relative p-6 bg-slate-50 min-h-screen">
    
    <!-- Floating Loading -->
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
        <span class="text-xs font-medium tracking-wide">Memuat data user...</span>
      </div>
    </Transition>

    <!-- Header -->
   <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
  <div>
    <h1 class="text-2xl font-bold text-slate-800">Manajemen User</h1>
    <p class="text-xs text-slate-500 mt-1">Kelola data user dan relasi role.</p>
  </div>
  
  <div class="flex items-center gap-2">
    <!-- Tombol Enrole Student & Teacher -->
    <button 
      @click="handleEnrolUsers"
      :disabled="enrolling"
      class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center justify-center gap-2"
    >
      <span v-if="enrolling">Proses Enrole...</span>
      <span v-else>⚡ Enrole Student & Teacher</span>
    </button>

    <!-- Tombol Tambah User -->
    <button 
      @click="openAddModal"
      class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center justify-center gap-2"
    >
      <span>+ Tambah User</span>
    </button>
  </div>
</div>

    <!-- Alert Error -->
    <div v-if="errorMessage" class="p-4 bg-rose-50 border border-rose-200 text-rose-600 text-xs rounded-xl">
      {{ errorMessage }}
    </div>

    <!-- Search & Filter -->
    <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex flex-col md:flex-row gap-4 items-center justify-between">
      <div class="w-full md:w-72">
        <input 
          v-model="search"
          type="text" 
          placeholder="Cari Username atau Email..." 
          class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500"
        />
      </div>

      <div class="w-full md:w-auto">
        <select 
          v-model="roleFilter"
          class="w-full md:w-48 px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 text-slate-600"
        >
          <option value="">Semua Role</option>
          <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>
      </div>
    </div>

    <!-- Tabel Data -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50/70 border-b border-slate-100 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
              <th class="py-3.5 px-4">ID</th>
              <th class="py-3.5 px-4">Username</th>
              <th class="py-3.5 px-4">Email</th>
              <th class="py-3.5 px-4">Role</th>
              <th class="py-3.5 px-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
            <tr v-if="!loading && users.length === 0">
              <td colspan="5" class="py-8 text-center text-slate-400">Belum ada data user.</td>
            </tr>
            <tr v-for="user in users" :key="user.id" class="hover:bg-slate-50/50 transition">
              <td class="py-3.5 px-4 font-mono font-medium">{{ user.id }}</td>
              <td class="py-3.5 px-4 font-bold text-slate-800 font-mono">{{ user.username }}</td>
              <td class="py-3.5 px-4 text-slate-500">{{ user.email }}</td>
              <td class="py-3.5 px-4">
                <span class="px-2.5 py-1 text-[10px] font-bold rounded-full bg-emerald-50 text-emerald-600 border border-emerald-100">
                  {{ user.role?.name || `Role ID: ${user.role_id}` }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-right space-x-2">
                <button @click="openEditModal(user)" class="px-2.5 py-1 text-slate-600 hover:text-emerald-600 font-semibold">Edit</button>
                <button @click="handleDelete(user.id)" class="px-2.5 py-1 text-rose-500 hover:text-rose-700 font-semibold">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
        <span>Total: {{ pagination.total }} User</span>
        <div class="flex items-center space-x-1">
          <button 
            :disabled="pagination.current_page === 1"
            @click="fetchUsers(pagination.current_page - 1)"
            class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 transition"
          >
            Prev
          </button>
          <span class="px-3 py-1.5 font-semibold text-slate-700">
            {{ pagination.current_page }} / {{ pagination.last_page }}
          </span>
          <button 
            :disabled="pagination.current_page === pagination.last_page"
            @click="fetchUsers(pagination.current_page + 1)"
            class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 transition"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL FORM -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        
        <div class="px-6 py-4 bg-emerald-700 text-white flex justify-between items-center">
          <h3 class="text-sm font-bold">{{ isEditing ? 'Edit User' : 'Tambah User Baru' }}</h3>
          <button @click="showModal = false" class="text-emerald-100 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Role *</label>
            <select v-model="form.role_id" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none">
              <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
            <span v-if="errors.role_id" class="text-[10px] text-rose-500">{{ errors.role_id[0] }}</span>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Username *</label>
            <input v-model="form.username" type="text" maxlength="20" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none" />
            <span v-if="errors.username" class="text-[10px] text-rose-500">{{ errors.username[0] }}</span>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Email *</label>
            <input v-model="form.email" type="email" required class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none" />
            <span v-if="errors.email" class="text-[10px] text-rose-500">{{ errors.email[0] }}</span>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">
              Password {{ isEditing ? '(Kosongkan jika tidak diubah)' : '*' }}
            </label>
            <input v-model="form.password" type="password" :required="!isEditing" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none" />
            <span v-if="errors.password" class="text-[10px] text-rose-500">{{ errors.password[0] }}</span>
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