<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

definePageMeta({ layout: 'teacher', middleware: 'auth' })

const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const backendUrl = config.public.backendUrl || 'http://localhost:8000'

interface Subject {
  id: number
  code: string
  name: string
}

interface MaterialItem {
  id: number
  teacher_id: number
  subject_id: number
  grade_level: string
  title: string
  type: 'pdf' | 'video' | 'document' | 'link' | 'text'
  file_path: string | null
  external_url: string | null
  content_text: string | null
  is_public: boolean
  created_at: string
  subject?: Subject
  teacher?: { id: number; name: string }
}

const loading = ref(true)
const actionLoading = ref(false)
const materials = ref<MaterialItem[]>([])
const subjects = ref<Subject[]>([])

// Filter State
const activeTab = ref<'all' | 'my' | 'public'>('my')
const search = ref('')
const selectedGrade = ref('')
const selectedSubject = ref('')
const selectedType = ref('')

// Modal Form State
const isModalOpen = ref(false)
const editingId = ref<number | null>(null)
const form = ref({
  subject_id: '',
  grade_level: '11',
  title: '',
  type: 'pdf' as 'pdf' | 'video' | 'document' | 'link' | 'text',
  external_url: '',
  content_text: '',
  is_public: false,
})
const selectedFile = ref<File | null>(null)
const formError = ref('')

// Preview Modal State
const previewItem = ref<MaterialItem | null>(null)
const isPreviewOpen = ref(false)

const api = async <T>(path: string, options: any = {}) => {
  return await $fetch<T>(`${apiBase}${path}`, {
    ...options,
    headers: {
      Authorization: `Bearer ${token.value}`,
      Accept: 'application/json',
      ...options.headers,
    },
  })
}

const fetchData = async () => {
  loading.value = true
  try {
    const [matRes, subjRes] = await Promise.all([
      api<any>(`/material-banks?scope=${activeTab.value}&paginate=false`),
      api<any>('/subjects?all=true')
    ])
    materials.value = matRes?.data || []
    subjects.value = subjRes?.data || subjRes || []
  } catch (err: any) {
    console.error('Gagal memuat bank materi:', err)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchData()
})

const onTabChange = (tab: 'all' | 'my' | 'public') => {
  activeTab.value = tab
  fetchData()
}

// Filtered Materials
const filteredMaterials = computed(() => {
  return materials.value.filter(item => {
    const matchSearch = !search.value || 
      item.title.toLowerCase().includes(search.value.toLowerCase()) ||
      (item.subject?.name && item.subject.name.toLowerCase().includes(search.value.toLowerCase())) ||
      (item.content_text && item.content_text.toLowerCase().includes(search.value.toLowerCase()))
    
    const matchGrade = !selectedGrade.value || item.grade_level === selectedGrade.value
    const matchSubject = !selectedSubject.value || String(item.subject_id) === String(selectedSubject.value)
    const matchType = !selectedType.value || item.type === selectedType.value

    return matchSearch && matchGrade && matchSubject && matchType
  })
})

// Stats
const totalPdf = computed(() => materials.value.filter(m => m.type === 'pdf').length)
const totalVideo = computed(() => materials.value.filter(m => m.type === 'video').length)
const totalDocs = computed(() => materials.value.filter(m => m.type === 'document' || m.type === 'text').length)

const openAddModal = () => {
  editingId.value = null
  form.value = {
    subject_id: subjects.value[0]?.id ? String(subjects.value[0].id) : '',
    grade_level: '11',
    title: '',
    type: 'pdf',
    external_url: '',
    content_text: '',
    is_public: false,
  }
  selectedFile.value = null
  formError.value = ''
  isModalOpen.value = true
}

const openEditModal = (item: MaterialItem) => {
  editingId.value = item.id
  form.value = {
    subject_id: String(item.subject_id),
    grade_level: item.grade_level,
    title: item.title,
    type: item.type,
    external_url: item.external_url || '',
    content_text: item.content_text || '',
    is_public: item.is_public,
  }
  selectedFile.value = null
  formError.value = ''
  isModalOpen.value = true
}

const onFileChange = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.files && target.files[0]) {
    selectedFile.value = target.files[0]
  }
}

const saveMaterial = async () => {
  if (!form.value.title || !form.value.subject_id) {
    formError.value = 'Judul dan Mata Pelajaran wajib diisi.'
    return
  }

  actionLoading.value = true
  formError.value = ''

  try {
    const formData = new FormData()
    formData.append('subject_id', form.value.subject_id)
    formData.append('grade_level', form.value.grade_level)
    formData.append('title', form.value.title)
    formData.append('type', form.value.type)
    formData.append('is_public', form.value.is_public ? '1' : '0')

    if (form.value.external_url) {
      formData.append('external_url', form.value.external_url)
    }
    if (form.value.content_text) {
      formData.append('content_text', form.value.content_text)
    }
    if (selectedFile.value) {
      formData.append('file', selectedFile.value)
    }

    if (editingId.value) {
      formData.append('_method', 'PUT')
      await $fetch(`${apiBase}/material-banks/${editingId.value}`, {
        method: 'POST',
        body: formData,
        headers: {
          Authorization: `Bearer ${token.value}`,
          Accept: 'application/json',
        }
      })
    } else {
      await $fetch(`${apiBase}/material-banks`, {
        method: 'POST',
        body: formData,
        headers: {
          Authorization: `Bearer ${token.value}`,
          Accept: 'application/json',
        }
      })
    }

    isModalOpen.value = false
    await fetchData()
  } catch (err: any) {
    formError.value = err?.data?.message || 'Gagal menyimpan bahan ajar.'
  } finally {
    actionLoading.value = false
  }
}

const deleteMaterial = async (id: number) => {
  if (!confirm('Apakah Anda yakin ingin menghapus materi ini dari Bank Materi?')) return
  try {
    await api(`/material-banks/${id}`, { method: 'DELETE' })
    await fetchData()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal menghapus materi')
  }
}

const openPreview = (item: MaterialItem) => {
  previewItem.value = item
  isPreviewOpen.value = true
}

const getFileUrl = (path: string | null) => {
  if (!path) return ''
  if (path.startsWith('http')) return path
  return `${backendUrl}/storage/${path}`
}

const getTypeBadge = (type: string) => {
  switch (type) {
    case 'pdf':
      return { bg: 'bg-rose-50 text-rose-700 border-rose-200', label: 'PDF Modul' }
    case 'video':
      return { bg: 'bg-purple-50 text-purple-700 border-purple-200', label: 'Video YouTube' }
    case 'document':
      return { bg: 'bg-blue-50 text-blue-700 border-blue-200', label: 'Dokumen' }
    case 'link':
      return { bg: 'bg-emerald-50 text-emerald-700 border-emerald-200', label: 'Tautan Web' }
    default:
      return { bg: 'bg-slate-100 text-slate-700 border-slate-200', label: 'Teks Catatan' }
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header Title & Info -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-1">
          <span>Repository Pembelajaran</span>
          <span>•</span>
          <span>Bahan Ajar Reusable</span>
        </div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Bank Materi Pembelajaran</h1>
        <p class="text-xs text-slate-500 mt-1">
          Arsipkan modul PDF, presentasi PPT, artikel, dan video interaktif untuk disisipkan ke pertemuan kelas mana saja.
        </p>
      </div>

      <button
        @click="openAddModal"
        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white font-bold text-xs shadow-md shadow-emerald-600/20 hover:bg-emerald-700 transition"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Tambah / Upload Materi
      </button>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
      <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-xs">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Bahan Ajar</span>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-3xl font-extrabold text-slate-900">{{ loading ? '...' : materials.length }}</span>
          <span class="text-xs text-slate-500">item tersimpan</span>
        </div>
      </div>
      <div class="p-5 rounded-2xl bg-white border border-rose-100 shadow-xs">
        <span class="text-xs font-semibold uppercase tracking-wider text-rose-600">Modul PDF</span>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-3xl font-extrabold text-rose-800">{{ loading ? '...' : totalPdf }}</span>
          <span class="text-xs text-rose-600">dokumen PDF</span>
        </div>
      </div>
      <div class="p-5 rounded-2xl bg-white border border-purple-100 shadow-xs">
        <span class="text-xs font-semibold uppercase tracking-wider text-purple-600">Video Pembelajaran</span>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-3xl font-extrabold text-purple-800">{{ loading ? '...' : totalVideo }}</span>
          <span class="text-xs text-purple-600">video/tautan</span>
        </div>
      </div>
      <div class="p-5 rounded-2xl bg-white border border-emerald-100 shadow-xs">
        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Dokumen & Catatan</span>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-3xl font-extrabold text-emerald-800">{{ loading ? '...' : totalDocs }}</span>
          <span class="text-xs text-emerald-600">materi teks/doc</span>
        </div>
      </div>
    </div>

    <!-- Scope Tabs (Materi Saya vs Publik Madrasah) -->
    <div class="flex items-center gap-2 border-b border-slate-200">
      <button
        @click="onTabChange('my')"
        :class="[
          'px-4 py-2.5 text-xs font-bold border-b-2 transition flex items-center gap-2',
          activeTab === 'my'
            ? 'border-emerald-600 text-emerald-700'
            : 'border-transparent text-slate-500 hover:text-slate-700'
        ]"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        Materi Saya
      </button>

      <button
        @click="onTabChange('public')"
        :class="[
          'px-4 py-2.5 text-xs font-bold border-b-2 transition flex items-center gap-2',
          activeTab === 'public'
            ? 'border-emerald-600 text-emerald-700'
            : 'border-transparent text-slate-500 hover:text-slate-700'
        ]"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Bank Materi Madrasah (Publik)
      </button>

      <button
        @click="onTabChange('all')"
        :class="[
          'px-4 py-2.5 text-xs font-bold border-b-2 transition',
          activeTab === 'all'
            ? 'border-emerald-600 text-emerald-700'
            : 'border-transparent text-slate-500 hover:text-slate-700'
        ]"
      >
        Semua Koleksi
      </button>
    </div>

    <!-- Filter & Search Bar -->
    <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col md:flex-row gap-3">
      <div class="flex-1 relative">
        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          v-model="search"
          type="text"
          placeholder="Cari judul materi, deskripsi, atau topik..."
          class="w-full pl-9 pr-4 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
        />
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <select
          v-model="selectedSubject"
          class="px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="">Semua Mata Pelajaran</option>
          <option v-for="subj in subjects" :key="subj.id" :value="subj.id">
            {{ subj.name }}
          </option>
        </select>

        <select
          v-model="selectedGrade"
          class="px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="">Semua Tingkat</option>
          <option value="10">Kelas 10</option>
          <option value="11">Kelas 11</option>
          <option value="12">Kelas 12</option>
        </select>

        <select
          v-model="selectedType"
          class="px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="">Semua Format</option>
          <option value="pdf">PDF Modul</option>
          <option value="video">Video YouTube</option>
          <option value="document">Dokumen / PPT</option>
          <option value="link">Tautan Web</option>
          <option value="text">Teks Catatan</option>
        </select>
      </div>
    </div>

    <!-- Materials Grid -->
    <div v-if="loading" class="p-12 text-center text-xs text-slate-400 bg-white rounded-2xl border border-slate-100">
      <svg class="animate-spin h-6 w-6 text-emerald-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      Memuat koleksi materi...
    </div>

    <div v-else-if="filteredMaterials.length === 0" class="p-12 text-center bg-white rounded-2xl border border-slate-200/80">
      <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
        </svg>
      </div>
      <h3 class="text-sm font-bold text-slate-800">Tidak ada materi ditemukan</h3>
      <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
        Silakan unggah dokumen atau tambahkan tautan video pertama Anda ke dalam bank materi.
      </p>
      <button
        @click="openAddModal"
        class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 text-xs font-bold rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100"
      >
        + Tambah Materi Sekarang
      </button>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="item in filteredMaterials"
        :key="item.id"
        class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between hover:shadow-md hover:border-emerald-300 transition-all group"
      >
        <div>
          <!-- Type Badge & Grade Badge -->
          <div class="flex items-center justify-between gap-2 mb-3">
            <span :class="['px-2.5 py-1 rounded-md border text-[10px] font-extrabold uppercase', getTypeBadge(item.type).bg]">
              {{ getTypeBadge(item.type).label }}
            </span>
            <div class="flex items-center gap-1.5">
              <span v-if="item.is_public" class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800" title="Materi Dibagikan ke Guru Lain">
                Publik
              </span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                Kelas {{ item.grade_level }}
              </span>
            </div>
          </div>

          <!-- Title & Subject -->
          <h3 class="font-extrabold text-slate-900 text-sm leading-snug line-clamp-2">{{ item.title }}</h3>
          <p class="text-xs font-semibold text-emerald-700 mt-1">
            {{ item.subject?.name || 'Mata Pelajaran' }}
          </p>

          <p v-if="item.content_text" class="text-[11px] text-slate-500 mt-2 line-clamp-3 leading-relaxed">
            {{ item.content_text }}
          </p>
          <p v-else-if="item.external_url" class="text-[11px] text-slate-400 mt-2 truncate">
            🔗 {{ item.external_url }}
          </p>
        </div>

        <!-- Footer Actions -->
        <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
          <button
            @click="openPreview(item)"
            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 text-slate-700 hover:bg-emerald-600 hover:text-white text-xs font-bold transition"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            Pratinjau
          </button>

          <div class="flex items-center gap-1">
            <button
              @click="openEditModal(item)"
              class="p-2 rounded-xl text-slate-400 hover:text-emerald-700 hover:bg-emerald-50 transition"
              title="Edit"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>
            <button
              @click="deleteMaterial(item.id)"
              class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
              title="Hapus"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Form Tambah / Edit Materi -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in"
    >
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="font-extrabold text-slate-900 text-sm">
            {{ editingId ? 'Edit Bahan Ajar' : 'Tambah Bahan Ajar ke Bank Materi' }}
          </h3>
          <button @click="isModalOpen = false" class="text-slate-400 hover:text-slate-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div v-if="formError" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700">
          {{ formError }}
        </div>

        <form @submit.prevent="saveMaterial" class="space-y-3.5 text-xs">
          <!-- Judul Materi -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Judul Bahan Ajar *</label>
            <input
              v-model="form.title"
              type="text"
              placeholder="Contoh: Modul Limit Fungsi Aljabar & Pembahasan Soal"
              required
              class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <!-- Mata Pelajaran -->
            <div>
              <label class="block font-bold text-slate-700 mb-1">Mata Pelajaran *</label>
              <select
                v-model="form.subject_id"
                required
                class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
              >
                <option value="" disabled>Pilih Mapel</option>
                <option v-for="subj in subjects" :key="subj.id" :value="String(subj.id)">
                  {{ subj.name }}
                </option>
              </select>
            </div>

            <!-- Tingkat Kelas -->
            <div>
              <label class="block font-bold text-slate-700 mb-1">Tingkat Kelas *</label>
              <select
                v-model="form.grade_level"
                required
                class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
              >
                <option value="10">Kelas 10</option>
                <option value="11">Kelas 11</option>
                <option value="12">Kelas 12</option>
              </select>
            </div>
          </div>

          <!-- Format / Tipe Bahan Ajar -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Format Materi *</label>
            <div class="grid grid-cols-5 gap-2">
              <button
                type="button"
                v-for="t in [
                  { key: 'pdf', label: 'PDF' },
                  { key: 'video', label: 'Video' },
                  { key: 'document', label: 'Doc/PPT' },
                  { key: 'link', label: 'Tautan' },
                  { key: 'text', label: 'Teks' },
                ]"
                :key="t.key"
                @click="form.type = t.key as any"
                :class="[
                  'py-2 rounded-xl border font-bold text-center transition',
                  form.type === t.key
                    ? 'border-emerald-600 bg-emerald-50 text-emerald-800 ring-2 ring-emerald-500/20'
                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                ]"
              >
                {{ t.label }}
              </button>
            </div>
          </div>

          <!-- Input File jika PDF/Document -->
          <div v-if="form.type === 'pdf' || form.type === 'document'">
            <label class="block font-bold text-slate-700 mb-1">File Dokumen (PDF, PPT, Word, dll.)</label>
            <input
              type="file"
              @change="onFileChange"
              class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
            />
          </div>

          <!-- Input External URL jika Video / Link -->
          <div v-if="form.type === 'video' || form.type === 'link'">
            <label class="block font-bold text-slate-700 mb-1">
              {{ form.type === 'video' ? 'Link Video (YouTube / Google Drive)' : 'Tautan / URL Web' }} *
            </label>
            <input
              v-model="form.external_url"
              type="url"
              placeholder="https://www.youtube.com/watch?v=..."
              class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
          </div>

          <!-- Input Teks Ringkasan / Deskripsi -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Catatan / Ringkasan Materi</label>
            <textarea
              v-model="form.content_text"
              rows="3"
              placeholder="Tambahkan catatan poin-poin penting dari materi ini..."
              class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            ></textarea>
          </div>

          <!-- Toggle Publik Madrasah -->
          <label class="flex items-center gap-2 cursor-pointer pt-1">
            <input
              type="checkbox"
              v-model="form.is_public"
              class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300"
            />
            <span class="font-bold text-slate-700">Jadikan materi publik di repository madrasah (bisa diakses guru lain)</span>
          </label>

          <!-- Action Buttons -->
          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="isModalOpen = false"
              class="px-4 py-2 rounded-xl border border-slate-200 font-semibold text-slate-600 hover:bg-slate-50 transition"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="actionLoading"
              class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold shadow-md shadow-emerald-600/20 hover:bg-emerald-700 disabled:opacity-50 transition"
            >
              {{ actionLoading ? 'Menyimpan...' : 'Simpan Bahan Ajar' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Pratinjau Materi -->
    <div
      v-if="isPreviewOpen && previewItem"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in"
    >
      <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-100 space-y-4 max-h-[90vh] flex flex-col justify-between">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', getTypeBadge(previewItem.type).bg]">
              {{ getTypeBadge(previewItem.type).label }}
            </span>
            <h3 class="font-extrabold text-slate-900 text-base mt-1">{{ previewItem.title }}</h3>
            <p class="text-xs text-emerald-700 font-semibold">{{ previewItem.subject?.name }} · Kelas {{ previewItem.grade_level }}</p>
          </div>
          <button @click="isPreviewOpen = false" class="text-slate-400 hover:text-slate-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="flex-1 overflow-y-auto space-y-4 text-xs text-slate-700">
          <div v-if="previewItem.content_text" class="p-4 rounded-2xl bg-slate-50 border border-slate-200/70 whitespace-pre-wrap leading-relaxed">
            {{ previewItem.content_text }}
          </div>

          <div v-if="previewItem.file_path" class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold">
                📄
              </div>
              <div>
                <p class="font-bold text-emerald-950">File Bahan Ajar Terlampir</p>
                <p class="text-[11px] text-emerald-700 truncate max-w-xs">{{ previewItem.file_path }}</p>
              </div>
            </div>
            <a
              :href="getFileUrl(previewItem.file_path)"
              target="_blank"
              class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 transition"
            >
              Unduh / Buka File
            </a>
          </div>

          <div v-if="previewItem.external_url" class="p-4 rounded-2xl bg-purple-50 border border-purple-200 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-purple-600 text-white flex items-center justify-center font-bold">
                🎥
              </div>
              <div class="min-w-0">
                <p class="font-bold text-purple-950">Tautan Eksternal / Video</p>
                <p class="text-[11px] text-purple-700 truncate max-w-sm">{{ previewItem.external_url }}</p>
              </div>
            </div>
            <a
              :href="previewItem.external_url"
              target="_blank"
              class="px-4 py-2 rounded-xl bg-purple-600 text-white font-bold hover:bg-purple-700 transition shrink-0"
            >
              Buka Tautan ↗
            </a>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex justify-end">
          <button
            @click="isPreviewOpen = false"
            class="px-5 py-2 rounded-xl bg-slate-100 text-slate-700 font-bold hover:bg-slate-200 transition text-xs"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
