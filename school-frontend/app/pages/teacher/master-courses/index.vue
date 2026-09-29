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

interface Semester {
  id: number
  academic_year: string
  semester_type: 'odd' | 'even'
  is_active: boolean
}

interface ClassRombel {
  id: number
  semester_id: number
  class_name_id: number
  className?: { id: number; name: string; grade_level: string }
  class_name?: { id: number; name: string; grade_level: string }
  pivot?: { synced_at: string | null }
}

interface MasterCourse {
  id: number
  teacher_id: number
  subject_id: number
  semester_id: number
  code: string
  name: string
  grade_level: string
  description: string
  is_active: boolean
  subject?: Subject
  semester?: Semester
  rombels?: ClassRombel[]
}

const loading = ref(true)
const actionLoading = ref(false)
const masterCourses = ref<MasterCourse[]>([])
const subjects = ref<Subject[]>([])
const semesters = ref<Semester[]>([])
const availableRombels = ref<ClassRombel[]>([])

// Filter State
const search = ref('')
const selectedGrade = ref('')
const selectedSubject = ref('')

// Modal Buat/Edit Master Course
const isCreateModalOpen = ref(false)
const editingId = ref<number | null>(null)
const form = ref({
  subject_id: '',
  semester_id: '',
  grade_level: '11',
  name: '',
  code: '',
  description: '',
  rombel_ids: [] as number[],
})
const formError = ref('')

// Modal Sinkronisasi Rombel
const isSyncModalOpen = ref(false)
const activeSyncMaster = ref<MasterCourse | null>(null)
const selectedSyncRombels = ref<number[]>([])
const syncFeedback = ref<{ message: string; type: 'success' | 'error' } | null>(null)

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
    const [mcRes, subjRes, semRes, rombelRes] = await Promise.all([
      api<any>('/master-courses?paginate=false'),
      api<any>('/subjects?all=true'),
      api<any>('/semesters?all=true'),
      api<any>('/rombel?all=true')
    ])
    masterCourses.value = mcRes?.data || []
    subjects.value = subjRes?.data || subjRes || []
    semesters.value = semRes?.data || semRes || []
    availableRombels.value = rombelRes?.data || rombelRes || []
  } catch (err: any) {
    console.error('Gagal mengambil data kelas master:', err)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchData()
})

const getRombelName = (rombel: ClassRombel) => {
  return rombel.className?.name || rombel.class_name?.name || `Kelas #${rombel.id}`
}

// Filtered Master Courses
const filteredMasterCourses = computed(() => {
  return masterCourses.value.filter(mc => {
    const matchSearch = !search.value || 
      mc.name.toLowerCase().includes(search.value.toLowerCase()) ||
      mc.code.toLowerCase().includes(search.value.toLowerCase()) ||
      (mc.subject?.name && mc.subject.name.toLowerCase().includes(search.value.toLowerCase()))
    
    const matchGrade = !selectedGrade.value || mc.grade_level === selectedGrade.value
    const matchSubject = !selectedSubject.value || String(mc.subject_id) === String(selectedSubject.value)

    return matchSearch && matchGrade && matchSubject
  })
})

// Total Connected Rombels Count
const totalConnectedRombels = computed(() => {
  const allIds = new Set<number>()
  masterCourses.value.forEach(mc => {
    (mc.rombels || []).forEach(r => allIds.add(r.id))
  })
  return allIds.size
})

// Open Create Modal
const openCreateModal = () => {
  editingId.value = null
  const activeSem = semesters.value.find(s => s.is_active) || semesters.value[0]
  form.value = {
    subject_id: subjects.value[0]?.id ? String(subjects.value[0].id) : '',
    semester_id: activeSem?.id ? String(activeSem.id) : '',
    grade_level: '11',
    name: '',
    code: `KM-${new Date().getFullYear()}-${Math.floor(1000 + Math.random() * 9000)}`,
    description: '',
    rombel_ids: [],
  }
  formError.value = ''
  isCreateModalOpen.value = true
}

// Open Edit Modal
const openEditModal = (mc: MasterCourse) => {
  editingId.value = mc.id
  form.value = {
    subject_id: String(mc.subject_id),
    semester_id: String(mc.semester_id),
    grade_level: mc.grade_level,
    name: mc.name,
    code: mc.code,
    description: mc.description || '',
    rombel_ids: (mc.rombels || []).map(r => r.id),
  }
  formError.value = ''
  isCreateModalOpen.value = true
}

// Save Master Course
const saveMasterCourse = async () => {
  if (!form.value.name || !form.value.subject_id || !form.value.semester_id) {
    formError.value = 'Mohon lengkapi seluruh field bertanda bintang (*).'
    return
  }

  actionLoading.value = true
  formError.value = ''
  try {
    if (editingId.value) {
      await api(`/master-courses/${editingId.value}`, {
        method: 'PUT',
        body: form.value,
      })
    } else {
      await api('/master-courses', {
        method: 'POST',
        body: form.value,
      })
    }
    isCreateModalOpen.value = false
    await fetchData()
  } catch (err: any) {
    formError.value = err?.data?.message || 'Gagal menyimpan Kelas Master.'
  } finally {
    actionLoading.value = false
  }
}

// Delete Master Course
const deleteMasterCourse = async (id: number) => {
  if (!confirm('Apakah Anda yakin ingin menghapus Kelas Master ini? Rombel yang terhubung akan dilepas hubungannya.')) return
  try {
    await api(`/master-courses/${id}`, { method: 'DELETE' })
    await fetchData()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal menghapus Kelas Master')
  }
}

// Open Sync Modal
const openSyncModal = (mc: MasterCourse) => {
  activeSyncMaster.value = mc
  selectedSyncRombels.value = (mc.rombels || []).map(r => r.id)
  syncFeedback.value = null
  isSyncModalOpen.value = true
}

// Select All Rombels of matching grade
const selectAllGradeRombels = () => {
  if (!activeSyncMaster.value) return
  const targetGrade = activeSyncMaster.value.grade_level
  const matched = availableRombels.value
    .filter(r => {
      const g = r.className?.grade_level || r.class_name?.grade_level
      return !g || g === targetGrade
    })
    .map(r => r.id)
  
  selectedSyncRombels.value = matched
}

// Execute Sync
const executeSync = async () => {
  if (!activeSyncMaster.value) return
  actionLoading.value = true
  syncFeedback.value = null
  try {
    const res = await api<any>(`/master-courses/${activeSyncMaster.value.id}/sync-rombels`, {
      method: 'POST',
      body: {
        rombel_ids: selectedSyncRombels.value,
      },
    })
    syncFeedback.value = {
      message: res?.message || 'Sinkronisasi berhasil dilakukan!',
      type: 'success',
    }
    await fetchData()
    setTimeout(() => {
      if (isSyncModalOpen.value) {
        isSyncModalOpen.value = false
      }
    }, 1200)
  } catch (err: any) {
    syncFeedback.value = {
      message: err?.data?.message || 'Gagal melakukan sinkronisasi rombel.',
      type: 'error',
    }
  } finally {
    actionLoading.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header Title & Info -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-1">
          <span>Efisiensi Pengajaran</span>
          <span>•</span>
          <span>Fitur Kelas Master</span>
        </div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Manajemen Kelas Master</h1>
        <p class="text-xs text-slate-500 mt-1">
          Buat satu template materi & kurikulum induk, lalu hubungkan ke beberapa rombel paralel (XI-1, XI-2, XI-3, XI-4).
        </p>
      </div>

      <button
        @click="openCreateModal"
        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white font-bold text-xs shadow-md shadow-emerald-600/20 hover:bg-emerald-700 transition"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Buat Master Kelas Baru
      </button>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-xs">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Kelas Master</span>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-3xl font-extrabold text-slate-900">{{ loading ? '...' : masterCourses.length }}</span>
          <span class="text-xs text-slate-500">kurikulum template</span>
        </div>
      </div>
      <div class="p-5 rounded-2xl bg-white border border-emerald-100 shadow-xs">
        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Rombel Terhubung</span>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-3xl font-extrabold text-emerald-800">{{ loading ? '...' : totalConnectedRombels }}</span>
          <span class="text-xs text-emerald-600">rombel aktif</span>
        </div>
      </div>
      <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-xs">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Efisiensi Guru</span>
        <div class="mt-2">
          <span class="text-sm font-bold text-slate-800 block">Single-Source Publishing</span>
          <span class="text-xs text-slate-500">1x upload tersinkron ke semua rombel</span>
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
          placeholder="Cari kode master, nama kelas, atau mata pelajaran..."
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
      </div>
    </div>

    <!-- Master Courses Grid -->
    <div v-if="loading" class="p-12 text-center text-xs text-slate-400 bg-white rounded-2xl border border-slate-100">
      <svg class="animate-spin h-6 w-6 text-emerald-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      Memuat daftar Kelas Master...
    </div>

    <div v-else-if="filteredMasterCourses.length === 0" class="p-12 text-center bg-white rounded-2xl border border-slate-200/80">
      <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
        </svg>
      </div>
      <h3 class="text-sm font-bold text-slate-800">Belum ada Kelas Master</h3>
      <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
        Mulai buat Master Kelas untuk menghubungkan materi Anda ke rombel-rombel paralel.
      </p>
      <button
        @click="openCreateModal"
        class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 text-xs font-bold rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100"
      >
        + Buat Master Kelas Sekarang
      </button>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="mc in filteredMasterCourses"
        :key="mc.id"
        class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between hover:shadow-md hover:border-emerald-300 transition-all"
      >
        <div>
          <!-- Header Card -->
          <div class="flex items-center justify-between gap-2 mb-3">
            <span class="px-2.5 py-1 rounded-md bg-emerald-50 border border-emerald-200/60 font-mono text-[11px] font-extrabold text-emerald-800">
              {{ mc.code }}
            </span>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
              Kelas {{ mc.grade_level }}
            </span>
          </div>

          <!-- Name & Subject -->
          <h3 class="font-extrabold text-slate-900 text-sm leading-snug">{{ mc.name }}</h3>
          <p class="text-xs font-semibold text-emerald-700 mt-0.5">
            {{ mc.subject?.name || 'Mata Pelajaran' }}
          </p>

          <p v-if="mc.description" class="text-[11px] text-slate-500 mt-2 line-clamp-2 leading-relaxed">
            {{ mc.description }}
          </p>

          <!-- Connected Rombels Section -->
          <div class="mt-4 pt-3 border-t border-slate-100">
            <div class="flex items-center justify-between mb-2">
              <span class="text-[11px] font-bold text-slate-600">Rombel Terhubung:</span>
              <span class="text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800">
                {{ (mc.rombels || []).length }} Rombel
              </span>
            </div>

            <div v-if="(mc.rombels || []).length" class="flex flex-wrap gap-1.5">
              <span
                v-for="rombel in mc.rombels"
                :key="rombel.id"
                class="px-2 py-1 rounded-lg bg-slate-50 border border-slate-200 text-[10px] font-bold text-slate-700"
              >
                {{ getRombelName(rombel) }}
              </span>
            </div>
            <div v-else class="text-[11px] text-amber-600 italic">
              Belum ada rombel terhubung.
            </div>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
          <button
            @click="openSyncModal(mc)"
            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white text-xs font-bold transition group"
          >
            <svg class="w-3.5 h-3.5 transition-transform group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Sinkronkan Rombel
          </button>

          <div class="flex items-center gap-1">
            <button
              @click="openEditModal(mc)"
              class="p-2 rounded-xl text-slate-400 hover:text-emerald-700 hover:bg-emerald-50 transition"
              title="Edit Master Course"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>
            <button
              @click="deleteMasterCourse(mc.id)"
              class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
              title="Hapus Master Course"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Buat / Edit Master Course -->
    <div
      v-if="isCreateModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in"
    >
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="font-extrabold text-slate-900 text-sm">
            {{ editingId ? 'Edit Kelas Master' : 'Buat Kelas Master Baru' }}
          </h3>
          <button @click="isCreateModalOpen = false" class="text-slate-400 hover:text-slate-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div v-if="formError" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700">
          {{ formError }}
        </div>

        <form @submit.prevent="saveMasterCourse" class="space-y-3.5 text-xs">
          <!-- Nama Master Course -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Nama Master Kelas *</label>
            <input
              v-model="form.name"
              type="text"
              placeholder="Contoh: Matematika Tingkat Lanjut - Kelas XI Induk"
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

            <!-- Tingkat -->
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

          <div class="grid grid-cols-2 gap-3">
            <!-- Semester -->
            <div>
              <label class="block font-bold text-slate-700 mb-1">Semester & Tahun Ajaran *</label>
              <select
                v-model="form.semester_id"
                required
                class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
              >
                <option value="" disabled>Pilih Semester</option>
                <option v-for="sem in semesters" :key="sem.id" :value="String(sem.id)">
                  {{ sem.academic_year }} ({{ sem.semester_type === 'odd' ? 'Ganjil' : 'Genap' }})
                </option>
              </select>
            </div>

            <!-- Kode Master -->
            <div>
              <label class="block font-bold text-slate-700 mb-1">Kode Master</label>
              <input
                v-model="form.code"
                type="text"
                placeholder="KM-2025-001"
                class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono"
              />
            </div>
          </div>

          <!-- Deskripsi -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Deskripsi & Catatan Kurikulum</label>
            <textarea
              v-model="form.description"
              rows="2"
              placeholder="Catatan mengenai materi yang akan diajarkan pada master kelas ini..."
              class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            ></textarea>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="isCreateModalOpen = false"
              class="px-4 py-2 rounded-xl border border-slate-200 font-semibold text-slate-600 hover:bg-slate-50 transition"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="actionLoading"
              class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold shadow-md shadow-emerald-600/20 hover:bg-emerald-700 disabled:opacity-50 transition"
            >
              {{ actionLoading ? 'Menyimpan...' : 'Simpan Master Kelas' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Kelola & Sinkronisasi Rombel -->
    <div
      v-if="isSyncModalOpen && activeSyncMaster"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in"
    >
      <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="font-extrabold text-slate-900 text-sm">Sinkronisasi Rombel Terhubung</h3>
            <p class="text-[11px] text-emerald-700 font-bold mt-0.5">{{ activeSyncMaster.name }}</p>
          </div>
          <button @click="isSyncModalOpen = false" class="text-slate-400 hover:text-slate-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div v-if="syncFeedback" :class="[
          'p-3 rounded-xl border text-xs',
          syncFeedback.type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-700'
        ]">
          {{ syncFeedback.message }}
        </div>

        <div class="space-y-3 text-xs">
          <div class="flex items-center justify-between">
            <span class="font-bold text-slate-700">Pilih Rombel yang Diampu:</span>
            <button
              type="button"
              @click="selectAllGradeRombels"
              class="text-[11px] font-bold text-emerald-700 hover:underline"
            >
              Pilih Semua Kelas {{ activeSyncMaster.grade_level }}
            </button>
          </div>

          <!-- Rombel Checkbox List -->
          <div class="max-h-60 overflow-y-auto space-y-2 p-1 border border-slate-100 rounded-2xl">
            <label
              v-for="rombel in availableRombels"
              :key="rombel.id"
              class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition select-none"
            >
              <div class="flex items-center gap-2.5">
                <input
                  type="checkbox"
                  :value="rombel.id"
                  v-model="selectedSyncRombels"
                  class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300"
                />
                <span class="font-bold text-slate-800">{{ getRombelName(rombel) }}</span>
              </div>
              <span class="text-[10px] text-slate-400">ID: {{ rombel.id }}</span>
            </label>
          </div>

          <p class="text-[11px] text-slate-400 leading-relaxed">
            * Menghubungkan rombel akan membuat instance course mata pelajaran secara otomatis di kelas-kelas tersebut.
          </p>

          <!-- Buttons -->
          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="isSyncModalOpen = false"
              class="px-4 py-2 rounded-xl border border-slate-200 font-semibold text-slate-600 hover:bg-slate-50 transition"
            >
              Batal
            </button>
            <button
              type="button"
              @click="executeSync"
              :disabled="actionLoading"
              class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold shadow-md shadow-emerald-600/20 hover:bg-emerald-700 disabled:opacity-50 transition"
            >
              {{ actionLoading ? 'Menyinkronkan...' : `Sinkronkan (${selectedSyncRombels.length} Rombel)` }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
