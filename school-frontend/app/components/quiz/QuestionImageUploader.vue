<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps<{
  modelValue?: string | null
  imageMeta?: any
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: string | null): void
  (e: 'update:imageMeta', val: any): void
}>()

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const storageBase = config.public.storageBase || 'http://localhost:8000/storage'
const token = useCookie<string | null>('auth_token')

const { compressImage } = useImageCompressor()
const isUploading = ref(false)
const dragOver = ref(false)
const isExpanded = ref(!!props.modelValue)
const uploadStats = ref<{ original: string; compressed: string; savings: string } | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const uploaderContainerRef = ref<HTMLDivElement | null>(null)
const altTextInput = ref('')

// Jika modelValue sudah ada (misal saat edit soal yang sudah ada gambar), otomatis buka
watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      isExpanded.value = true
    }
  },
  { immediate: true }
)

// Sinkronkan alt_text lokal jika metadata berubah
watch(
  () => props.imageMeta,
  (newMeta) => {
    if (newMeta && newMeta.alt_text) {
      altTextInput.value = newMeta.alt_text
    } else if (!props.modelValue) {
      altTextInput.value = ''
    }
  },
  { immediate: true, deep: true }
)

const updateAltText = () => {
  if (props.imageMeta) {
    emit('update:imageMeta', {
      ...props.imageMeta,
      alt_text: altTextInput.value.trim() || 'Ilustrasi pendukung butir soal'
    })
  }
}

const getFullUrl = (url: string | null | undefined) => {
  if (!url) return ''
  if (url.startsWith('http://') || url.startsWith('https://')) return url
  const cleanPath = url.replace(/^\/storage\/?/, '')
  return `${storageBase}/${cleanPath}`
}

const processAndUploadFile = async (file: File) => {
  if (!file || !file.type.startsWith('image/')) {
    alert('Harap pilih berkas gambar (JPG, PNG, WebP, atau SVG)')
    return
  }

  isUploading.value = true
  uploadStats.value = null

  try {
    // 1. Kompresi gambar di browser guru menggunakan Canvas API
    const comp = await compressImage(file, 1200, 800, 0.82)
    uploadStats.value = {
      original: `${(comp.originalSize / 1024).toFixed(0)} KB`,
      compressed: `${(comp.compressedSize / 1024).toFixed(0)} KB`,
      savings: `${Math.max(0, Math.round((1 - comp.compressedSize / comp.originalSize) * 100))}%`
    }

    // 2. Kirim berkas Blob WebP ke Backend API
    const formData = new FormData()
    const cleanBaseName = file.name.substring(0, file.name.lastIndexOf('.')) || 'soal_diagram'
    const ext = comp.mimeType === 'image/svg+xml' ? 'svg' : 'webp'
    formData.append('image', comp.blob, `${cleanBaseName}.${ext}`)
    formData.append('alt_text', altTextInput.value.trim() || cleanBaseName.replace(/[-_]/g, ' '))

    const res: any = await $fetch(`${apiBase}/quizzes/upload-question-image`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token.value}`,
        Accept: 'application/json'
      },
      body: formData
    })

    if (res?.success) {
      emit('update:modelValue', res.image_url)
      emit('update:imageMeta', res.meta)
      altTextInput.value = res.meta?.alt_text || ''
    }
  } catch (err: any) {
    console.error('Gagal mengunggah gambar soal:', err)
    alert(err?.data?.message || err?.message || 'Gagal memproses gambar soal')
  } finally {
    isUploading.value = false
  }
}

const onDrop = (e: DragEvent) => {
  dragOver.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) {
    processAndUploadFile(file)
  }
}

const onFileSelect = (e: Event) => {
  const target = e.target as HTMLInputElement
  const file = target.files?.[0]
  if (file) {
    processAndUploadFile(file)
  }
}

const removeImage = () => {
  emit('update:modelValue', null)
  emit('update:imageMeta', null)
  uploadStats.value = null
  altTextInput.value = ''
  isExpanded.value = false
  if (fileInputRef.value) {
    fileInputRef.value.value = ''
  }
}

// Fitur Usability Unggulan: Paste (Ctrl + V) langsung dari Clipboard (Snipping Tool)
const handlePasteEvent = (e: ClipboardEvent) => {
  // Hanya proses jika belum ada gambar atau user sedang fokus di area uploader
  if (props.modelValue) return
  const items = e.clipboardData?.items
  if (!items) return

  for (let i = 0; i < items.length; i++) {
    if (items[i].type.indexOf('image') !== -1) {
      const file = items[i].getAsFile()
      if (file) {
        e.preventDefault()
        processAndUploadFile(file)
        break
      }
    }
  }
}

onMounted(() => {
  if (uploaderContainerRef.value) {
    uploaderContainerRef.value.addEventListener('paste', handlePasteEvent)
  }
})

onUnmounted(() => {
  if (uploaderContainerRef.value) {
    uploaderContainerRef.value.removeEventListener('paste', handlePasteEvent)
  }
})
</script>

<template>
  <div ref="uploaderContainerRef" class="my-1.5">
    <!-- MODE 0: DEFAULT BERSIH (HANYA TOMBOL + TAMBAH LAMPIRAN GAMBAR) -->
    <div v-if="!modelValue && !isExpanded" class="flex items-center">
      <button
        type="button"
        @click="isExpanded = true"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-dashed border-slate-300 hover:border-emerald-500 hover:bg-emerald-50/70 text-slate-600 hover:text-emerald-700 text-xs font-bold transition shadow-2xs group cursor-pointer"
        title="Klik jika butir soal ini membutuhkan gambar ilustrasi atau diagram pendukung"
      >
        <svg class="w-4 h-4 text-slate-400 group-hover:text-emerald-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <span>+ Tambah Lampiran Gambar (Opsional)</span>
      </button>
    </div>

    <!-- MODE AKTIF: JIKA DIKLIK BUKA ATAU SUDAH ADA GAMBAR TERLAMPIR -->
    <div v-else class="space-y-2">
      <div class="flex items-center justify-between">
        <label class="block text-xs font-bold text-slate-700 flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <span>Lampiran Gambar Soal (Opsional)</span>
        </label>
        <div class="flex items-center gap-2">
          <span v-if="modelValue" class="text-[11px] text-emerald-700 font-bold flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Gambar Berhasil Terlampir
          </span>
          <button
            v-if="!modelValue"
            type="button"
            @click="isExpanded = false"
            class="px-2 py-0.5 rounded text-[11px] font-bold text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer"
          >
            Tutup
          </button>
        </div>
      </div>

    <!-- STATE 1: BELUM ADA GAMBAR (DRAG, DROP, PASTE, PICKER) -->
    <div
      v-if="!modelValue"
      @dragover.prevent="dragOver = true"
      @dragleave.prevent="dragOver = false"
      @drop.prevent="onDrop"
      tabindex="0"
      :class="[
        'border-2 border-dashed rounded-2xl p-4 text-center transition cursor-pointer flex flex-col items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-emerald-500',
        dragOver ? 'border-emerald-500 bg-emerald-50/70 scale-[0.99]' : 'border-slate-300 hover:border-slate-400 bg-slate-50/60'
      ]"
      @click="fileInputRef?.click()"
    >
      <input
        ref="fileInputRef"
        type="file"
        accept="image/png,image/jpeg,image/webp,image/svg+xml"
        class="hidden"
        @change="onFileSelect"
      />

      <div class="w-10 h-10 rounded-2xl bg-white text-slate-500 shadow-2xs border border-slate-200/80 flex items-center justify-center">
        <svg v-if="!isUploading" class="w-5 h-5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <svg v-else class="w-5 h-5 animate-spin text-emerald-600" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <div class="text-xs text-slate-700">
        <span class="font-bold text-slate-900">Klik pilih berkas</span>, drag & drop diagram, atau tekan <kbd class="px-1.5 py-0.5 rounded-md bg-white border border-slate-200 text-slate-700 font-mono text-[10px] font-bold shadow-2xs">Ctrl + V</kbd> setelah screenshot
      </div>
      <p class="text-[11px] text-slate-400">
        Format JPG, PNG, WebP (Otomatis dikompresi ringan & tajam untuk kecepatan ujian siswa)
      </p>
    </div>

    <!-- STATE 2: SUDAH ADA GAMBAR TERLAMPIR (PREVIEW, STATS, ACTIONS) -->
    <div v-else class="rounded-2xl border border-slate-200 bg-white p-3.5 space-y-3 shadow-2xs">
      <div class="flex flex-col sm:flex-row items-center gap-4">
        <!-- Thumbnail Preview -->
        <div class="relative w-full sm:w-44 h-32 rounded-xl overflow-hidden bg-slate-50 border border-slate-200/80 shrink-0 flex items-center justify-center group">
          <img
            :src="getFullUrl(modelValue)"
            alt="Preview Diagram Soal"
            class="w-full h-full object-contain p-1.5"
            loading="lazy"
          />
          <a
            :href="getFullUrl(modelValue)"
            target="_blank"
            rel="noopener noreferrer"
            class="absolute inset-0 bg-slate-950/60 text-white opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-1.5 text-xs font-bold"
            title="Buka gambar ukuran penuh di tab baru"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
            <span>Ukuran Penuh</span>
          </a>
        </div>

        <!-- Info & Controls -->
        <div class="flex-1 min-w-0 space-y-2 w-full">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-mono font-bold border border-emerald-200 flex items-center gap-1">
              <svg class="w-3 h-3 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
              WebP High Performance
            </span>
            <span v-if="imageMeta?.width && imageMeta?.height" class="text-xs text-slate-500 font-mono">
              {{ imageMeta.width }} × {{ imageMeta.height }} px
            </span>
            <span v-if="imageMeta?.size_kb" class="text-xs text-slate-500 font-mono font-bold">
              • {{ imageMeta.size_kb }} KB
            </span>
          </div>

          <div v-if="uploadStats" class="text-xs text-emerald-700 bg-emerald-50/70 p-2 rounded-xl border border-emerald-100 leading-snug">
            Terkonversi: <span class="line-through text-slate-400 font-mono">{{ uploadStats.original }}</span> → <b class="font-mono">{{ uploadStats.compressed }}</b> (Hemat {{ uploadStats.savings }} beban jaringan siswa)
          </div>

          <p class="text-[11px] text-slate-400 font-mono truncate">
            {{ modelValue }}
          </p>

          <div class="pt-1 flex items-center gap-2 flex-wrap">
            <button
              type="button"
              @click="removeImage"
              class="px-3 py-1.5 rounded-xl text-xs font-bold text-rose-600 hover:bg-rose-50 transition border border-rose-200 flex items-center gap-1 cursor-pointer"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
              <span>Hapus Gambar</span>
            </button>
            <button
              type="button"
              @click="fileInputRef?.click()"
              class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-100 transition border border-slate-200 flex items-center gap-1 cursor-pointer"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <span>Ganti Gambar</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Input Keterangan / Alt Text Gambar -->
      <div class="pt-2 border-t border-slate-100">
        <label class="block text-[11px] font-bold text-slate-600 mb-1">
          Keterangan / Alt Teks Gambar (Membantu Siswa & Aksesibilitas)
        </label>
        <input
          v-model="altTextInput"
          @blur="updateAltText"
          type="text"
          placeholder="Contoh: Diagram penampang membran plasma dan protein integral"
          class="w-full px-3 py-1.5 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-medium"
        />
      </div>
    </div>
  </div>
  </div>
</template>
