<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import BackButton from '~/components/ui/BackButton.vue'
import AppModal from '~/components/ui/AppModal.vue'
import AppButton from '~/components/ui/AppButton.vue'
import QuestionImageUploader from '~/components/quiz/QuestionImageUploader.vue'

definePageMeta({ layout: 'teacher', middleware: 'auth' })

const route = useRoute()
const bankId = computed(() => route.params.id)

const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const storageBase = config.public.storageBase || 'http://localhost:8000/storage'

const getFullUrl = (url: string | null | undefined) => {
  if (!url) return ''
  if (url.startsWith('http://') || url.startsWith('https://')) return url
  const cleanPath = url.replace(/^\/storage\/?/, '')
  return `${storageBase}/${cleanPath}`
}

interface OptionItem {
  key: string
  text: string
  is_correct: boolean
}

interface PairItem {
  id?: string
  left: string
  right: string
}

interface StatementItem {
  id?: string
  text: string
  answer: boolean
}

interface QuestionItem {
  id: number
  question_bank_id: number
  type: 'pg' | 'pg_kompleks' | 'isian' | 'menjodohkan' | 'benar_salah' | 'esai'
  question: string
  image_url?: string | null
  image_meta?: any
  options: OptionItem[] | null
  data?: {
    accepted_answers?: string[]
    pairs?: PairItem[]
    statements?: StatementItem[]
  } | null
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

// Modal Form State
const isModalOpen = ref(false)
const editingId = ref<number | null>(null)
const form = ref({
  type: 'pg' as QuestionItem['type'],
  difficulty: 'medium' as 'easy' | 'medium' | 'hard',
  weight: 1.0,
  question: '',
  image_url: null as string | null,
  image_meta: null as any,
  options: [
    { key: 'A', text: '', is_correct: true },
    { key: 'B', text: '', is_correct: false },
    { key: 'C', text: '', is_correct: false },
    { key: 'D', text: '', is_correct: false },
    { key: 'E', text: '', is_correct: false },
  ] as OptionItem[],
  accepted_answers: [''] as string[],
  pairs: [
    { left: '', right: '' },
    { left: '', right: '' },
  ] as PairItem[],
  statements: [
    { text: '', answer: true },
    { text: '', answer: false },
  ] as StatementItem[],
  correct_answer: '',
  explanation: '',
})
const formError = ref('')

// Preview Modal State (F4)
const isPreviewOpen = ref(false)
const previewQuestion = ref<any>(null)
const previewAnswer = ref<any>(null)

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
    image_url: null,
    image_meta: null,
    options: [
      { key: 'A', text: '', is_correct: true },
      { key: 'B', text: '', is_correct: false },
      { key: 'C', text: '', is_correct: false },
      { key: 'D', text: '', is_correct: false },
      { key: 'E', text: '', is_correct: false },
    ],
    accepted_answers: [''],
    pairs: [
      { left: '', right: '' },
      { left: '', right: '' },
    ],
    statements: [
      { text: '', answer: true },
      { text: '', answer: false },
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

  const accepted = (item.data?.accepted_answers && item.data.accepted_answers.length > 0)
    ? [...item.data.accepted_answers]
    : ['']

  const pairs = (item.data?.pairs && item.data.pairs.length > 0)
    ? JSON.parse(JSON.stringify(item.data.pairs))
    : [{ left: '', right: '' }, { left: '', right: '' }]

  const statements = (item.data?.statements && item.data.statements.length > 0)
    ? JSON.parse(JSON.stringify(item.data.statements))
    : [{ text: '', answer: true }, { text: '', answer: false }]

  form.value = {
    type: item.type,
    difficulty: item.difficulty,
    weight: Number(item.weight) || 1.0,
    question: item.question,
    image_url: item.image_url || null,
    image_meta: item.image_meta || null,
    options: parsedOptions,
    accepted_answers: accepted,
    pairs,
    statements,
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

// Option Helpers for PG
const addOption = () => {
  const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G']
  const nextKey = letters[form.value.options.length] || `O${form.value.options.length + 1}`
  form.value.options.push({ key: nextKey, text: '', is_correct: false })
}

const removeOption = (index: number) => {
  if (form.value.options.length <= 2) return
  form.value.options.splice(index, 1)
}

// Isian Accepted Answers Helpers
const addAcceptedAnswer = () => {
  form.value.accepted_answers.push('')
}
const removeAcceptedAnswer = (index: number) => {
  if (form.value.accepted_answers.length <= 1) return
  form.value.accepted_answers.splice(index, 1)
}

// Menjodohkan Pairs Helpers
const addPair = () => {
  form.value.pairs.push({ left: '', right: '' })
}
const removePair = (index: number) => {
  if (form.value.pairs.length <= 2) return
  form.value.pairs.splice(index, 1)
}

// Benar Salah Statements Helpers
const addStatement = () => {
  form.value.statements.push({ text: '', answer: true })
}
const removeStatement = (index: number) => {
  if (form.value.statements.length <= 1) return
  form.value.statements.splice(index, 1)
}

const saveItem = async () => {
  if (!form.value.question.trim()) {
    formError.value = 'Pertanyaan tidak boleh kosong.'
    return
  }

  let payloadData: any = null
  let payloadOptions: any = null

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
    payloadOptions = form.value.options
  } else if (form.value.type === 'isian') {
    const validAccepted = form.value.accepted_answers.map(a => a.trim()).filter(Boolean)
    if (validAccepted.length === 0) {
      formError.value = 'Tuliskan minimal 1 alternatif kunci jawaban isian yang diterima.'
      return
    }
    payloadData = { accepted_answers: validAccepted }
  } else if (form.value.type === 'menjodohkan') {
    const validPairs = form.value.pairs.filter(p => p.left.trim() && p.right.trim())
    if (validPairs.length < 2) {
      formError.value = 'Soal menjodohkan membutuhkan minimal 2 pasang pernyataan lengkap.'
      return
    }
    payloadData = {
      pairs: validPairs.map((p, idx) => ({
        id: String(idx + 1),
        left: p.left.trim(),
        right: p.right.trim(),
      }))
    }
  } else if (form.value.type === 'benar_salah') {
    const validStatements = form.value.statements.filter(s => s.text.trim())
    if (validStatements.length < 1) {
      formError.value = 'Soal Benar/Salah membutuhkan minimal 1 pernyataan.'
      return
    }
    payloadData = {
      statements: validStatements.map((s, idx) => ({
        id: String(idx + 1),
        text: s.text.trim(),
        answer: Boolean(s.answer),
      }))
    }
  }

  actionLoading.value = true
  formError.value = ''

  try {
    const payload = {
      type: form.value.type,
      question: form.value.question,
      image_url: form.value.image_url || null,
      image_meta: form.value.image_meta || null,
      difficulty: form.value.difficulty,
      weight: form.value.weight,
      options: payloadOptions,
      data: payloadData,
      correct_answer: form.value.type === 'esai' ? form.value.correct_answer : null,
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

// Modal Delete Confirmation State
const isDeleteModalOpen = ref(false)
const targetDeleteId = ref<number | null>(null)

const promptDeleteItem = (itemId: number) => {
  targetDeleteId.value = itemId
  isDeleteModalOpen.value = true
}

const confirmDeleteItem = async () => {
  if (!targetDeleteId.value) return
  actionLoading.value = true
  try {
    await api(`/question-banks/items/${targetDeleteId.value}`, { method: 'DELETE' })
    isDeleteModalOpen.value = false
    targetDeleteId.value = null
    await fetchData()
  } catch (err: any) {
    formError.value = err?.data?.message || 'Gagal menghapus butir soal.'
  } finally {
    actionLoading.value = false
  }
}

// Pratinjau Siswa (F4)
const openPreview = (item: QuestionItem, index: number) => {
  previewAnswer.value = null
  let sanitizedItem: any = {
    number: index + 1,
    type: item.type,
    question: item.question,
    weight: item.weight,
    difficulty: item.difficulty,
    explanation: item.explanation,
    raw: item,
  }

  if (item.type === 'pg' || item.type === 'pg_kompleks') {
    sanitizedItem.options = (item.options || []).map(o => ({ key: o.key, text: o.text }))
  } else if (item.type === 'menjodohkan') {
    const pairs = item.data?.pairs || []
    sanitizedItem.pairs = pairs.map(p => ({ id: p.id || '1', left: p.left }))
    // Acak pilihan kanan untuk simulasi siswa
    const choices = [...pairs.map(p => p.right)].sort(() => Math.random() - 0.5)
    sanitizedItem.choices = choices
  } else if (item.type === 'benar_salah') {
    sanitizedItem.statements = (item.data?.statements || []).map(s => ({ id: s.id || '1', text: s.text }))
  }

  previewQuestion.value = sanitizedItem
  isPreviewOpen.value = true
}

const answerComponent = (type: string) => {
  switch (type) {
    case 'pg': return resolveComponent('ExamAnswerPg')
    case 'pg_kompleks': return resolveComponent('ExamAnswerPgKompleks')
    case 'isian': return resolveComponent('ExamAnswerIsian')
    case 'menjodohkan': return resolveComponent('ExamAnswerMenjodohkan')
    case 'benar_salah': return resolveComponent('ExamAnswerBenarSalah')
    default: return resolveComponent('ExamAnswerEsai')
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

const getTypeBadge = (type: string) => {
  switch (type) {
    case 'pg': return { label: 'Pilihan Ganda', color: 'bg-blue-50 text-blue-700 border-blue-200' }
    case 'pg_kompleks': return { label: 'PG Kompleks', color: 'bg-indigo-50 text-indigo-700 border-indigo-200' }
    case 'isian': return { label: 'Isian Singkat', color: 'bg-amber-50 text-amber-800 border-amber-200' }
    case 'menjodohkan': return { label: 'Menjodohkan', color: 'bg-purple-50 text-purple-700 border-purple-200' }
    case 'benar_salah': return { label: 'Benar / Salah', color: 'bg-teal-50 text-teal-800 border-teal-200' }
    case 'esai': return { label: 'Esai / Uraian', color: 'bg-slate-100 text-slate-700 border-slate-200' }
    default: return { label: type, color: 'bg-slate-100 text-slate-700 border-slate-200' }
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Back Button & Breadcrumbs -->
    <div class="flex items-center gap-2">
      <BackButton fallback="/teacher/questions/bank" label="Kembali ke Bank Soal" />
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
        Mulai tambahkan butir-butir pertanyaan PG, PG Kompleks, Isian Singkat, Menjodohkan, atau Benar/Salah.
      </p>
      <button
        @click="openAddModal"
        class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 text-xs font-bold rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100"
      >
        + Tambah Butir Soal Pertama
      </button>
    </div>

    <!-- Questions List -->
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
            <span :class="['text-xs font-bold px-2.5 py-0.5 rounded-full border', getTypeBadge(item.type).color]">
              {{ getTypeBadge(item.type).label }}
            </span>
            <span :class="['text-[10px] font-bold px-2 py-0.5 rounded-full border', getDifficultyBadge(item.difficulty).bg]">
              {{ getDifficultyBadge(item.difficulty).label }}
            </span>
            <span class="text-[11px] font-semibold text-slate-500">
              Bobot: <b class="text-slate-800">{{ item.weight }} Poin</b>
            </span>
            <span v-if="item.image_url" class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
              <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              Ada Gambar
            </span>
          </div>

          <div class="flex items-center gap-1.5">
            <!-- Pratinjau Button (F4) -->
            <button
              @click="openPreview(item, index)"
              class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-bold text-slate-600 bg-slate-50 hover:bg-emerald-50 hover:text-emerald-700 border border-slate-200 transition"
              title="Pratinjau Tampilan Siswa"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <span>Pratinjau</span>
            </button>

            <!-- Edit Button -->
            <button
              @click="openEditModal(item)"
              class="p-2 min-h-[40px] min-w-[40px] flex items-center justify-center rounded-xl text-slate-500 hover:text-brand-800 hover:bg-brand-50 transition cursor-pointer"
              title="Edit Soal"
              aria-label="Edit Soal"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>

            <!-- Delete Button -->
            <button
              @click="promptDeleteItem(item.id)"
              class="p-2 min-h-[40px] min-w-[40px] flex items-center justify-center rounded-xl text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer"
              title="Hapus Soal"
              aria-label="Hapus Soal"
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

        <!-- Thumbnail Lampiran Gambar Butir Soal -->
        <div v-if="item.image_url" class="max-w-md my-2 rounded-2xl overflow-hidden border border-slate-200/90 bg-slate-50 p-2 shadow-2xs">
          <img
            :src="getFullUrl(item.image_url)"
            :alt="item.image_meta?.alt_text || 'Diagram butir soal'"
            class="max-h-52 w-auto rounded-xl object-contain mx-auto"
            loading="lazy"
          />
          <div v-if="item.image_meta?.alt_text" class="text-[11px] text-slate-500 text-center mt-1.5 italic font-medium">
            {{ item.image_meta.alt_text }}
          </div>
        </div>

        <!-- Render Content Berdasarkan Tipe -->
        <!-- 1. PG & PG Kompleks -->
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

        <!-- 2. Isian Singkat -->
        <div v-else-if="item.type === 'isian'" class="p-3.5 rounded-2xl bg-amber-50/60 border border-amber-200 text-xs space-y-1.5">
          <span class="font-extrabold text-amber-900 block">Kunci Jawaban Singkat yang Diterima (Otomatis Dinilai):</span>
          <div class="flex flex-wrap gap-1.5">
            <span
              v-for="ans in (item.data?.accepted_answers || [])"
              :key="ans"
              class="px-2.5 py-1 rounded-xl bg-white border border-amber-300 font-mono font-bold text-amber-950 text-xs shadow-2xs"
            >
              "{{ ans }}"
            </span>
            <span v-if="!item.data?.accepted_answers?.length" class="text-slate-400 italic">
              Belum ada kunci jawaban.
            </span>
          </div>
        </div>

        <!-- 3. Menjodohkan -->
        <div v-else-if="item.type === 'menjodohkan'" class="p-3.5 rounded-2xl bg-purple-50/50 border border-purple-200 text-xs space-y-2">
          <span class="font-extrabold text-purple-900 block">Kunci Pasangan Menjodohkan (Kiri ↔ Kanan):</span>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <div
              v-for="pair in (item.data?.pairs || [])"
              :key="pair.id"
              class="p-2.5 rounded-xl bg-white border border-purple-200 flex items-center justify-between gap-2 shadow-2xs"
            >
              <span class="font-semibold text-slate-800">{{ pair.left }}</span>
              <span class="text-purple-600 font-extrabold">↔</span>
              <span class="font-bold text-purple-950 bg-purple-50 px-2 py-0.5 rounded-lg border border-purple-200">{{ pair.right }}</span>
            </div>
          </div>
        </div>

        <!-- 4. Benar / Salah -->
        <div v-else-if="item.type === 'benar_salah'" class="p-3.5 rounded-2xl bg-teal-50/50 border border-teal-200 text-xs space-y-2">
          <span class="font-extrabold text-teal-900 block">Pernyataan & Kunci Benar/Salah:</span>
          <div class="space-y-1.5">
            <div
              v-for="st in (item.data?.statements || [])"
              :key="st.id"
              class="p-2.5 rounded-xl bg-white border border-teal-200 flex items-center justify-between gap-3 shadow-2xs"
            >
              <span class="font-medium text-slate-800">{{ st.text }}</span>
              <span
                :class="[
                  'px-2.5 py-0.5 rounded-lg font-extrabold text-[11px] shrink-0',
                  st.answer ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300'
                ]"
              >
                {{ st.answer ? 'BENAR' : 'SALAH' }}
              </span>
            </div>
          </div>
        </div>

        <!-- 5. Esai -->
        <div v-else-if="item.type === 'esai' && item.correct_answer" class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs">
          <span class="font-extrabold text-slate-800 block mb-0.5">Panduan Penskoran / Rubrik Esai:</span>
          <p class="text-slate-700 leading-relaxed">{{ item.correct_answer }}</p>
        </div>

        <!-- Pembahasan Soal -->
        <div v-if="item.explanation" class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/70 text-xs text-slate-600">
          <span class="font-bold text-slate-800 block mb-0.5">Pembahasan & Penjelasan:</span>
          <p class="leading-relaxed">{{ item.explanation }}</p>
        </div>
      </div>
    </div>

    <!-- Modal Form Tambah / Edit Butir Soal (F1) -->
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

        <div v-if="formError" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700 font-semibold">
          {{ formError }}
        </div>

        <form @submit.prevent="saveItem" class="flex-1 overflow-y-auto space-y-4 text-xs pr-1">
          <!-- Tipe Soal, Kesulitan, Bobot -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Tipe Soal *</label>
              <select
                v-model="form.type"
                required
                class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500"
              >
                <option value="pg">Pilihan Ganda (Tunggal)</option>
                <option value="pg_kompleks">Pilihan Ganda Kompleks</option>
                <option value="isian">Isian Singkat (Auto-grade)</option>
                <option value="menjodohkan">Menjodohkan (Pairs)</option>
                <option value="benar_salah">Benar / Salah</option>
                <option value="esai">Esai / Uraian (Manual)</option>
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
                class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono font-bold"
              />
            </div>
          </div>

          <!-- Teks Pertanyaan -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Teks Pertanyaan *</label>
            <textarea
              v-model="form.question"
              rows="3"
              placeholder="Tuliskan soal atau petunjuk pertanyaan di sini..."
              required
              class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-medium"
            ></textarea>
          </div>

          <!-- Lampiran Gambar Soal (Opsional) -->
          <QuestionImageUploader
            v-model="form.image_url"
            v-model:image-meta="form.image_meta"
          />

          <!-- 1. Builder untuk PG / PG Kompleks -->
          <div v-if="form.type === 'pg' || form.type === 'pg_kompleks'" class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="block font-bold text-slate-700">
                Pilihan Jawaban & Kunci Jawaban Benar *
                <span class="text-[10px] font-normal text-slate-400 block">
                  {{ form.type === 'pg' ? 'Pilih satu radio button sebagai kunci jawaban benar' : 'Centang semua kotak jawaban yang benar' }}
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
                  class="w-4 h-4 text-emerald-600 focus:ring-emerald-500 border-slate-300 ml-1 cursor-pointer"
                  title="Pilih sebagai kunci benar"
                />
                <!-- Checkbox for PG Kompleks -->
                <input
                  v-else
                  type="checkbox"
                  v-model="opt.is_correct"
                  class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500 border-slate-300 ml-1 cursor-pointer"
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

          <!-- 2. Builder untuk Isian Singkat -->
          <div v-else-if="form.type === 'isian'" class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="block font-bold text-slate-700">
                Alternatif Kunci Jawaban yang Diterima *
                <span class="text-[10px] font-normal text-slate-400 block">
                  Penilaian otomatis bersifat tidak sensitif huruf besar/kecil (case-insensitive). Tambahkan variasi penulisan yang sah.
                </span>
              </label>
              <button
                type="button"
                @click="addAcceptedAnswer"
                class="text-[11px] font-bold text-emerald-700 hover:underline"
              >
                + Tambah Alternatif
              </button>
            </div>

            <div class="space-y-2">
              <div
                v-for="(ans, idx) in form.accepted_answers"
                :key="idx"
                class="flex items-center gap-2"
              >
                <span class="text-xs font-mono font-bold text-slate-400 w-5 text-right">{{ idx + 1 }}.</span>
                <input
                  v-model="form.accepted_answers[idx]"
                  type="text"
                  placeholder="Contoh: fotosintesis atau foto sintesis"
                  required
                  class="flex-1 px-3 py-2 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 font-medium"
                />
                <button
                  type="button"
                  @click="removeAcceptedAnswer(idx)"
                  v-if="form.accepted_answers.length > 1"
                  class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50"
                  title="Hapus variasi"
                >
                  ✕
                </button>
              </div>
            </div>
          </div>

          <!-- 3. Builder untuk Menjodohkan -->
          <div v-else-if="form.type === 'menjodohkan'" class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="block font-bold text-slate-700">
                Daftar Pasangan Menjodohkan (Minimal 2 pasang) *
                <span class="text-[10px] font-normal text-slate-400 block">
                  Pilihan kanan akan otomatis diacak saat ujian siswa dimulai.
                </span>
              </label>
              <button
                type="button"
                @click="addPair"
                class="text-[11px] font-bold text-emerald-700 hover:underline"
              >
                + Tambah Pasangan
              </button>
            </div>

            <div class="space-y-2">
              <div
                v-for="(pair, idx) in form.pairs"
                :key="idx"
                class="grid grid-cols-1 sm:grid-cols-11 gap-2 items-center p-2.5 rounded-2xl border border-slate-200 bg-slate-50/50"
              >
                <div class="sm:col-span-5">
                  <input
                    v-model="pair.left"
                    type="text"
                    placeholder="Pernyataan Kiri (Premis)"
                    required
                    class="w-full px-3 py-1.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  />
                </div>
                <div class="sm:col-span-1 text-center font-bold text-purple-600 text-xs">
                  ↔
                </div>
                <div class="sm:col-span-4">
                  <input
                    v-model="pair.right"
                    type="text"
                    placeholder="Jawaban Kanan yang Benar"
                    required
                    class="w-full px-3 py-1.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  />
                </div>
                <div class="sm:col-span-1 text-right">
                  <button
                    type="button"
                    @click="removePair(idx)"
                    v-if="form.pairs.length > 2"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50"
                    title="Hapus baris pasangan"
                  >
                    ✕
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- 4. Builder untuk Benar / Salah -->
          <div v-else-if="form.type === 'benar_salah'" class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="block font-bold text-slate-700">
                Daftar Pernyataan & Kunci Kebenaran *
                <span class="text-[10px] font-normal text-slate-400 block">
                  Tentukan apakah pernyataan berikut Benar (True) atau Salah (False).
                </span>
              </label>
              <button
                type="button"
                @click="addStatement"
                class="text-[11px] font-bold text-emerald-700 hover:underline"
              >
                + Tambah Pernyataan
              </button>
            </div>

            <div class="space-y-2">
              <div
                v-for="(st, idx) in form.statements"
                :key="idx"
                class="flex items-center gap-2 p-2.5 rounded-2xl border border-slate-200 bg-slate-50/50"
              >
                <span class="text-xs font-mono font-bold text-slate-400 w-5 text-right">{{ idx + 1 }}.</span>
                <input
                  v-model="st.text"
                  type="text"
                  placeholder="Ketik kalimat pernyataan di sini..."
                  required
                  class="flex-1 px-3 py-1.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
                
                <div class="flex items-center gap-1 shrink-0 bg-white p-1 rounded-xl border border-slate-200">
                  <button
                    type="button"
                    @click="st.answer = true"
                    :class="[
                      'px-2.5 py-1 rounded-lg text-[10px] font-extrabold transition',
                      st.answer ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100'
                    ]"
                  >
                    BENAR
                  </button>
                  <button
                    type="button"
                    @click="st.answer = false"
                    :class="[
                      'px-2.5 py-1 rounded-lg text-[10px] font-extrabold transition',
                      !st.answer ? 'bg-rose-600 text-white' : 'text-slate-600 hover:bg-slate-100'
                    ]"
                  >
                    SALAH
                  </button>
                </div>

                <button
                  type="button"
                  @click="removeStatement(idx)"
                  v-if="form.statements.length > 1"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50"
                  title="Hapus pernyataan"
                >
                  ✕
                </button>
              </div>
            </div>
          </div>

          <!-- 5. Kunci / Panduan untuk Esai -->
          <div v-else-if="form.type === 'esai'">
            <label class="block font-bold text-slate-700 mb-1">
              Panduan Penskoran / Kata Kunci Esai (Koreksi Manual)
            </label>
            <textarea
              v-model="form.correct_answer"
              rows="2"
              placeholder="Tuliskan kata kunci atau kriteria rubrik bagi guru penilai..."
              class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            ></textarea>
          </div>

          <!-- Pembahasan / Penjelasan Soal -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Pembahasan / Penjelasan Soal (Opsional)</label>
            <textarea
              v-model="form.explanation"
              rows="2"
              placeholder="Penjelasan langkah penyelesaian untuk ditampilkan saat review evaluasi..."
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

    <!-- Modal Pratinjau Tampilan Siswa (F4) -->
    <div
      v-if="isPreviewOpen && previewQuestion"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in"
    >
      <div class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-slate-100 space-y-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 rounded-xl bg-emerald-100 text-emerald-800 font-extrabold text-xs">
              Simulasi Siswa CBT
            </span>
            <span :class="['text-xs font-bold px-2 py-0.5 rounded-full border', getTypeBadge(previewQuestion.type).color]">
              {{ getTypeBadge(previewQuestion.type).label }}
            </span>
          </div>
          <button @click="isPreviewOpen = false" class="text-slate-400 hover:text-slate-600">
            ✕
          </button>
        </div>

        <!-- Bagian Pertanyaan Siswa -->
        <div class="bg-slate-50/70 p-5 rounded-2xl border border-slate-200/80 space-y-4">
          <div class="flex items-center justify-between">
            <span class="font-extrabold text-xs text-slate-700">Soal Nomor {{ previewQuestion.number }}</span>
            <span class="text-xs text-slate-400 font-semibold">Bobot: {{ previewQuestion.weight }} Poin</span>
          </div>
          <p class="text-sm font-semibold text-slate-900 whitespace-pre-wrap leading-relaxed">
            {{ previewQuestion.question }}
          </p>

          <!-- Pratinjau Gambar Soal -->
          <div v-if="previewQuestion.raw?.image_url" class="max-w-md mx-auto my-3 rounded-2xl overflow-hidden border border-slate-200 bg-white p-2 shadow-2xs">
            <img
              :src="getFullUrl(previewQuestion.raw.image_url)"
              :alt="previewQuestion.raw.image_meta?.alt_text || 'Pratinjau diagram soal'"
              class="max-h-64 w-auto object-contain mx-auto rounded-xl"
            />
            <p v-if="previewQuestion.raw.image_meta?.alt_text" class="text-[11px] text-slate-500 text-center mt-1 italic">
              {{ previewQuestion.raw.image_meta.alt_text }}
            </p>
          </div>

          <!-- Render Komponen Siswa yang Sama Persis -->
          <component
            :is="answerComponent(previewQuestion.type)"
            :question="previewQuestion"
            v-model="previewAnswer"
          />
        </div>

        <!-- Verifikasi Kunci Jawaban (Khusus Guru) -->
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 space-y-2 text-xs">
          <div class="flex items-center gap-1.5 font-extrabold text-emerald-900">
            <span>✓</span>
            <span>Kunci Jawaban & Panduan Guru (Disembunyikan dari Siswa saat Ujian):</span>
          </div>

          <div v-if="previewQuestion.raw.type === 'pg' || previewQuestion.raw.type === 'pg_kompleks'">
            <div class="flex flex-wrap gap-2 mt-1">
              <span
                v-for="opt in previewQuestion.raw.options.filter((o: any) => o.is_correct)"
                :key="opt.key"
                class="px-2 py-0.5 rounded-md bg-emerald-200 text-emerald-900 font-bold"
              >
                {{ opt.key }}: {{ opt.text }}
              </span>
            </div>
          </div>
          <div v-else-if="previewQuestion.raw.type === 'isian'">
            <div class="flex flex-wrap gap-1 mt-1">
              <span
                v-for="ans in (previewQuestion.raw.data?.accepted_answers || [])"
                :key="ans"
                class="px-2 py-0.5 rounded-md bg-emerald-200 text-emerald-900 font-mono font-bold"
              >
                "{{ ans }}"
              </span>
            </div>
          </div>
          <div v-else-if="previewQuestion.raw.type === 'menjodohkan'">
            <ul class="list-disc pl-4 space-y-0.5 text-emerald-950 font-medium">
              <li v-for="pair in (previewQuestion.raw.data?.pairs || [])" :key="pair.id">
                <b>{{ pair.left }}</b> ↔ {{ pair.right }}
              </li>
            </ul>
          </div>
          <div v-else-if="previewQuestion.raw.type === 'benar_salah'">
            <ul class="list-disc pl-4 space-y-0.5 text-emerald-950 font-medium">
              <li v-for="st in (previewQuestion.raw.data?.statements || [])" :key="st.id">
                {{ st.text }} → <b>{{ st.answer ? 'BENAR' : 'SALAH' }}</b>
              </li>
            </ul>
          </div>
          <div v-else-if="previewQuestion.raw.type === 'esai' && previewQuestion.raw.correct_answer">
            <p class="text-emerald-900 font-medium">{{ previewQuestion.raw.correct_answer }}</p>
          </div>

          <div v-if="previewQuestion.explanation" class="pt-2 border-t border-emerald-200/80 text-[11px] text-emerald-800">
            <b>Pembahasan:</b> {{ previewQuestion.explanation }}
          </div>
        </div>

        <div class="flex justify-end">
          <button
            @click="isPreviewOpen = false"
            class="px-4 py-2 rounded-xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800"
          >
            Tutup Pratinjau
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Konfirmasi Hapus Butir Soal -->
    <AppModal
      v-model="isDeleteModalOpen"
      title="Hapus Butir Soal?"
      description="Tindakan ini permanen dan akan menghapus butir soal dari paket bank soal ini."
      max-width="sm"
    >
      <div class="space-y-4">
        <p class="text-xs text-slate-600">
          Apakah Anda yakin ingin menghapus butir soal ini?
        </p>
        <div class="flex justify-end gap-2 pt-2">
          <AppButton 
            variant="outline" 
            @click="isDeleteModalOpen = false"
          >
            Batal
          </AppButton>
          <AppButton 
            variant="danger" 
            :loading="actionLoading"
            @click="confirmDeleteItem"
          >
            Ya, Hapus
          </AppButton>
        </div>
      </div>
    </AppModal>
  </div>
</template>
