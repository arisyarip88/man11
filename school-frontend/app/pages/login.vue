<script setup>
import { ref, computed } from 'vue'

definePageMeta({
  layout: false
})

const route = useRoute()
const { login, isLoading } = useAuth()

const email = ref('')
const password = ref('')
const errorMessage = ref('')
const isExamInProgress = ref(false)

const isSessionReplaced = computed(() => route.query.reason === 'session_replaced')

const handleLogin = async () => {
  errorMessage.value = ''
  isExamInProgress.value = false

  try {
    await login({
      login: email.value.trim(),
      email: email.value.trim(),
      username: email.value.trim(),
      password: password.value
    })
  } catch (err) {
    console.error('Login error:', err)
    if (err.data?.error_code === 'exam_in_progress') {
      isExamInProgress.value = true
      errorMessage.value = err.data.message || 'Anda sedang memiliki sesi ujian aktif yang belum selesai. Anda tidak dapat login di perangkat lain saat ujian berlangsung.'
    } else if (err.data?.message) {
      errorMessage.value = err.data.message
    } else if (err.message && err.message.includes('Failed to fetch')) {
      errorMessage.value = 'Gagal terhubung ke server backend (localhost:8000). Pastikan server backend sedang aktif.'
    } else {
      errorMessage.value = err.message || 'Email atau password salah.'
    }
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex items-center justify-center p-4 font-sans">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-100 p-8 space-y-6">
      
      <!-- Brand & Header -->
      <div class="text-center space-y-2">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-brand-100 text-brand-800 mb-2">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">MAN 11 JAKARTA</h1>
        <p class="text-sm text-slate-500">Masuk ke akun Anda untuk melanjutkan</p>
      </div>

      <!-- Session Replaced Alert (A6) -->
      <div v-if="isSessionReplaced" class="bg-amber-50 text-amber-900 p-3.5 rounded-xl text-xs border border-amber-200 flex items-start space-x-2.5">
        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <div>
          <span class="font-bold block text-amber-800 mb-0.5">Sesi Berpindah Perangkat</span>
          Akun Anda telah masuk di perangkat lain. Sistem hanya mengizinkan 1 perangkat aktif demi keamanan dan integritas ujian.
        </div>
      </div>

      <!-- Exam in progress Alert (A1) -->
      <div v-if="isExamInProgress" class="bg-rose-50 text-rose-900 p-3.5 rounded-xl text-xs border border-rose-200 flex items-start space-x-2.5">
        <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
        </svg>
        <div>
          <span class="font-bold block text-rose-800 mb-0.5">Ujian Sedang Berlangsung</span>
          {{ errorMessage }}
        </div>
      </div>

      <!-- Generic Error Alert -->
      <div v-else-if="errorMessage" class="bg-red-50 text-red-600 p-3 rounded-lg text-xs font-medium border border-red-100">
        {{ errorMessage }}
      </div>

      <!-- Form Login -->
      <form @submit.prevent="handleLogin" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Email / Username / NIP</label>
          <input 
            v-model="email"
            type="text" 
            required
            placeholder="NIP / Username / Email" 
            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 outline-none focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition min-h-[44px]"
          />
        </div>

        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider">Password</label>
            <a href="#" class="text-xs font-semibold text-brand-700 hover:text-brand-800 hover:underline">Lupa password?</a>
          </div>
          <input 
            v-model="password"
            type="password" 
            required
            placeholder="••••••••" 
            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 outline-none focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition min-h-[44px]"
          />
        </div>

        <button 
          type="submit"
          :disabled="isLoading"
          class="w-full bg-brand-700 hover:bg-brand-800 active:bg-brand-900 text-white font-bold py-3 px-4 rounded-xl text-sm shadow-md transition flex items-center justify-center space-x-2 disabled:opacity-50 min-h-[44px]"
        >
          <span v-if="isLoading">Loading...</span>
          <span v-else>Masuk ke Portal</span>
        </button>
      </form>

      <!-- Footer -->
      <p class="text-center text-xs text-slate-500">
        Belum punya akun? 
        <NuxtLink to="/register" class="font-semibold text-brand-700 hover:underline">Daftar sekarang</NuxtLink>
      </p>

    </div>
  </div>
</template>