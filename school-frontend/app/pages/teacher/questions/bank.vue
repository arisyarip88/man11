<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

definePageMeta({ layout: 'teacher', middleware: 'auth' })

const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'

interface Subject {
  id: number
  code: string
  name: string
}

interface QuestionBank {
  id: number
  teacher_id: number
  subject_id: number
  grade_level: string
  difficulty?: 'easy' | 'medium' | 'hard' | null
  title: string
  description: string
  is_public: boolean
  items_count?: number
  difficulty_summary?: {
    total: number
    easy: number
    medium: number
    hard: number
    easy_pct: number
    medium_pct: number
    hard_pct: number
  }
  created_at: string
  subject?: Subject
  teacher?: { id: number; name: string }
}

const loading = ref(true)
const actionLoading = ref(false)
const questionBanks = ref<QuestionBank[]>([])
const subjects = ref<Subject[]>([])

// Filter State
const activeTab = ref<'all' | 'my' | 'public'>('my')
const search = ref('')
const selectedGrade = ref('')
const selectedSubject = ref('')
const selectedDifficulty = ref('')

// Modal Form State
const isModalOpen = ref(false)
const editingId = ref<number | null>(null)
const form = ref({
  subject_id: '',
  grade_level: '11',
  difficulty: '',
  title: '',
  description: '',
  is_public: false,
})
const formError = ref('')

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
    const [bankRes, subjRes] = await Promise.all([
      api<any>(`/question-banks?scope=${activeTab.value}&paginate=false`),
      api<any>('/subjects?all=true')
    ])
    questionBanks.value = bankRes?.data || []
    subjects.value = subjRes?.data || subjRes || []
  } catch (err: any) {
    console.error('Gagal memuat bank soal:', err)
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

// Filtered Question Banks
const filteredBanks = computed(() => {
  return questionBanks.value.filter(item => {
    const matchSearch = !search.value || 
      item.title.toLowerCase().includes(search.value.toLowerCase()) ||
      (item.subject?.name && item.subject.name.toLowerCase().includes(search.value.toLowerCase())) ||
      (item.description && item.description.toLowerCase().includes(search.value.toLowerCase()))
    
    const matchGrade = !selectedGrade.value || item.grade_level === selectedGrade.value
    const matchSubject = !selectedSubject.value || String(item.subject_id) === String(selectedSubject.value)
    const matchDifficulty = !selectedDifficulty.value || item.difficulty === selectedDifficulty.value

    return matchSearch && matchGrade && matchSubject && matchDifficulty
  })
})

// Stats
const totalItemsCount = computed(() => {
  return questionBanks.value.reduce((acc, curr) => acc + (curr.items_count || 0), 0)
})

const openAddModal = () => {
  editingId.value = null
  form.value = {
    subject_id: subjects.value[0]?.id ? String(subjects.value[0].id) : '',
    grade_level: '11',
    difficulty: '',
    title: '',
    description: '',
    is_public: false,
  }
  formError.value = ''
  isModalOpen.value = true
}

const openEditModal = (item: QuestionBank) => {
  editingId.value = item.id
  form.value = {
    subject_id: String(item.subject_id),
    grade_level: item.grade_level,
    difficulty: item.difficulty || '',
    title: item.title,
    description: item.description || '',
    is_public: item.is_public,
  }
  formError.value = ''
  isModalOpen.value = true
}

const saveQuestionBank = async () => {
  if (!form.value.title || !form.value.subject_id) {
    formError.value = 'Judul paket soal dan Mata Pelajaran wajib diisi.'
    return
  }

  actionLoading.value = true
  formError.value = ''

  try {
    if (editingId.value) {
      await api(`/question-banks/${editingId.value}`, {
        method: 'PUT',
        body: form.value,
      })
    } else {
      await api('/question-banks', {
        method: 'POST',
        body: form.value,
      })
    }
    isModalOpen.value = false
    await fetchData()
  } catch (err: any) {
    formError.value = err?.data?.message || 'Gagal menyimpan paket bank soal.'
  } finally {
    actionLoading.value = false
  }
}

const deleteQuestionBank = async (id: number) => {
  if (!confirm('Apakah Anda yakin ingin menghapus paket Bank Soal ini? Seluruh butir soal di dalamnya akan terhapus.')) return
  try {
    await api(`/question-banks/${id}`, { method: 'DELETE' })
    await fetchData()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal menghapus paket bank soal')
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header Title & Info -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-1">
          <span>Repository Asesmen</span>
          <span>•</span>
          <span>Bank Soal Terintegrasi</span>
        </div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Bank Soal & Asesmen</h1>
        <p class="text-xs text-slate-500 mt-1">
          Koleksi butir soal (Pilihan Ganda, PG Kompleks, Esai) yang siap diimpor ke kuis pertemuan atau ujian CBT madrasah.
        </p>
      </div>

      <button
        @click="openAddModal"
        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white font-bold text-xs shadow-md shadow-emerald-600/20 hover:bg-emerald-700 transition"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Buat Paket Soal Baru
      </button>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-xs">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Paket Bank Soal</span>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-3xl font-extrabold text-slate-900">{{ loading ? '...' : questionBanks.length }}</span>
          <span class="text-xs text-slate-500">paket soal</span>
        </div>
      </div>
      <div class="p-5 rounded-2xl bg-white border border-emerald-100 shadow-xs">
        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Total Butir Pertanyaan</span>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-3xl font-extrabold text-emerald-800">{{ loading ? '...' : totalItemsCount }}</span>
          <span class="text-xs text-emerald-600">butir soal siap pakai</span>
        </div>
      </div>
      <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-xs">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Tipe Asesmen Didukung</span>
        <div class="mt-2 flex items-center gap-1.5 flex-wrap">
          <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">Pilihan Ganda</span>
          <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-800">PG Kompleks</span>
          <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-800">Esai</span>
        </div>
      </div>
    </div>

    <!-- Tabs -->
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
        Bank Soal Saya
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
        Bank Soal Madrasah (Publik)
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
          placeholder="Cari judul paket soal atau topik kompetensi..."
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

        <!-- B6: Filter Tingkat Kesulitan -->
        <select
          v-model="selectedDifficulty"
          class="px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-medium"
        >
          <option value="">Semua Kesulitan</option>
          <option value="easy">🟢 Mudah</option>
          <option value="medium">🟡 Sedang</option>
          <option value="hard">🔴 Sulit</option>
        </select>
      </div>
    </div>

    <!-- Question Banks Grid -->
    <div v-if="loading" class="p-12 text-center text-xs text-slate-400 bg-white rounded-2xl border border-slate-100">
      <svg class="animate-spin h-6 w-6 text-emerald-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      Memuat daftar paket bank soal...
    </div>

    <div v-else-if="filteredBanks.length === 0" class="p-12 text-center bg-white rounded-2xl border border-slate-200/80">
      <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
        </svg>
      </div>
      <h3 class="text-sm font-bold text-slate-800">Belum ada paket Bank Soal</h3>
      <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
        Buat paket bank soal pertama Anda dan masukkan butir-butir pertanyaan pilihan ganda atau esai.
      </p>
      <button
        @click="openAddModal"
        class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 text-xs font-bold rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100"
      >
        + Buat Paket Soal
      </button>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="bank in filteredBanks"
        :key="bank.id"
        class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between hover:shadow-md hover:border-emerald-300 transition-all group"
      >
        <div>
          <!-- Header Card -->
          <div class="flex items-center justify-between gap-2 mb-3">
            <span class="px-2.5 py-1 rounded-md bg-emerald-50 border border-emerald-200/60 font-mono text-[10px] font-extrabold text-emerald-800">
              ID #BS-{{ bank.id }}
            </span>
            <div class="flex items-center gap-1.5 flex-wrap justify-end">
              <!-- B5: Badge Kesulitan Paket -->
              <span
                v-if="bank.difficulty"
                class="text-[9px] font-bold px-2 py-0.5 rounded-full capitalize"
                :class="{
                  'bg-emerald-100 text-emerald-800 border border-emerald-200': bank.difficulty === 'easy',
                  'bg-amber-100 text-amber-800 border border-amber-200': bank.difficulty === 'medium',
                  'bg-rose-100 text-rose-800 border border-rose-200': bank.difficulty === 'hard',
                }"
              >
                {{ bank.difficulty === 'easy' ? '🟢 Mudah' : (bank.difficulty === 'medium' ? '🟡 Sedang' : '🔴 Sulit') }}
              </span>
              <span v-if="bank.is_public" class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800">
                Publik
              </span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                Kelas {{ bank.grade_level }}
              </span>
            </div>
          </div>

          <!-- Title & Subject -->
          <h3 class="font-extrabold text-slate-900 text-sm leading-snug">{{ bank.title }}</h3>
          <p class="text-xs font-semibold text-emerald-700 mt-0.5">
            {{ bank.subject?.name || 'Mata Pelajaran' }}
          </p>

          <p v-if="bank.description" class="text-[11px] text-slate-500 mt-2 line-clamp-2 leading-relaxed">
            {{ bank.description }}
          </p>

          <!-- B5: Bar Komposisi Mini Kesulitan Butir Soal -->
          <div v-if="bank.difficulty_summary && bank.difficulty_summary.total > 0" class="mt-3.5 p-2 bg-slate-50/70 border border-slate-100 rounded-xl space-y-1.5">
            <div class="flex items-center justify-between text-[10px] text-slate-500 font-medium">
              <span>Rasio Butir Soal:</span>
              <span class="font-mono text-[10px]">
                <span class="text-emerald-700 font-bold">{{ bank.difficulty_summary.easy }} Mudah</span> • 
                <span class="text-amber-700 font-bold">{{ bank.difficulty_summary.medium }} Sedang</span> • 
                <span class="text-rose-700 font-bold">{{ bank.difficulty_summary.hard }} Sulit</span>
              </span>
            </div>
            <div class="w-full h-1.5 rounded-full bg-slate-200 overflow-hidden flex">
              <div :style="{ width: bank.difficulty_summary.easy_pct + '%' }" class="bg-emerald-500 h-full" title="Mudah"></div>
              <div :style="{ width: bank.difficulty_summary.medium_pct + '%' }" class="bg-amber-400 h-full" title="Sedang"></div>
              <div :style="{ width: bank.difficulty_summary.hard_pct + '%' }" class="bg-rose-500 h-full" title="Sulit"></div>
            </div>
          </div>

          <!-- Total Items Badge -->
          <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
            <span class="text-[11px] font-bold text-slate-500">Jumlah Soal Tersimpan:</span>
            <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-extrabold text-xs">
              {{ bank.items_count || 0 }} Butir Soal
            </span>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
          <NuxtLink
            :to="`/teacher/questions/${bank.id}/items`"
            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white text-xs font-bold transition group"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Kelola Butir Soal
          </NuxtLink>

          <div class="flex items-center gap-1">
            <button
              @click="openEditModal(bank)"
              class="p-2 rounded-xl text-slate-400 hover:text-emerald-700 hover:bg-emerald-50 transition"
              title="Edit Paket"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
              </svg>
            </button>
            <button
              @click="deleteQuestionBank(bank.id)"
              class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
              title="Hapus Paket"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Form Buat / Edit Paket Bank Soal -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in"
    >
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="font-extrabold text-slate-900 text-sm">
            {{ editingId ? 'Edit Paket Bank Soal' : 'Buat Paket Bank Soal Baru' }}
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

        <form @submit.prevent="saveQuestionBank" class="space-y-3.5 text-xs">
          <!-- Judul Paket -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Judul Paket Soal *</label>
            <input
              v-model="form.title"
              type="text"
              placeholder="Contoh: Bank Soal Trigonometri & Geometri Analitik"
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

          <!-- B4: Tingkat Kesulitan Paket Soal -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Tingkat Kesulitan Paket</label>
            <select
              v-model="form.difficulty"
              class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 font-medium"
            >
              <option value="">Bebas / Campuran</option>
              <option value="easy">🟢 Mudah (Dasar & Konseptual)</option>
              <option value="medium">🟡 Sedang (Penerapan & Prosedural)</option>
              <option value="hard">🔴 Sulit (Penalaran Tinggi / HOTS)</option>
            </select>
          </div>

          <!-- Deskripsi -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Deskripsi / Cakupan Materi</label>
            <textarea
              v-model="form.description"
              rows="3"
              placeholder="Jelaskan kisi-kisi atau cakupan topik soal pada paket ini..."
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
            <span class="font-bold text-slate-700">Jadikan paket soal publik untuk digunakan guru lain</span>
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
              {{ actionLoading ? 'Menyimpan...' : 'Simpan Paket Soal' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
