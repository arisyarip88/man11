<script setup lang="ts">
import { ref, watch, onBeforeUnmount, nextTick } from 'vue'

const props = defineProps<{
  modelValue: boolean
  session?: any
  loading?: boolean
  errorMessage?: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'submit', payload: { qr_token: string, time_slot?: number }): void
  (e: 'close'): void
}>()

const activeTab = ref<'camera' | 'manual'>('manual')
const inputToken = ref('')
const videoRef = ref<HTMLVideoElement | null>(null)
const cameraStream = ref<MediaStream | null>(null)
const cameraError = ref('')
const isCameraStarting = ref(false)
const scanInterval = ref<any>(null)
const hasBarcodeDetector = ref(false)

const subjectName = (course: any) => course?.subject?.name || 'Mata Pelajaran'
const className = (course: any) => course?.class?.className?.name || course?.class?.class_name?.name || course?.class?.name || 'Kelas'

const checkBarcodeSupport = () => {
  if (typeof window !== 'undefined' && 'BarcodeDetector' in window) {
    hasBarcodeDetector.value = true
  } else {
    hasBarcodeDetector.value = false
  }
}

const stopCamera = () => {
  if (scanInterval.value) {
    clearInterval(scanInterval.value)
    scanInterval.value = null
  }
  if (cameraStream.value) {
    cameraStream.value.getTracks().forEach(t => t.stop())
    cameraStream.value = null
  }
}

const startCamera = async () => {
  stopCamera()
  cameraError.value = ''
  isCameraStarting.value = true

  await nextTick()

  if (!navigator?.mediaDevices?.getUserMedia) {
    cameraError.value = 'Perangkat atau browser ini tidak mendukung akses kamera langsung.'
    isCameraStarting.value = false
    activeTab.value = 'manual'
    return
  }

  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      video: {
        facingMode: { ideal: 'environment' },
        width: { ideal: 1280 },
        height: { ideal: 720 },
      },
      audio: false,
    })

    cameraStream.value = stream
    if (videoRef.value) {
      videoRef.value.srcObject = stream
      await videoRef.value.play().catch(() => {})
    }

    if (hasBarcodeDetector.value) {
      const barcodeDetector = new (window as any).BarcodeDetector({ formats: ['qr_code'] })
      scanInterval.value = setInterval(async () => {
        if (!videoRef.value || videoRef.value.readyState < 2) return
        try {
          const barcodes = await barcodeDetector.detect(videoRef.value)
          if (barcodes && barcodes.length > 0) {
            const code = barcodes[0].rawValue
            if (code) {
              stopCamera()
              handleScannedValue(code)
            }
          }
        } catch {
          // Ignore scanning frames error
        }
      }, 350)
    }
  } catch (err: any) {
    console.warn('Camera access denied or failed:', err)
    cameraError.value = 'Akses kamera tidak diizinkan atau tidak tersedia. Silakan gunakan Input Token secara manual.'
  } finally {
    isCameraStarting.value = false
  }
}

const handleScannedValue = (raw: string) => {
  const clean = raw.trim()
  if (!clean) return

  try {
    if (clean.startsWith('{') && clean.endsWith('}')) {
      const parsed = JSON.parse(clean)
      emit('submit', {
        qr_token: parsed.qr_token || parsed.token || clean,
        time_slot: parsed.time_slot !== undefined ? parsed.time_slot : undefined,
      })
      return
    }
  } catch {
    // fallback plain string
  }

  emit('submit', { qr_token: clean })
}

const handleManualSubmit = () => {
  const clean = inputToken.value.trim()
  if (!clean) return
  handleScannedValue(clean)
}

const pasteFromClipboard = async () => {
  try {
    const text = await navigator.clipboard.readText()
    if (text) {
      inputToken.value = text.trim()
    }
  } catch {
    alert('Tidak dapat membaca papan klip. Silakan tempel secara manual.')
  }
}

const closeModal = () => {
  stopCamera()
  inputToken.value = ''
  emit('update:modelValue', false)
  emit('close')
}

watch(() => props.modelValue, (isOpen) => {
  if (isOpen) {
    checkBarcodeSupport()
    inputToken.value = ''
    if (hasBarcodeDetector.value) {
      activeTab.value = 'camera'
      startCamera()
    } else {
      activeTab.value = 'manual'
    }
  } else {
    stopCamera()
  }
})

watch(activeTab, (newTab) => {
  if (newTab === 'camera') {
    startCamera()
  } else {
    stopCamera()
  }
})

onBeforeUnmount(() => {
  stopCamera()
})
</script>

<template>
  <div
    v-if="modelValue"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity"
    @click.self="closeModal"
  >
    <div class="bg-white rounded-3xl max-w-md w-full overflow-hidden shadow-2xl border border-slate-100 flex flex-col max-h-[90vh]">
      <!-- Header -->
      <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center shrink-0 shadow-xs">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
            </svg>
          </div>
          <div>
            <h3 class="font-extrabold text-slate-800 text-sm">Presensi QR Proyektor KBM</h3>
            <p class="text-xs text-slate-500 font-medium">
              {{ subjectName(session?.course) }} • {{ className(session?.course) }}
            </p>
          </div>
        </div>
        <button
          type="button"
          @click="closeModal"
          class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 hover:text-slate-700 hover:bg-slate-200 flex items-center justify-center transition"
        >
          ✕
        </button>
      </div>

      <!-- Tab Switcher -->
      <div class="p-3 bg-slate-100/70 border-b border-slate-200/60 flex items-center gap-2">
        <button
          type="button"
          @click="activeTab = 'camera'"
          class="flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5"
          :class="activeTab === 'camera' ? 'bg-white text-emerald-800 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          <span>Kamera Scanner</span>
        </button>
        <button
          type="button"
          @click="activeTab = 'manual'"
          class="flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5"
          :class="activeTab === 'manual' ? 'bg-white text-emerald-800 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
          <span>Input Kode Token</span>
        </button>
      </div>

      <!-- Content Area -->
      <div class="p-5 overflow-y-auto space-y-4">
        <!-- Error Message Banner -->
        <div v-if="errorMessage" class="p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl text-xs flex items-center gap-2.5">
          <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="font-medium leading-relaxed">{{ errorMessage }}</span>
        </div>

        <!-- TAB 1: Camera Scanner -->
        <div v-if="activeTab === 'camera'" class="space-y-3">
          <div class="relative w-full aspect-square bg-slate-950 rounded-2xl overflow-hidden flex items-center justify-center border-2 border-slate-800 shadow-inner">
            <video
              ref="videoRef"
              class="w-full h-full object-cover"
              autoplay
              playsinline
              muted
            ></video>

            <!-- Scanner Frame & Laser Line -->
            <div class="absolute inset-8 border-2 border-emerald-400/80 rounded-2xl pointer-events-none flex items-center justify-center shadow-lg">
              <div class="w-full h-0.5 bg-gradient-to-r from-transparent via-emerald-400 to-transparent animate-pulse"></div>
            </div>

            <!-- Loading camera overlay -->
            <div v-if="isCameraStarting" class="absolute inset-0 bg-slate-950/80 flex flex-col items-center justify-center text-white gap-2">
              <div class="w-6 h-6 border-2 border-emerald-400 border-t-transparent rounded-full animate-spin"></div>
              <p class="text-xs font-semibold">Mengaktifkan kamera...</p>
            </div>

            <!-- Camera Error overlay -->
            <div v-if="cameraError" class="absolute inset-0 bg-slate-950/90 p-6 flex flex-col items-center justify-center text-center text-white gap-2">
              <p class="text-xs text-rose-300">{{ cameraError }}</p>
              <button
                type="button"
                @click="activeTab = 'manual'"
                class="mt-2 px-3 py-1.5 bg-white/20 hover:bg-white/30 text-white rounded-lg text-xs font-bold transition"
              >
                Gunakan Input Token ➔
              </button>
            </div>
          </div>

          <p class="text-center text-[11px] text-slate-500 font-medium">
            Arahkan kamera ke layar proyektor kelas hingga kode QR berada di dalam bingkai hijau.
          </p>
        </div>

        <!-- TAB 2: Manual Token Input -->
        <div v-else class="space-y-3.5">
          <p class="text-xs text-slate-600 leading-relaxed">
            Jika kamera tidak dapat digunakan, Anda dapat mengetikkan atau menempelkan kode token QR yang tertera pada layar proyektor:
          </p>

          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <label class="text-[11px] font-bold text-slate-700">Kode Token QR Proyektor</label>
              <button
                type="button"
                @click="pasteFromClipboard"
                class="text-[11px] font-bold text-emerald-700 hover:text-emerald-800 transition flex items-center gap-1"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <span>Tempel dari Clipboard</span>
              </button>
            </div>

            <input
              v-model="inputToken"
              type="text"
              placeholder="Contoh: a8f419... atau {&quot;qr_token&quot;:&quot;...&quot;}"
              class="w-full px-4 py-3 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:border-emerald-600 font-mono transition shadow-xs"
              @keyup.enter="handleManualSubmit"
            />
          </div>

          <div class="p-3 bg-emerald-50/60 border border-emerald-100 rounded-xl text-[11px] text-emerald-900 space-y-1">
            <p class="font-bold flex items-center gap-1">
              <span>💡</span>
              <span>Informasi Validasi QR Sesi</span>
            </p>
            <p class="text-slate-600">
              Setiap QR code di proyektor memiliki masa berlaku dinamis (15 menit). Pastikan memindai kode terbaru yang sedang ditampilkan guru.
            </p>
          </div>
        </div>
      </div>

      <!-- Footer Actions -->
      <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
        <button
          type="button"
          @click="closeModal"
          class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-100 transition"
        >
          Tutup
        </button>
        <button
          v-if="activeTab === 'manual'"
          type="button"
          :disabled="loading || !inputToken.trim()"
          @click="handleManualSubmit"
          class="px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white font-bold text-xs shadow-xs transition flex items-center gap-2"
        >
          <span v-if="loading" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          <span>Kirim Presensi Hadir</span>
        </button>
      </div>
    </div>
  </div>
</template>
