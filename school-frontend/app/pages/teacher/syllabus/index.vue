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

interface LearningObjective {
  id: number
  subject_id: number
  phase: 'E' | 'F'
  grade_level: '10' | '11' | '12'
  code: string
  title: string
  description: string
  target_hours: number
  is_active: boolean
  subject?: Subject
}

const loading = ref(true)
const saving = ref(false)
const items = ref<LearningObjective[]>([])
const subjects = ref<Subject[]>([])

// Filter State
const search = ref('')
const selectedGrade = ref('')
const selectedPhase = ref('')
const selectedSubject = ref('')

// Modal State
const isModalOpen = ref(false)
const editingId = ref<number | null>(null)
const form = ref({
  subject_id: '',
  phase: 'F' as 'E' | 'F',
  grade_level: '11' as '10' | '11' | '12',
  code: '',
  title: '',
  description: '',
  target_hours: 18,
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
    const [objectivesRes, subjectsRes] = await Promise.all([
      api<any>('/curriculum/objectives?paginate=false'),
      api<any>('/subjects?all=true')
    ])
    items.value = objectivesRes?.data || []
    subjects.value = subjectsRes?.data || subjectsRes || []
  } catch (err: any) {
    console.error('Gagal mengambil data silabus:', err)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchData()
})

// Filtered List
const filteredItems = computed(() => {
  return items.value.filter(item => {
    const matchSearch = !search.value || 
      item.code.toLowerCase().includes(search.value.toLowerCase()) ||
      item.title.toLowerCase().includes(search.value.toLowerCase()) ||
      item.description.toLowerCase().includes(search.value.toLowerCase())
    
    const matchGrade = !selectedGrade.value || item.grade_level === selectedGrade.value
    const matchPhase = !selectedPhase.value || item.phase === selectedPhase.value
    const matchSubject = !selectedSubject.value || String(item.subject_id) === String(selectedSubject.value)

    return matchSearch && matchGrade && matchPhase && matchSubject
  })
})

// Statistics
const totalTargetHours = computed(() => {
  return filteredItems.value.reduce((acc, curr) => acc + (Number(curr.target_hours) || 0), 0)
})

const openAddModal = () => {
  editingId.value = null
  form.value = {
    subject_id: subjects.value[0]?.id ? String(subjects.value[0].id) : '',
    phase: 'F',
    grade_level: '11',
    code: `CP-${form.value.grade_level}.0${filteredItems.value.length + 1}`,
    title: '',
    description: '',
    target_hours: 18,
  }
  formError.value = ''
  isModalOpen.value = true
}

const openEditModal = (item: LearningObjective) => {
  editingId.value = item.id
  form.value = {
    subject_id: String(item.subject_id),
    phase: item.phase,
    grade_level: item.grade_level,
    code: item.code,
    title: item.title,
    description: item.description,
    target_hours: item.target_hours,
  }
  formError.value = ''
  isModalOpen.value = true
}

// Auto update phase when grade level changes
const onGradeChange = () => {
  if (form.value.grade_level === '10') {
    form.value.phase = 'E'
  } else {
    form.value.phase = 'F'
  }
}

const saveObjective = async () => {
  if (!form.value.title || !form.value.code || !form.value.subject_id || !form.value.description) {
    formError.value = 'Mohon lengkapi seluruh field yang wajib diisi.'
    return
  }

  saving.value = true
  formError.value = ''
  try {
    if (editingId.value) {
      await api(`/curriculum/objectives/${editingId.value}`, {
        method: 'PUT',
        body: form.value,
      })
    } else {
      await api('/curriculum/objectives', {
        method: 'POST',
        body: form.value,
      })
    }
    isModalOpen.value = false
    await fetchData()
  } catch (err: any) {
    formError.value = err?.data?.message || 'Gagal menyimpan Capaian Pembelajaran.'
  } finally {
    saving.value = false
  }
}

const deleteObjective = async (id: number) => {
  if (!confirm('Apakah Anda yakin ingin menghapus Capaian Pembelajaran ini?')) return
  try {
    await api(`/curriculum/objectives/${id}`, { method: 'DELETE' })
    await fetchData()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal menghapus data')
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header Title & Info -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-1">
          <span>Kurikulum Merdeka</span>
          <span>•</span>
          <span>MAN 11 Jakarta</span>
        </div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Capaian & Alur Tujuan Pembelajaran (ATP)</h1>
        <p class="text-xs text-slate-500 mt-1">
          Penyusunan target kompetensi, elemen capaian, dan alokasi jam pelajaran (JP) per mata pelajaran.
        </p>
      </div>

      <button
        @click="openAddModal"
        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white font-bold text-xs shadow-md shadow-emerald-600/20 hover:bg-emerald-700 transition"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Tambah CP / TP
      </button>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-xs">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Capaian Terdaftar</span>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-3xl font-extrabold text-slate-900">{{ loading ? '...' : items.length }}</span>
          <span class="text-xs text-slate-500">butir kompetensi</span>
        </div>
      </div>
      <div class="p-5 rounded-2xl bg-white border border-emerald-100 shadow-xs">
        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Total Alokasi Waktu</span>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-3xl font-extrabold text-emerald-800">{{ loading ? '...' : totalTargetHours }}</span>
          <span class="text-xs text-emerald-600">Jam Pelajaran (JP)</span>
        </div>
      </div>
      <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-xs">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Fase Kurikulum</span>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-lg font-bold text-slate-800">Fase E & F</span>
          <span class="text-xs text-slate-500">(Kelas 10, 11, 12)</span>
        </div>
      </div>
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
          placeholder="Cari kode, judul, atau deskripsi kompetensi..."
          class="w-full pl-9 pr-4 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
        />
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <!-- Filter Mapel -->
        <select
          v-model="selectedSubject"
          class="px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="">Semua Mata Pelajaran</option>
          <option v-for="subj in subjects" :key="subj.id" :value="subj.id">
            {{ subj.name }}
          </option>
        </select>

        <!-- Filter Tingkat -->
        <select
          v-model="selectedGrade"
          class="px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="">Semua Tingkat</option>
          <option value="10">Kelas 10</option>
          <option value="11">Kelas 11</option>
          <option value="12">Kelas 12</option>
        </select>

        <!-- Filter Fase -->
        <select
          v-model="selectedPhase"
          class="px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="">Semua Fase</option>
          <option value="E">Fase E (Kelas 10)</option>
          <option value="F">Fase F (Kelas 11-12)</option>
        </select>
      </div>
    </div>

    <!-- Table Content -->
    <div class="rounded-2xl bg-white border border-slate-200/80 shadow-xs overflow-hidden">
      <div v-if="loading" class="p-12 text-center text-xs text-slate-400">
        <svg class="animate-spin h-6 w-6 text-emerald-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Memuat data silabus & ATP...
      </div>

      <div v-else-if="filteredItems.length === 0" class="p-12 text-center">
        <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <h3 class="text-sm font-bold text-slate-800">Belum ada Capaian Pembelajaran</h3>
        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
          Silakan tambahkan Capaian Pembelajaran (CP) dan Alur Tujuan Pembelajaran (ATP) pertama Anda.
        </p>
        <button
          @click="openAddModal"
          class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 text-xs font-bold rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100"
        >
          + Tambah CP Sekarang
        </button>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/70 text-[11px] font-bold uppercase tracking-wider text-slate-500">
              <th class="px-5 py-3.5">Kode</th>
              <th class="px-5 py-3.5">Mata Pelajaran</th>
              <th class="px-5 py-3.5">Fase / Kelas</th>
              <th class="px-5 py-3.5">Alokasi</th>
              <th class="px-5 py-3.5">Tujuan & Elemen Kompetensi</th>
              <th class="px-5 py-3.5 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <tr v-for="item in filteredItems" :key="item.id" class="hover:bg-slate-50/60 transition">
              <td class="px-5 py-4 font-extrabold text-emerald-800 whitespace-nowrap">
                <span class="px-2 py-1 rounded-md bg-emerald-50 border border-emerald-200/60 font-mono">
                  {{ item.code }}
                </span>
              </td>
              <td class="px-5 py-4 font-bold text-slate-800 whitespace-nowrap">
                {{ item.subject?.name || 'Mata Pelajaran' }}
              </td>
              <td class="px-5 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">
                  Fase {{ item.phase }} (Kelas {{ item.grade_level }})
                </span>
              </td>
              <td class="px-5 py-4 font-semibold text-slate-700 whitespace-nowrap">
                {{ item.target_hours }} JP
              </td>
              <td class="px-5 py-4">
                <p class="font-bold text-slate-900 leading-tight">{{ item.title }}</p>
                <p class="text-[11px] text-slate-500 mt-1 line-clamp-2 leading-relaxed">
                  {{ item.description }}
                </p>
              </td>
              <td class="px-5 py-4 text-right whitespace-nowrap space-x-1">
                <button
                  @click="openEditModal(item)"
                  class="p-1.5 rounded-lg text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 transition"
                  title="Edit"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                <button
                  @click="deleteObjective(item.id)"
                  class="p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition"
                  title="Hapus"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Form Tambah/Edit CP & TP -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in"
    >
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="font-extrabold text-slate-900 text-sm">
            {{ editingId ? 'Edit Capaian Pembelajaran' : 'Tambah Capaian Pembelajaran (CP/TP)' }}
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

        <form @submit.prevent="saveObjective" class="space-y-3.5 text-xs">
          <!-- Mata Pelajaran -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Mata Pelajaran *</label>
            <select
              v-model="form.subject_id"
              required
              class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
            >
              <option value="" disabled>Pilih Mata Pelajaran</option>
              <option v-for="subj in subjects" :key="subj.id" :value="String(subj.id)">
                {{ subj.name }} ({{ subj.code }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <!-- Tingkat Kelas -->
            <div>
              <label class="block font-bold text-slate-700 mb-1">Tingkat Kelas *</label>
              <select
                v-model="form.grade_level"
                @change="onGradeChange"
                required
                class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
              >
                <option value="10">Kelas 10</option>
                <option value="11">Kelas 11</option>
                <option value="12">Kelas 12</option>
              </select>
            </div>

            <!-- Fase Kurikulum -->
            <div>
              <label class="block font-bold text-slate-700 mb-1">Fase Kurikulum</label>
              <input
                v-model="form.phase"
                type="text"
                readonly
                class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-slate-500 font-bold focus:outline-none"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <!-- Kode Kompetensi -->
            <div>
              <label class="block font-bold text-slate-700 mb-1">Kode CP/TP *</label>
              <input
                v-model="form.code"
                type="text"
                placeholder="Contoh: MAT-F.01"
                required
                class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono"
              />
            </div>

            <!-- Target Jam (JP) -->
            <div>
              <label class="block font-bold text-slate-700 mb-1">Target Alokasi (JP) *</label>
              <input
                v-model.number="form.target_hours"
                type="number"
                min="1"
                placeholder="18"
                required
                class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
          </div>

          <!-- Judul Tujuan Pembelajaran -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Judul / Topik Capaian *</label>
            <input
              v-model="form.title"
              type="text"
              placeholder="Contoh: Memahami Fungsi Trigonometri dan Penerapannya"
              required
              class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
          </div>

          <!-- Deskripsi Lengkap -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Deskripsi Kompetensi / Alur Pembelajaran *</label>
            <textarea
              v-model="form.description"
              rows="3"
              placeholder="Jelaskan indikator ketercapaian, ruang lingkup materi, serta kompetensi yang diharapkan..."
              required
              class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            ></textarea>
          </div>

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
              :disabled="saving"
              class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold shadow-md shadow-emerald-600/20 hover:bg-emerald-700 disabled:opacity-50 transition"
            >
              {{ saving ? 'Menyimpan...' : 'Simpan CP/TP' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
