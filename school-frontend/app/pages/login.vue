<script setup>
import { ref } from 'vue'

definePageMeta({
  layout: false
})

// Panggil fungsi login & state isLoading dari composable useAuth
const { login, isLoading } = useAuth()

const email = ref('')
const password = ref('')
const errorMessage = ref('')

const handleLogin = async () => {
  errorMessage.value = ''

  try {
    // Memanggil fungsi login dari composable (mengirim payload email & password)
    await login({
      email: email.value,
      password: password.value
    })
  } catch (err) {
    console.error('Login error:', err)
    errorMessage.value = err.data?.message || err.message || 'Email atau password salah.'
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex items-center justify-center p-4 font-sans">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-100 p-8 space-y-6">
      
      <!-- Brand & Header -->
      <div class="text-center space-y-2">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 mb-2">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">MAN 11 JAKARTA</h1>
        <p class="text-sm text-slate-400">Masuk ke akun Anda untuk melanjutkan</p>
      </div>

      <!-- Error Alert -->
      <div v-if="errorMessage" class="bg-red-50 text-red-600 p-3 rounded-lg text-xs font-medium border border-red-100">
        {{ errorMessage }}
      </div>

      <!-- Form Login -->
      <form @submit.prevent="handleLogin" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Email</label>
          <input 
            v-model="email"
            type="email" 
            required
            placeholder="nama@email.com" 
            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-700 outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
          />
        </div>

        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Password</label>
            <a href="#" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 hover:underline">Lupa password?</a>
          </div>
          <input 
            v-model="password"
            type="password" 
            required
            placeholder="••••••••" 
            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-700 outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
          />
        </div>

        <button 
          type="submit"
          :disabled="isLoading"
          class="w-full bg-emerald-500 hover:bg-emerald-600 active:bg-emerald-700 text-white font-medium py-2.5 px-4 rounded-xl text-sm shadow-lg shadow-emerald-500/25 transition flex items-center justify-center space-x-2 disabled:opacity-50"
        >
          <span v-if="isLoading">Loading...</span>
          <span v-else>Masuk</span>
        </button>
      </form>

      <!-- Footer -->
      <p class="text-center text-xs text-slate-400">
        Belum punya akun? 
        <NuxtLink to="/register" class="font-semibold text-emerald-600 hover:underline">Daftar sekarang</NuxtLink>
      </p>

    </div>
  </div>
</template>