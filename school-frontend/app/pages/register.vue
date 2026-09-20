<script setup>
import { ref } from 'vue'

definePageMeta({
  layout: false
})

const { register } = useAuth()

const username = ref('')
const email = ref('')
const password = ref('')
const isLoading = ref(false)
const errorMessage = ref('')

const handleRegister = async () => {
  isLoading.value = true
  errorMessage.value = ''
  
  try {
    await register({ 
      username: username.value, 
      email: email.value, 
      password: password.value 
    })
  } catch (error) {
    errorMessage.value = 'Gagal mendaftar. Silakan coba lagi.'
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen w-full flex flex-col md:flex-row font-sans bg-white">
    
    <!-- Left Column: Full Height Register Form -->
    <div class="w-full md:w-1/2 min-h-screen p-8 md:p-16 flex flex-col justify-between">
      <!-- Brand Logo/Name -->
    

      <!-- Form Container -->
      <div class="my-auto py-8 max-w-sm mx-auto w-full text-center">
        <h2 class="text-3xl font-bold text-emerald-500 mb-2">Create Account</h2>
        <div class="w-12 h-1 bg-emerald-500 mx-auto rounded-full mb-6"></div>

        <!-- Social Buttons -->
        <div class="flex justify-center space-x-3 mb-4">
          <button type="button" class="w-10 h-10 rounded-full border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 transition font-semibold text-sm">
            f
          </button>
          <button type="button" class="w-10 h-10 rounded-full border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 transition font-semibold text-xs">
            in
          </button>
          <button type="button" class="w-10 h-10 rounded-full border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 transition font-semibold text-sm">
            G
          </button>
        </div>

        <p class="text-xs text-slate-400 mb-6">or use your email for registration</p>

        <!-- Error Alert -->
        <div v-if="errorMessage" class="bg-red-50 text-red-600 p-3 rounded-xl text-xs font-medium mb-4">
          {{ errorMessage }}
        </div>

        <!-- Input Fields -->
        <form @submit.prevent="handleRegister" class="space-y-3.5">
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </span>
            <input 
              v-model="username"
              type="text" 
              required
              placeholder="username" 
              class="w-full pl-11 pr-4 py-3 bg-slate-100/80 border-none rounded-xl text-sm text-slate-700 outline-none focus:bg-slate-100 focus:ring-2 focus:ring-emerald-500 transition placeholder:text-slate-400"
            />
          </div>

          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </span>
            <input 
              v-model="email"
              type="email" 
              required
              placeholder="Email" 
              class="w-full pl-11 pr-4 py-3 bg-slate-100/80 border-none rounded-xl text-sm text-slate-700 outline-none focus:bg-slate-100 focus:ring-2 focus:ring-emerald-500 transition placeholder:text-slate-400"
            />
          </div>

          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </span>
            <input 
              v-model="password"
              type="password" 
              required
              placeholder="Password" 
              class="w-full pl-11 pr-4 py-3 bg-slate-100/80 border-none rounded-xl text-sm text-slate-700 outline-none focus:bg-slate-100 focus:ring-2 focus:ring-emerald-500 transition placeholder:text-slate-400"
            />
          </div>

          <button 
            type="submit"
            :disabled="isLoading"
            class="w-40 bg-emerald-500 hover:bg-emerald-600 active:bg-emerald-700 text-white font-semibold py-3 px-6 rounded-full text-sm shadow-lg shadow-emerald-500/20 transition mt-6 disabled:opacity-50"
          >
            <span v-if="isLoading">Loading...</span>
            <span v-else>Sign Up</span>
          </button>
        </form>
      </div>

      <div></div>
    </div>

    <!-- Right Column: Full Height Green Panel -->
    <div class="w-full md:w-1/2 min-h-screen bg-emerald-500 p-8 md:p-16 text-white flex flex-col justify-center items-center text-center">
      <h2 class="text-4xl font-bold mb-3">Welcome Back!</h2>
      <div class="w-16 h-1 bg-white/40 rounded-full mb-6"></div>
      <p class="text-sm text-emerald-50 leading-relaxed mb-8 max-w-sm">
        To keep connected with us please login with your personal info
      </p>
      <NuxtLink 
        to="/login"
        class="px-12 py-3 border-2 border-white rounded-full font-semibold text-sm hover:bg-white hover:text-emerald-500 transition"
      >
        Sign In
      </NuxtLink>
    </div>

  </div>
</template>