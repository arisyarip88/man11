<script setup>
import { ref, watch, reactive } from 'vue'

const props = defineProps({
  isOpen: Boolean,
  user: Object
})

const emit = defineEmits(['close', 'updated'])

const activeTab = ref('profile') // 'profile' | 'password'
const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const form = reactive({
  name: '',
  username: '',
  email: '',
  current_password: '',
  new_password: '',
  new_password_confirmation: ''
})

// Sinkronkan data form ketika modal dibuka
watch(() => props.user, (newUser) => {
  if (newUser) {
    form.name = newUser.name || ''
    form.username = newUser.username || ''
    form.email = newUser.email || ''
  }
}, { immediate: true })

const closeModal = () => {
  errorMessage.value = ''
  successMessage.value = ''
  form.current_password = ''
  form.new_password = ''
  form.new_password_confirmation = ''
  emit('close')
}

const handleSubmit = async () => {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    // Sesuaikan URL API Laravel
    const response = await $fetch('http://localhost:8000/api/profile/update', {
      method: 'PUT',
      headers: {
        Authorization: `Bearer ${useCookie('auth_token').value}` 
      },
      body: form
    })

    successMessage.value = response.message
    emit('updated', response.data)
    
    setTimeout(() => {
      closeModal()
    }, 1200)
  } catch (err) {
    errorMessage.value = err.data?.message || 'Terjadi kesalahan saat menyimpan data.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs">
    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl transition-all">
      
      <!-- Header Modal -->
      <div class="flex items-center justify-between border-b pb-3">
        <h3 class="text-lg font-bold text-gray-800">Pengaturan Profil</h3>
        <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Tab Switcher -->
      <div class="mt-4 flex rounded-lg bg-gray-100 p-1">
        <button 
          @click="activeTab = 'profile'"
          :class="['w-1/2 py-2 text-xs font-semibold rounded-md transition', activeTab === 'profile' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-gray-700']">
          Data Profil
        </button>
        <button 
          @click="activeTab = 'password'"
          :class="['w-1/2 py-2 text-xs font-semibold rounded-md transition', activeTab === 'password' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-gray-700']">
          Ganti Password
        </button>
      </div>

      <!-- Alert Message -->
      <div v-if="errorMessage" class="mt-3 rounded-lg bg-red-50 p-2 text-xs text-red-600">
        {{ errorMessage }}
      </div>
      <div v-if="successMessage" class="mt-3 rounded-lg bg-green-50 p-2 text-xs text-green-600">
        {{ successMessage }}
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="mt-4 space-y-3">
        
        <!-- Tab 1: Profile -->
        <template v-if="activeTab === 'profile'">
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">NAMA LENGKAP</label>
            <input v-model="form.name" type="text" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">USERNAME</label>
            <input v-model="form.username" type="text" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">ALAMAT EMAIL</label>
            <input v-model="form.email" type="email" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
          </div>
        </template>

        <!-- Tab 2: Password -->
        <template v-if="activeTab === 'password'">
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">PASSWORD SAAT INI</label>
            <input v-model="form.current_password" type="password" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">PASSWORD BARU</label>
            <input v-model="form.new_password" type="password" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">KONFIRMASI PASSWORD BARU</label>
            <input v-model="form.new_password_confirmation" type="password" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
          </div>
        </template>

        <!-- Footer / Action Buttons -->
        <div class="mt-6 flex justify-end gap-2 border-t pt-3">
          <button type="button" @click="closeModal" class="rounded-lg bg-gray-100 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-200">
            Batal
          </button>
          <button type="submit" :disabled="loading" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
            {{ loading ? 'Sabar...' : 'Simpan Perubahan' }}
          </button>
        </div>
      </form>

    </div>
  </div>
</template>