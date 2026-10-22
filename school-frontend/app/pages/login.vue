<script setup>
import { ref, computed } from 'vue'

definePageMeta({
  layout: false
})

const route = useRoute()
const { login, isLoading } = useAuth()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const rememberMe = ref(false)
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
      errorMessage.value = err.data.message || 'Sesi ujian aktif Anda sedang berjalan di perangkat lain. Sistem membatasi 1 perangkat aktif saat ujian berlangsung.'
    } else if (err.data?.message) {
      errorMessage.value = err.data.message
    } else if (err.message && err.message.includes('Failed to fetch')) {
      errorMessage.value = 'Gagal terhubung ke server. Pastikan jaringan internet atau server aktif.'
    } else {
      errorMessage.value = err.message || 'Identitas atau kata sandi tidak sesuai. Silakan coba lagi.'
    }
  }
}
</script>

<template>
  <div class="min-h-screen w-full bg-white text-slate-800 font-sans flex flex-col lg:flex-row antialiased selection:bg-brand-100 selection:text-brand-900">
    
    <!-- LEFT PANEL: Visual Identity & Institutional Heritage (Desktop Full-Height) -->
    <section class="relative w-full lg:w-1/2 xl:w-7/12 bg-brand-900 text-white flex flex-col justify-between p-8 sm:p-12 lg:p-16 xl:p-20 overflow-hidden">
      
      <!-- Subtle Brand Accent Layer (Zero AI Fluff) -->
      <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-brand-800/40 via-transparent to-brand-950/80"></div>
      
      <!-- Top Brand Stamp -->
      <header class="relative z-10 flex items-center justify-between">
        <NuxtLink to="/" class="inline-flex items-center gap-3.5 group">
          <div class="w-12 h-12 rounded-xl bg-white p-2 shadow-sm flex items-center justify-center shrink-0">
            <img src="/logo.png" alt="Logo MAN 11 Jakarta" class="w-full h-full object-contain" />
          </div>
          <div class="leading-tight">
            <span class="block text-base font-bold tracking-tight text-white">MAN 11 JAKARTA</span>
            <span class="block text-xs text-brand-200">Kementerian Agama RI</span>
          </div>
        </NuxtLink>

        <span class="text-xs font-medium px-3 py-1 rounded-full bg-white/10 text-brand-100 border border-white/15">
          Akreditasi A
        </span>
      </header>

      <!-- Center Editorial Narrative -->
      <div class="relative z-10 my-auto py-12 max-w-xl space-y-6">
        <div class="w-10 h-1 bg-brand-400 rounded-full"></div>
        
        <h1 class="text-3xl sm:text-4xl xl:text-5xl font-extrabold tracking-tight text-white leading-tight">
          Layanan Akademik & Pembelajaran Digital
        </h1>
        
        <p class="text-brand-100/90 text-sm sm:text-base leading-relaxed font-normal">
          Portal terintegrasi untuk siswa, dewan guru, dan staf madrasah. Akses jadwal pelajaran, materi terstruktur, penugasan, dan evaluasi hasil belajar secara transparan.
        </p>

        <!-- Institutional Trust Points -->
        <div class="pt-4 border-t border-brand-800/80 grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs text-brand-200">
          <div>
            <span class="block font-semibold text-white mb-0.5">Integritas CBT</span>
            Penguncian sesi tunggal untuk menjaga objektivitas penilaian siswa.
          </div>
          <div>
            <span class="block font-semibold text-white mb-0.5">Satu Pintu</span>
            Satu akun untuk seluruh rekap kehadiran, materi, dan rapor berkala.
          </div>
        </div>
      </div>

      <!-- Bottom Institutional Info -->
      <footer class="relative z-10 pt-8 border-t border-brand-800/80 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs text-brand-200/80">
        <address class="not-italic">
          Jl. Lebak Bulus V No.1, Cilandak, Jakarta Selatan
        </address>
        <span>Portal Resmi Madrasah Aliyah Negeri 11</span>
      </footer>

    </section>

    <!-- RIGHT PANEL: Minimalist Focused Login Panel -->
    <section class="w-full lg:w-1/2 xl:w-5/12 min-h-screen bg-slate-50/60 flex flex-col justify-between p-6 sm:p-10 lg:p-14 xl:p-16 relative">
      
      <!-- Top Utility Nav -->
      <div class="w-full flex items-center justify-between">
        <div class="lg:hidden flex items-center gap-2.5">
          <img src="/logo.png" alt="Logo MAN 11" class="w-8 h-8 object-contain" />
          <span class="text-xs font-bold text-slate-900 tracking-tight">MAN 11 JAKARTA</span>
        </div>
        <div class="hidden lg:block"></div>

        <NuxtLink 
          to="/" 
          class="text-xs font-semibold text-slate-500 hover:text-brand-700 transition inline-flex items-center gap-1.5"
        >
          <span>← Kembali ke Beranda</span>
        </NuxtLink>
      </div>

      <!-- Center Form Wrapper -->
      <div class="w-full max-w-[400px] mx-auto my-auto py-8">
        
        <!-- Form Header -->
        <div class="mb-8 space-y-2">
          <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
            Masuk ke Akun
          </h2>
          <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">
            Masukkan NISN untuk Siswa, NIP untuk Guru, atau username terdaftar.
          </p>
        </div>

        <!-- Session Replaced Alert -->
        <div 
          v-if="isSessionReplaced" 
          role="alert"
          class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs leading-relaxed space-y-1"
        >
          <span class="font-bold text-amber-950 block">Sesi Dipindahkan</span>
          Akun Anda telah masuk di perangkat lain. Sistem hanya mengizinkan satu perangkat aktif demi keamanan data Anda.
        </div>

        <!-- Exam Active Alert -->
        <div 
          v-if="isExamInProgress" 
          role="alert"
          class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs leading-relaxed space-y-1"
        >
          <span class="font-bold text-rose-950 block">Ujian Sedang Berlangsung</span>
          {{ errorMessage }}
        </div>

        <!-- General Error Alert -->
        <div 
          v-else-if="errorMessage" 
          role="alert"
          class="mb-6 p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-medium"
        >
          {{ errorMessage }}
        </div>

        <!-- Clean Form Card -->
        <form @submit.prevent="handleLogin" class="space-y-4">
          
          <!-- Identifier Input -->
          <div class="space-y-1.5">
            <label for="login-identifier" class="block text-xs font-semibold text-slate-700">
              ID Pengguna
            </label>
            <input 
              id="login-identifier"
              v-model="email"
              type="text" 
              required
              autocomplete="username"
              placeholder="NISN / NIP / Username"
              class="w-full h-11 px-3.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-900 placeholder:text-slate-400 outline-none focus:border-brand-700 focus:ring-2 focus:ring-brand-700/15 transition"
            />
          </div>

          <!-- Password Input with Native Eye Toggle -->
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <label for="login-password" class="block text-xs font-semibold text-slate-700">
                Kata Sandi
              </label>
              <a 
                href="#" 
                class="text-xs font-semibold text-brand-700 hover:text-brand-800 transition"
                tabindex="-1"
              >
                Lupa sandi?
              </a>
            </div>
            
            <div class="relative">
              <input 
                id="login-password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                placeholder="Masukkan kata sandi"
                class="w-full h-11 pl-3.5 pr-10 bg-white border border-slate-200 rounded-xl text-sm text-slate-900 placeholder:text-slate-400 outline-none focus:border-brand-700 focus:ring-2 focus:ring-brand-700/15 transition"
              />
              <button 
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-slate-700 transition focus:outline-none min-tap-target"
                :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
              >
                <svg v-if="!showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Remember Me Checkbox -->
          <div class="flex items-center justify-between text-xs pt-1">
            <label class="flex items-center gap-2 cursor-pointer select-none text-slate-600 hover:text-slate-900">
              <input 
                v-model="rememberMe"
                type="checkbox" 
                class="w-4 h-4 rounded border-slate-300 text-brand-700 focus:ring-brand-700/20"
              />
              <span>Ingat perangkat ini</span>
            </label>
          </div>

          <!-- Submit Button -->
          <button 
            type="submit"
            :disabled="isLoading"
            class="w-full h-11 mt-2 bg-brand-700 hover:bg-brand-800 active:bg-brand-900 text-white font-bold rounded-xl text-sm transition shadow-sm hover:shadow flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
          >
            <template v-if="isLoading">
              <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>Memverifikasi...</span>
            </template>
            <template v-else>
              <span>Masuk ke Portal</span>
            </template>
          </button>
        </form>

        <!-- Secondary Action -->
        <div class="mt-6 pt-6 border-t border-slate-200/80 text-center">
          <p class="text-xs text-slate-500">
            Belum memiliki akun terdaftar? 
            <NuxtLink to="/register" class="font-bold text-brand-700 hover:underline ml-1">
              Daftar sekarang
            </NuxtLink>
          </p>
        </div>

      </div>

      <!-- Bottom Minimal Footer -->
      <footer class="pt-6 border-t border-slate-200/80 flex items-center justify-between text-xs text-slate-400">
        <span>© 2026 MAN 11 Jakarta</span>
        <a href="mailto:admin@man11jakarta.sch.id" class="hover:text-slate-600 transition">
          Bantuan Masuk
        </a>
      </footer>

    </section>

  </div>
</template>