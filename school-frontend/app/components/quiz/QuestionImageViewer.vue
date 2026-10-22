<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'

const props = defineProps<{
  imageUrl?: string | null
  meta?: {
    width?: number
    height?: number
    aspect_ratio?: number | string
    alt_text?: string
    format?: string
  } | null
}>()

const config = useRuntimeConfig()
const storageBase = config.public.storageBase || 'http://localhost:8000/storage'

const isLoaded = ref(false)
const hasError = ref(false)
const isZoomed = ref(false)
const retryKey = ref(0)

const computedUrl = computed(() => {
  if (!props.imageUrl) return ''
  let url = props.imageUrl
  if (!url.startsWith('http://') && !url.startsWith('https://')) {
    const cleanPath = url.replace(/^\/storage\/?/, '')
    url = `${storageBase}/${cleanPath}`
  }
  // Tambahkan retry key jika tombol muat ulang ditekan
  if (retryKey.value > 0) {
    const delimiter = url.includes('?') ? '&' : '?'
    return `${url}${delimiter}retry=${retryKey.value}`
  }
  return url
})

// Reset status load saat imageUrl berganti
watch(
  () => props.imageUrl,
  () => {
    isLoaded.value = false
    hasError.value = false
    retryKey.value = 0
  }
)

const retryLoading = () => {
  hasError.value = false
  isLoaded.value = false
  retryKey.value++
}

// Tutup zoom dengan tombol ESC
const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && isZoomed.value) {
    isZoomed.value = false
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
  <div v-if="imageUrl" class="my-4 select-none">
    <!-- Kontainer Gambar dengan Aspect Ratio Stabil (Zero Layout Shift) -->
    <div
      class="relative max-w-2xl mx-auto rounded-2xl overflow-hidden border border-slate-200/90 bg-slate-50/80 shadow-2xs group cursor-zoom-in transition hover:border-emerald-300"
      @click="isZoomed = true"
    >
      <!-- Skeleton Placeholder saat gambar masih mengunduh -->
      <div
        v-if="!isLoaded && !hasError"
        class="w-full h-48 sm:h-64 bg-slate-200/80 animate-pulse flex flex-col items-center justify-center gap-2 text-slate-500 text-xs font-semibold"
      >
        <svg class="w-6 h-6 text-slate-400 animate-spin" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <span>Memuat diagram ilustrasi soal...</span>
      </div>

      <!-- State Jika Gagal Muat (Koneksi Terputus) -->
      <div
        v-if="hasError"
        class="p-6 text-center space-y-3 bg-rose-50/70 border border-rose-200 rounded-2xl"
      >
        <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <p class="text-xs font-semibold text-rose-900">
          Gagal memuat gambar diagram. Periksa kembali jaringan internet Anda.
        </p>
        <button
          type="button"
          @click.stop="retryLoading"
          class="px-3.5 py-1.5 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 transition shadow-xs inline-flex items-center gap-1.5"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <span>Coba Muat Ulang Gambar</span>
        </button>
      </div>

      <!-- Elemen Gambar Utama -->
      <img
        v-show="!hasError"
        :src="computedUrl"
        :alt="meta?.alt_text || 'Gambar pendukung butir soal'"
        :class="[
          'w-full max-h-[380px] sm:max-h-[460px] object-contain transition-all duration-300 mx-auto',
          isLoaded ? 'opacity-100 scale-100' : 'opacity-0 scale-95'
        ]"
        loading="eager"
        decoding="async"
        @load="isLoaded = true"
        @error="hasError = true"
      />

      <!-- Badge Overlay Hint Zoom (Desktop & Mobile) -->
      <div
        v-if="isLoaded && !hasError"
        class="absolute bottom-2.5 right-2.5 px-2.5 py-1 rounded-xl bg-slate-900/80 text-white text-[11px] font-bold backdrop-blur-xs flex items-center gap-1.5 opacity-80 group-hover:opacity-100 transition shadow-sm pointer-events-none"
      >
        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
        </svg>
        <span>Klik untuk Perbesar</span>
      </div>
    </div>

    <!-- Keterangan / Caption Gambar Soal -->
    <p
      v-if="meta?.alt_text && isLoaded && !hasError"
      class="text-[11px] sm:text-xs text-slate-500 text-center mt-2 italic font-medium px-4"
    >
      {{ meta.alt_text }}
    </p>

    <!-- MODAL LIGHTBOX / FULLSCREEN ZOOM INTERAKTIF -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="isZoomed"
          class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/90 backdrop-blur-md p-4 sm:p-8"
          @click="isZoomed = false"
        >
          <div
            class="relative max-w-5xl max-h-[92vh] w-full flex flex-col items-center justify-center space-y-3"
            @click.stop
          >
            <!-- Header Modal Zoom -->
            <div class="w-full flex items-center justify-between text-white px-2">
              <span class="text-xs font-semibold text-slate-300">
                {{ meta?.alt_text || 'Pratinjau Layar Penuh Diagram Soal' }}
              </span>
              <button
                type="button"
                @click="isZoomed = false"
                class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs flex items-center gap-1.5 transition border border-white/10 cursor-pointer"
              >
                <span>Tutup</span>
                <kbd class="px-1.5 py-0.5 rounded bg-white/20 text-[10px] font-mono">Esc</kbd>
                <svg class="w-4 h-4 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Gambar Layar Penuh -->
            <div class="w-full max-h-[82vh] overflow-auto flex items-center justify-center p-2 rounded-2xl bg-white shadow-2xl">
              <img
                :src="computedUrl"
                :alt="meta?.alt_text || 'Diagram soal ukuran penuh'"
                class="max-w-full max-h-[78vh] object-contain rounded-xl"
              />
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
