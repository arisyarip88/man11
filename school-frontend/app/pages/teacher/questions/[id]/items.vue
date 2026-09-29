<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

definePageMeta({ layout: 'teacher', middleware: 'auth' })

const route = useRoute()
const bankId = computed(() => route.params.id)

const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'

interface OptionItem {
  key: string
  text: string
  is_correct: boolean
}

interface QuestionItem {
  id: number
  question_bank_id: number
  type: 'pg' | 'pg_kompleks' | 'esai' | 'isian'
  question: string
  options: OptionItem[] | null
  correct_answer: string | null
  explanation: string | null
  difficulty: 'easy' | 'medium' | 'hard'
  weight: number
  created_at: string
}

interface QuestionBank {
  id: number
  title: string
  grade_level: string
  description: string
  subject?: { name: string; code: string }
  teacher?: { name: string }
}

const loading = ref(true)
const actionLoading = ref(false)
const bank = ref<QuestionBank | null>(null)
const items = ref<QuestionItem[]>([])

// Modal State
const isModalOpen = ref(false)
const editingId = ref<number | null>(null)
const form = ref({
  type: 'pg' as 'pg' | 'pg_kompleks' | 'esai' | 'isian',
  difficulty: 'medium' as 'easy' | 'medium' | 'hard',
  weight: 1.0,
  question: '',
  options: [
    { key: 'A', text: '', is_correct: true },
    { key: 'B', text: '', is_correct: false },
    { key: 'C', text: '', is_correct: false },
    { key: 'D', text: '', is_correct: false },
    { key: 'E', text: '', is_correct: false },
  ] as OptionItem[],
  correct_answer: '',
  explanation: '',
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
    const [bankRes, itemsRes] = await Promise.all([
      api<any>(`/question-banks/${bankId.value}`),
      api<any>(`/question-banks/${bankId.value}/items`)
    ])
    bank.value = bankRes?.data || null
    items.value = itemsRes?.data || []
  } catch (err: any) {
    console.error('Gagal memuat butir soal:', err)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchData()
})

const openAddModal = () => {
  editingId.value = null
  form.value = {
    type: 'pg',
    difficulty: 'medium',
    weight: 1.0,
    question: '',
    options: [
      { key: 'A', text: '', is_correct: true },
      { key: 'B', text: '', is_correct: false },
      { key: 'C', text: '', is_correct: false },
      { key: 'D', text: '', is_correct: false },
      { key: 'E', text: '', is_correct: false },
    ],
    correct_answer: '',
    explanation: '',
  }
  formError.value = ''
  isModalOpen.value = true
}

const openEditModal = (item: QuestionItem) => {
  editingId.value = item.id
  let parsedOptions: OptionItem[] = []
  if (item.options && Array.isArray(item.options)) {
    parsedOptions = JSON.parse(JSON.stringify(item.options))
  } else {
    parsedOptions = [
      { key: 'A', text: '', is_correct: true },
      { key: 'B', text: '', is_correct: false },
      { key: 'C', text: '', is_correct: false },
      { key: 'D', text: '', is_correct: false },
      { key: 'E', text: '', is_correct: false },
    ]
  }

  form.value = {
    type: item.type,
    difficulty: item.difficulty,
    weight: Number(item.weight) || 1.0,
    question: item.question,
    options: parsedOptions,
    correct_answer: item.correct_answer || '',
    explanation: item.explanation || '',
  }
  formError.value = ''
  isModalOpen.value = true
}

// Select Single Correct Answer for Standard PG
const setSingleCorrect = (index: number) => {
  form.value.options.forEach((opt, idx) => {
    opt.is_correct = idx === index
  })
}

// Add/Remove Options
const addOption = () => {
  const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G']
  const nextKey = letters[form.value.options.length] || `O${form.value.options.length + 1}`
  form.value.options.push({ key: nextKey, text: '', is_correct: false })
}

const removeOption = (index: number) => {
  if (form.value.options.length <= 2) return
  form.value.options.splice(index, 1)
}

const saveItem = async () => {
  if (!form.value.question) {
    formError.value = 'Pertanyaan tidak boleh kosong.'
    return
  }

  if (form.value.type === 'pg' || form.value.type === 'pg_kompleks') {
    const hasCorrect = form.value.options.some(opt => opt.is_correct)
    if (!hasCorrect) {
      formError.value = 'Tentukan setidaknya satu kunci jawaban yang benar.'
      return
    }
    const hasEmptyText = form.value.options.some(opt => !opt.text.trim())
    if (hasEmptyText) {
      formError.value = 'Teks semua pilihan jawaban harus diisi.'
      return
    }
  }

  actionLoading.value = true
  formError.value = ''

  try {
    const payload = {
      type: form.value.type,
      question: form.value.question,
      difficulty: form.value.difficulty,
      weight: form.value.weight,
      options: (form.value.type === 'pg' || form.value.type === 'pg_kompleks') ? form.value.options : null,
      correct_answer: (form.value.type === 'esai' || form.value.type === 'isian') ? form.value.correct_answer : null,
      explanation: form.value.explanation || null,
    }

    if (editingId.value) {
      await api(`/question-banks/items/${editingId.value}`, {
        method: 'PUT',
        body: payload,
      })
    } else {
      await api(`/question-banks/${bankId.value}/items`, {
        method: 'POST',
        body: payload,
      })
    }

    isModalOpen.value = false
    await fetchData()
  } catch (err: any) {
    formError.value = err?.data?.message || 'Gagal menyimpan butir soal.'
  } finally {
    actionLoading.value = false
  }
}

const deleteItem = async (itemId: number) => {
  if (!confirm('Apakah Anda yakin ingin menghapus butir soal ini?')) return
  try {
    await api(`/question-banks/items/${itemId}`, { method: 'DELETE' })
    await fetchData()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal menghapus butir soal')
  }
}

const getDifficultyBadge = (diff: string) => {
  switch (diff) {
    case 'easy':
      return { bg: 'bg-emerald-50 text-emerald-700 border-emerald-200', label: 'Mudah' }
    case 'hard':
      return { bg: 'bg-rose-50 text-rose-700 border-rose-200', label: 'Sulit' }
    default:
      return { bg: 'bg-amber-50 text-amber-700 border-amber-200', label: 'Sedang' }
  }
}

const getTypeLabel = (type: string) => {
  switch (type) {
    case 'pg': return 'Pilihan Ganda'
    case 'pg_kompleks': return 'PG Kompleks'
    case 'esai': return 'Esai'
    case 'isian': return 'Isian Singkat'
    default: return type
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Back Button & Breadcrumbs -->
    <div class="flex items-center gap-2">
      <NuxtLink
        to="/teacher/questions/bank"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-600 hover:bg-slate-50 hover:text-emerald-700 transition"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Kembali ke Bank Soal
      </NuxtLink>
      <span class="text-slate-300">/</span>
      <span class="text-xs font-semibold text-slate-500">Kelola Butir Soal</span>
    </div>

    <!-- Header Paket Info -->
    <div class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="px-2.5 py-0.5 rounded-md bg-emerald-50 border border-emerald-200/60 font-mono text-[10px] font-extrabold text-emerald-800">
            ID #BS-{{ bankId }}
          </span>
          <span class="text-xs font-bold text-emerald-700">
            {{ bank?.subject?.name || 'Mata Pelajaran' }} · Kelas {{ bank?.grade_level }}
          </span>
        </div>
        <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">{{ bank?.title || 'Memuat Paket Soal...' }}</h1>
        <p v-if="bank?.description" class="text-xs text-slate-500 mt-1 max-w-2xl leading-relaxed">
          {{ bank.description }}
        </p>
      </div>

      <div class="flex items-center gap-3">
        <div class="text-right hidden sm:block">
          <span class="text-xs font-bold text-slate-400 block uppercase tracking-wider">Total Soal</span>
          <span class="text-2xl font-extrabold text-slate-900">{{ items.length }} Butir</span>
        </div>
        <button
          @click="openAddModal"
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white font-bold text-xs shadow-md shadow-emerald-600/20 hover:bg-emerald-700 transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Tambah Butir Soal
        </button>
      </div>
    </div>

    <!-- Items List Content -->
    <div v-if="loading" class="p-12 text-center text-xs text-slate-400 bg-white rounded-3xl border border-slate-100">
      <svg class="animate-spin h-6 w-6 text-emerald-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      Memuat daftar pertanyaan...
    </div>

    <div v-else-if="items.length === 0" class="p-12 text-center bg-white rounded-3xl border border-slate-200/80">
      <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <h3 class="text-sm font-bold text-slate-800">Paket soal ini masih kosong</h3>
      <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
        Mulai tambahkan butir-butir pertanyaan pilihan ganda, PG kompleks, atau esai.
      </p>
      <button
        @click="openAddModal"
        class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 text-xs font-bold rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100"
      >
        + Tambah Butir Soal Pertama
      </button>
    </div>

    <!-- Questions Accordion / List -->
    <div v-else class="space-y-4">
      <div
        v-for="(item, index) in items"
        :key="item.id"
        class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs space-y-4 hover:border-emerald-300 transition"
      >
        <!-- Item Header -->
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
          <div class="flex items-center gap-2.5 flex-wrap">
            <span class="w-7 h-7 rounded-xl bg-emerald-600 text-white font-extrabold text-xs flex items-center justify-center shadow-xs">
              {{ index + 1 }}
            </span>
            <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">
              {{ getTypeLabel(item.type) }}
            </span>
            <span :class="['text-[10px] font-bold px-2 py-0.5 rounded-full border', getDifficultyBadge(item.difficulty).bg]">
              {{ getDifficultyBadge(item.difficulty).label }}
            </span>
            <span class="text-[11px] font-semibold text-slate-500">
              Bobot: <b class="text-slate-800">{{ item.weight }} Poin</b>
            </span>
          </div>

          <div class="flex items-center gap-1">
            <button
              @click="openEditModal(item)"
              class="p-2 rounded-xl text-slate-400 hover:text-emerald-700 hover:bg-emerald-50 transition"
              title="Edit Soal"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>
            <button
              @click="deleteItem(item.id)"
              class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
              title="Hapus Soal"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Question Text -->
        <div class="text-sm font-semibold text-slate-900 leading-relaxed whitespace-pre-wrap">
          {{ item.question }}
        </div>

        <!-- Options for PG / PG Kompleks -->
        <div v-if="(item.type === 'pg' || item.type === 'pg_kompleks') && item.options" class="grid gap-2 text-xs">
          <div
            v-for="opt in item.options"
            :key="opt.key"
            :class="[
              'p-3 rounded-2xl border flex items-center justify-between gap-3 transition',
              opt.is_correct
                ? 'bg-emerald-50/70 border-emerald-300 text-emerald-950 font-bold'
                : 'bg-white border-slate-200 text-slate-700'
            ]"
          >
            <div class="flex items-center gap-3">
              <span
                :class="[
                  'w-6 h-6 rounded-lg text-xs font-extrabold flex items-center justify-center shrink-0',
                  opt.is_correct ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'
                ]"
              >
                {{ opt.key }}
              </span>
              <span>{{ opt.text }}</span>
            </div>
            <span v-if="opt.is_correct" class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-200 text-emerald-900 shrink-0">
              ✓ Kunci Benar
            </span>
          </div>
        </div>

        <!-- Rubrik Jawaban jika Esai -->
        <div v-if="item.correct_answer && (item.type === 'esai' || item.type === 'isian')" class="p-3.5 rounded-2xl bg-amber-50/70 border border-amber-200 text-xs">
          <span class="font-extrabold text-amber-900 block mb-0.5">Panduan Kunci Jawaban / Rubrik Penskoran:</span>
          <p class="text-amber-800 leading-relaxed">{{ item.correct_answer }}</p>
        </div>

        <!-- Pembahasan Soal -->
        <div v-if="item.explanation" class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/70 text-xs text-slate-600">
          <span class="font-bold text-slate-800 block mb-0.5">Pembahasan & Penjelasan:</span>
          <p class="leading-relaxed">{{ item.explanation }}</p>
        </div>
      </div>
    </div>

    <!-- Modal Form Tambah / Edit Butir Soal -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in"
    >
      <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-100 space-y-4 max-h-[90vh] flex flex-col justify-between">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="font-extrabold text-slate-900 text-sm">
            {{ editingId ? 'Edit Butir Soal' : 'Tambah Butir Soal Baru' }}
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

        <form @submit.prevent="saveItem" class="flex-1 overflow-y-auto space-y-4 text-xs pr-1">
          <!-- Tipe Soal & Kesulitan -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Tipe Soal *</label>
              <select
                v-model="form.type"
                required
                class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
              >
                <option value="pg">Pilihan Ganda (Tunggal)</option>
                <option value="pg_kompleks">Pilihan Ganda Kompleks</option>
                <option value="esai">Esai / Uraian</option>
                <option value="isian">Isian Singkat</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Tingkat Kesulitan *</label>
              <select
                v-model="form.difficulty"
                required
                class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
              >
                <option value="easy">Mudah</option>
                <option value="medium">Sedang</option>
                <option value="hard">Sulit</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Bobot Poin *</label>
              <input
                v-model.number="form.weight"
                type="number"
                step="0.5"
                min="0.5"
                required
                class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
          </div>

          <!-- Teks Pertanyaan -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Teks Pertanyaan *</label>
            <textarea
              v-model="form.question"
              rows="4"
              placeholder="Tuliskan soal atau studi kasus pertanyaan di sini..."
              required
              class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            ></textarea>
          </div>

          <!-- Options Builder for PG / PG Kompleks -->
          <div v-if="form.type === 'pg' || form.type === 'pg_kompleks'" class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="block font-bold text-slate-700">
                Pilihan Jawaban & Kunci Jawaban Benar *
                <span class="text-[10px] font-normal text-slate-400 block">
                  {{ form.type === 'pg' ? 'Klik radio button pada opsi yang merupakan kunci jawaban benar' : 'Centang kotak untuk semua jawaban yang benar' }}
                </span>
              </label>
              <button
                type="button"
                @click="addOption"
                class="text-[11px] font-bold text-emerald-700 hover:underline"
              >
                + Tambah Opsi
              </button>
            </div>

            <div class="space-y-2">
              <div
                v-for="(opt, idx) in form.options"
                :key="opt.key"
                class="flex items-center gap-2 p-2 rounded-2xl border border-slate-200 bg-slate-50/50"
              >
                <!-- Radio for Single PG -->
                <input
                  v-if="form.type === 'pg'"
                  type="radio"
                  name="correct_answer_radio"
                  :checked="opt.is_correct"
                  @change="setSingleCorrect(idx)"
                  class="w-4 h-4 text-emerald-600 focus:ring-emerald-500 border-slate-300 ml-1"
                  title="Pilih sebagai kunci jawaban benar"
                />
                <!-- Checkbox for PG Kompleks -->
                <input
                  v-else
                  type="checkbox"
                  v-model="opt.is_correct"
                  class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500 border-slate-300 ml-1"
                  title="Centang jika opsi ini benar"
                />

                <span class="w-6 text-center font-extrabold text-slate-700">{{ opt.key }}.</span>
                <input
                  v-model="opt.text"
                  type="text"
                  :placeholder="`Pilihan jawaban ${opt.key}`"
                  required
                  class="flex-1 px-3 py-1.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />

                <button
                  type="button"
                  @click="removeOption(idx)"
                  v-if="form.options.length > 2"
                  class="p-1 rounded-lg text-slate-400 hover:text-rose-600"
                  title="Hapus opsi"
                >
                  ✕
                </button>
              </div>
            </div>
          </div>

          <!-- Kunci Jawaban untuk Esai -->
          <div v-if="form.type === 'esai' || form.type === 'isian'">
            <label class="block font-bold text-slate-700 mb-1">
              {{ form.type === 'esai' ? 'Panduan Penskoran / Kata Kunci Esai' : 'Kunci Jawaban Singkat' }}
            </label>
            <textarea
              v-model="form.correct_answer"
              rows="2"
              placeholder="Tuliskan kata kunci atau panduan penilaian bagi guru/korektor..."
              class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            ></textarea>
          </div>

          <!-- Pembahasan / Penjelasan Soal -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Pembahasan / Penjelasan Soal (Opsional)</label>
            <textarea
              v-model="form.explanation"
              rows="2"
              placeholder="Penjelasan langkah penyelesaian untuk ditampilkan saat review hasil ujian..."
              class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            ></textarea>
          </div>

          <!-- Actions -->
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
              {{ actionLoading ? 'Menyimpan...' : 'Simpan Butir Soal' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
