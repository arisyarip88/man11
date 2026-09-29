<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'

definePageMeta({
  layout: 'teacher',
  middleware: 'auth'
})

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const storageBase = config.public.storageBase || 'http://localhost:8000/storage'
const token = useCookie<string | null>('auth_token')
const { user, hasRole } = useAuth()

const isVerifier = computed(() => {
  return hasRole('admin') || hasRole('super_admin') || hasRole('kurikulum') || user.value?.role === 'admin'
})

// State list & loading
const journals = ref<any[]>([])
const loading = ref(true)
const saving = ref(false)
const verifying = ref(false)

// Filter state
const search = ref('')
const statusFilter = ref('')
const rombelFilter = ref('')
const dateFilter = ref('')

// Dropdowns data
const rombels = ref<any[]>([])
const subjects = ref<any[]>([])
const meets = ref<any[]>([])

// Modals
const showFormModal = ref(false)
const isEditing = ref(false)
const editingId = ref<number | null>(null)
const showDetailModal = ref(false)
const selectedJournal = ref<any>(null)
const showVerifyModal = ref(false)
const verifyingJournal = ref<any>(null)
const previewImageModal = ref<string | null>(null)

// Form state
const journalForm = reactive({
  rombel_id: '',
  subject_id: '',
  meet_id: '',
  date: new Date().toISOString().substring(0, 10),
  session_time: 'Jam 1-2 (07.00 - 08.20)',
  topic: '',
  activity_summary: '',
  notes: '',
  att_h: 36,
  att_s: 0,
  att_i: 0,
  att_a: 0,
})

const photoFile = ref<File | null>(null)
const photoPreview = ref<string | null>(null)

// Verify Form
const verifyForm = reactive({
  approval_status: 'approved',
  approval_notes: '',
})

// Session Time Presets
const sessionTimePresets = [
  'Jam 1-2 (07.00 - 08.20)',
  'Jam 3-4 (08.20 - 09.40)',
  'Jam 5-6 (10.00 - 11.20)',
  'Jam 7-8 (11.20 - 12.40)',
  'Jam 9-10 (13.15 - 14.35)',
]

// KPI Counters
const kpiStats = computed(() => {
  const total = journals.value.length
  let approved = 0
  let pending = 0
  let rejected = 0

  journals.value.forEach(j => {
    if (j.approval_status === 'approved') approved++
    else if (j.approval_status === 'rejected') rejected++
    else pending++
  })

  return { total, approved, pending, rejected }
})

// Fetch Journals
const fetchJournals = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (search.value) params.append('search', search.value)
    if (statusFilter.value) params.append('approval_status', statusFilter.value)
    if (rombelFilter.value) params.append('rombel_id', rombelFilter.value)
    if (dateFilter.value) params.append('date', dateFilter.value)
    params.append('all', 'true')

    const res: any = await $fetch(`${apiBase}/teacher-journals?${params.toString()}`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    journals.value = res.data || res || []
  } catch (err) {
    console.error('Fetch journals error:', err)
  } finally {
    loading.value = false
  }
}

// Fetch Dropdown options
const fetchDropdownOptions = async () => {
  try {
    const [rombelRes, subjectRes]: [any, any] = await Promise.all([
      $fetch(`${apiBase}/rombel?all=true`, { headers: { Authorization: `Bearer ${token.value}` } }),
      $fetch(`${apiBase}/subjects?all=true`, { headers: { Authorization: `Bearer ${token.value}` } }),
    ])
    rombels.value = rombelRes.data || rombelRes || []
    subjects.value = subjectRes.data || subjectRes || []
  } catch (err) {
    console.error('Fetch dropdown options error:', err)
  }
}

// Watch rombel change to load meets if applicable
watch(() => journalForm.rombel_id, async (newRombelId) => {
  if (!newRombelId) {
    meets.value = []
    return
  }
  try {
    const res: any = await $fetch(`${apiBase}/meets?class_id=${newRombelId}&paginate=false`, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    meets.value = res.data || res || []
  } catch (err) {
    meets.value = []
  }
})

// Photo handler
const handlePhotoSelect = (e: any) => {
  const file = e.target.files?.[0]
  if (file) {
    photoFile.value = file
    const reader = new FileReader()
    reader.onload = (event) => {
      photoPreview.value = event.target?.result as string
    }
    reader.readAsDataURL(file)
  }
}

const clearPhoto = () => {
  photoFile.value = null
  photoPreview.value = null
}

// Open Create Modal
const openCreateModal = () => {
  isEditing.value = false
  editingId.value = null
  Object.assign(journalForm, {
    rombel_id: rombels.value[0]?.id || '',
    subject_id: subjects.value[0]?.id || '',
    meet_id: '',
    date: new Date().toISOString().substring(0, 10),
    session_time: 'Jam 1-2 (07.00 - 08.20)',
    topic: '',
    activity_summary: '',
    notes: '',
    att_h: 36,
    att_s: 0,
    att_i: 0,
    att_a: 0,
  })
  photoFile.value = null
  photoPreview.value = null
  showFormModal.value = true
}

// Open Edit Modal
const openEditModal = (journal: any) => {
  isEditing.value = true
  editingId.value = journal.id
  Object.assign(journalForm, {
    rombel_id: journal.rombel_id,
    subject_id: journal.subject_id,
    meet_id: journal.meet_id || '',
    date: journal.date,
    session_time: journal.session_time || 'Jam 1-2 (07.00 - 08.20)',
    topic: journal.topic,
    activity_summary: journal.activity_summary,
    notes: journal.notes || '',
    att_h: journal.attendance_stats?.H ?? 0,
    att_s: journal.attendance_stats?.S ?? 0,
    att_i: journal.attendance_stats?.I ?? 0,
    att_a: journal.attendance_stats?.A ?? 0,
  })
  photoFile.value = null
  photoPreview.value = journal.photo_evidence ? `${storageBase}/${journal.photo_evidence}` : null
  showFormModal.value = true
}

// Save / Submit Journal
const handleSaveJournal = async () => {
  if (!journalForm.rombel_id || !journalForm.subject_id || !journalForm.topic || !journalForm.activity_summary) {
    alert('Mohon lengkapi rombel, mata pelajaran, topik materi, dan rangkuman aktivitas.')
    return
  }

  saving.value = true
  try {
    const formData = new FormData()
    formData.append('rombel_id', String(journalForm.rombel_id))
    formData.append('subject_id', String(journalForm.subject_id))
    if (journalForm.meet_id) formData.append('meet_id', String(journalForm.meet_id))
    formData.append('date', journalForm.date)
    formData.append('session_time', journalForm.session_time)
    formData.append('topic', journalForm.topic)
    formData.append('activity_summary', journalForm.activity_summary)
    if (journalForm.notes) formData.append('notes', journalForm.notes)

    formData.append('attendance_stats[H]', String(journalForm.att_h))
    formData.append('attendance_stats[S]', String(journalForm.att_s))
    formData.append('attendance_stats[I]', String(journalForm.att_i))
    formData.append('attendance_stats[A]', String(journalForm.att_a))

    if (photoFile.value) {
      formData.append('photo', photoFile.value)
    }

    if (isEditing.value && editingId.value) {
      formData.append('_method', 'PUT')
      await $fetch(`${apiBase}/teacher-journals/${editingId.value}`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token.value}` },
        body: formData,
      })
    } else {
      await $fetch(`${apiBase}/teacher-journals`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token.value}` },
        body: formData,
      })
    }

    showFormModal.value = false
    await fetchJournals()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal menyimpan jurnal mengajar.')
  } finally {
    saving.value = false
  }
}

// Delete Journal
const handleDeleteJournal = async (id: number) => {
  if (!confirm('Apakah Anda yakin ingin menghapus jurnal mengajar ini?')) return

  try {
    await $fetch(`${apiBase}/teacher-journals/${id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${token.value}` }
    })
    await fetchJournals()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal menghapus jurnal mengajar.')
  }
}

// Detail Modal
const openDetailModal = (journal: any) => {
  selectedJournal.value = journal
  showDetailModal.value = true
}

// Verify Modal (Waka Kurikulum / Admin)
const openVerifyModal = (journal: any) => {
  verifyingJournal.value = journal
  verifyForm.approval_status = journal.approval_status === 'pending' ? 'approved' : journal.approval_status
  verifyForm.approval_notes = journal.approval_notes || ''
  showVerifyModal.value = true
}

const handleVerifyJournal = async () => {
  if (!verifyingJournal.value) return
  verifying.value = true
  try {
    await $fetch(`${apiBase}/teacher-journals/${verifyingJournal.value.id}/verify`, {
      method: 'PATCH',
      headers: { Authorization: `Bearer ${token.value}` },
      body: verifyForm,
    })
    showVerifyModal.value = false
    if (showDetailModal.value && selectedJournal.value?.id === verifyingJournal.value.id) {
      selectedJournal.value.approval_status = verifyForm.approval_status
      selectedJournal.value.approval_notes = verifyForm.approval_notes
    }
    await fetchJournals()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal memperbarui status verifikasi jurnal.')
  } finally {
    verifying.value = false
  }
}

const resetFilters = () => {
  search.value = ''
  statusFilter.value = ''
  rombelFilter.value = ''
  dateFilter.value = ''
  fetchJournals()
}

onMounted(async () => {
  await fetchDropdownOptions()
  await fetchJournals()
})
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
      <!-- Breadcrumb -->
      <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <NuxtLink to="/teacher/dashboard" class="hover:text-emerald-700 transition">Dashboard Guru</NuxtLink>
        <span>/</span>
        <span class="text-slate-800">Jurnal Mengajar</span>
      </nav>

      <!-- Page Header -->
      <div class="mt-3 flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
          <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800 border border-emerald-200/60">
            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
            Aktivitas Harian Pembelajaran
          </div>
          <h1 class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
            Jurnal Mengajar Guru
          </h1>
          <p class="mt-1 text-xs sm:text-sm text-slate-500">
            Dokumentasi materi ajar harian, rekapan kehadiran, catatan kendala kelas, dan verifikasi Waka Kurikulum.
          </p>
        </div>

        <button
          @click="openCreateModal"
          type="button"
          class="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-5 py-3 text-xs font-bold text-white shadow-md shadow-emerald-600/20 hover:bg-emerald-700 transition transform active:scale-95"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Tulis Jurnal Baru
        </button>
      </div>

      <!-- KPI Summary Cards -->
      <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs">
          <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Jurnal</p>
          <p class="mt-2 text-3xl font-black text-slate-900">{{ kpiStats.total }}</p>
          <p class="mt-1 text-[11px] text-slate-500">Seluruh sesi mengajar</p>
        </div>

        <div class="rounded-3xl border border-amber-200/80 bg-amber-50/60 p-5 shadow-xs">
          <p class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Menunggu Verifikasi</p>
          <p class="mt-2 text-3xl font-black text-amber-800">{{ kpiStats.pending }}</p>
          <p class="mt-1 text-[11px] text-amber-600">Perlu ditinjau Kurikulum</p>
        </div>

        <div class="rounded-3xl border border-emerald-200/80 bg-emerald-50/60 p-5 shadow-xs">
          <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Disetujui</p>
          <p class="mt-2 text-3xl font-black text-emerald-800">{{ kpiStats.approved }}</p>
          <p class="mt-1 text-[11px] text-emerald-600">Terverifikasi resmi</p>
        </div>

        <div class="rounded-3xl border border-rose-200/80 bg-rose-50/60 p-5 shadow-xs">
          <p class="text-[11px] font-bold uppercase tracking-wider text-rose-700">Perlu Revisi</p>
          <p class="mt-2 text-3xl font-black text-rose-800">{{ kpiStats.rejected }}</p>
          <p class="mt-1 text-[11px] text-rose-600">Catatan dari Kurikulum</p>
        </div>
      </div>

      <!-- Filter & Search Toolbar -->
      <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-4 shadow-xs space-y-3">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <!-- Search input -->
          <div class="relative lg:col-span-2">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="search"
              @keyup.enter="fetchJournals"
              type="text"
              placeholder="Cari topik materi atau catatan..."
              class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-2xl text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            />
          </div>

          <!-- Status Filter -->
          <div>
            <select
              v-model="statusFilter"
              @change="fetchJournals"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            >
              <option value="">Semua Status Approval</option>
              <option value="pending">Menunggu Verifikasi (Pending)</option>
              <option value="approved">Disetujui (Approved)</option>
              <option value="rejected">Perlu Revisi (Rejected)</option>
            </select>
          </div>

          <!-- Rombel Filter -->
          <div>
            <select
              v-model="rombelFilter"
              @change="fetchJournals"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            >
              <option value="">Semua Rombel</option>
              <option v-for="r in rombels" :key="r.id" :value="r.id">
                {{ r.class_name?.name || r.className?.name || r.name || 'Kelas' }}
              </option>
            </select>
          </div>

          <!-- Date Filter -->
          <div class="flex gap-2">
            <input
              v-model="dateFilter"
              @change="fetchJournals"
              type="date"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            />
            <button
              @click="resetFilters"
              type="button"
              class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-2xl text-xs font-bold transition shrink-0"
              title="Reset Filter"
            >
              Reset
            </button>
          </div>
        </div>
      </div>

      <!-- Journal List / Table -->
      <div class="mt-6 rounded-3xl border border-slate-200 bg-white shadow-xs overflow-hidden">
        <div v-if="loading" class="p-12 text-center text-xs text-slate-400">
          Memuat riwayat jurnal mengajar...
        </div>

        <div v-else-if="journals.length === 0" class="p-12 text-center">
          <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 mb-3">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
          </div>
          <p class="font-extrabold text-slate-800 text-sm">Belum Ada Jurnal Mengajar</p>
          <p class="mt-1 text-xs text-slate-500">Mulai catat materi ajar harian dan kehadiran siswa sekarang.</p>
          <button
            @click="openCreateModal"
            class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 transition"
          >
            + Tulis Jurnal Harian
          </button>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-100 text-slate-500 uppercase tracking-wider text-[10px]">
                <th class="py-3.5 px-4 font-bold w-12 text-center">No</th>
                <th class="py-3.5 px-4 font-bold w-40">Tanggal & Jam</th>
                <th class="py-3.5 px-4 font-bold w-44">Kelas & Mapel</th>
                <th class="py-3.5 px-4 font-bold">Topik / Materi Pembelajaran</th>
                <th class="py-3.5 px-4 font-bold text-center w-28">Presensi</th>
                <th class="py-3.5 px-4 font-bold text-center w-20">Foto</th>
                <th class="py-3.5 px-4 font-bold text-center w-36">Status Approval</th>
                <th class="py-3.5 px-4 font-bold text-center w-36">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="(j, idx) in journals" :key="j.id" class="hover:bg-slate-50/70 transition">
                <td class="py-3.5 px-4 text-center font-bold text-slate-400">{{ idx + 1 }}</td>
                <td class="py-3.5 px-4">
                  <div class="font-bold text-slate-900">{{ j.date }}</div>
                  <div class="text-[10px] text-slate-500 font-semibold mt-0.5">{{ j.session_time }}</div>
                </td>
                <td class="py-3.5 px-4">
                  <span class="inline-block px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 text-[10px] font-bold border border-emerald-200/60">
                    {{ j.rombel?.class_name?.name || j.rombel?.className?.name || 'Kelas' }}
                  </span>
                  <div class="text-xs font-bold text-slate-800 mt-1">{{ j.subject?.name }}</div>
                </td>
                <td class="py-3.5 px-4 max-w-xs">
                  <p class="font-bold text-slate-900 line-clamp-1">{{ j.topic }}</p>
                  <p class="text-slate-500 text-[11px] line-clamp-2 mt-0.5">{{ j.activity_summary }}</p>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <div v-if="j.attendance_stats" class="inline-flex items-center gap-1 text-[10px] font-bold bg-slate-100 px-2 py-1 rounded-lg">
                    <span class="text-emerald-700" title="Hadir">H:{{ j.attendance_stats.H ?? 0 }}</span>
                    <span class="text-slate-300">•</span>
                    <span class="text-amber-700" title="Sakit">S:{{ j.attendance_stats.S ?? 0 }}</span>
                    <span class="text-slate-300">•</span>
                    <span class="text-sky-700" title="Izin">I:{{ j.attendance_stats.I ?? 0 }}</span>
                    <span class="text-slate-300">•</span>
                    <span class="text-rose-700" title="Alpa">A:{{ j.attendance_stats.A ?? 0 }}</span>
                  </div>
                  <span v-else class="text-slate-400 text-[10px]">-</span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <button
                    v-if="j.photo_evidence"
                    @click="previewImageModal = `${storageBase}/${j.photo_evidence}`"
                    type="button"
                    class="h-8 w-8 rounded-lg overflow-hidden border border-slate-200 inline-block hover:scale-110 transition shadow-2xs"
                  >
                    <img :src="`${storageBase}/${j.photo_evidence}`" class="h-full w-full object-cover" alt="Foto kegiatan" />
                  </button>
                  <span v-else class="text-slate-400 text-[10px]">-</span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span
                    class="inline-block px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"
                    :class="{
                      'bg-amber-100 text-amber-800 border border-amber-200': j.approval_status === 'pending',
                      'bg-emerald-100 text-emerald-800 border border-emerald-200': j.approval_status === 'approved',
                      'bg-rose-100 text-rose-800 border border-rose-200': j.approval_status === 'rejected'
                    }"
                  >
                    {{ j.approval_status === 'pending' ? 'Pending' : j.approval_status === 'approved' ? 'Disetujui' : 'Ditolak' }}
                  </span>
                  <div v-if="j.approval_notes" class="text-[10px] text-rose-600 font-semibold line-clamp-1 mt-0.5">
                    "{{ j.approval_notes }}"
                  </div>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <button
                      @click="openDetailModal(j)"
                      class="p-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition"
                      title="Lihat Detail"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </button>

                    <button
                      v-if="j.approval_status === 'pending'"
                      @click="openEditModal(j)"
                      class="p-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition"
                      title="Edit Jurnal"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>

                    <button
                      v-if="j.approval_status === 'pending'"
                      @click="handleDeleteJournal(j.id)"
                      class="p-1.5 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 transition"
                      title="Hapus Jurnal"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>

                    <!-- Verifier Button (Kurikulum / Admin) -->
                    <button
                      v-if="isVerifier"
                      @click="openVerifyModal(j)"
                      class="p-1.5 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition"
                      title="Verifikasi Jurnal"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL FORM JURNAL (CREATE / EDIT) -->
    <div v-if="showFormModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs overflow-y-auto">
      <div class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl space-y-5 my-8 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Form Jurnal Harian</span>
            <h3 class="text-xl font-extrabold text-slate-900 mt-0.5">
              {{ isEditing ? 'Edit Jurnal Mengajar' : 'Tulis Jurnal Mengajar Harian' }}
            </h3>
          </div>
          <button @click="showFormModal = false" class="text-slate-400 hover:text-slate-600 p-2 rounded-xl hover:bg-slate-100 transition">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="handleSaveJournal" class="space-y-4 text-xs">
          <!-- Row 1: Rombel & Mapel -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Pilih Kelas / Rombel *</label>
              <select
                v-model="journalForm.rombel_id"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                required
              >
                <option value="" disabled>-- Pilih Rombel --</option>
                <option v-for="r in rombels" :key="r.id" :value="r.id">
                  {{ r.class_name?.name || r.className?.name || r.name || 'Kelas' }}
                </option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Mata Pelajaran *</label>
              <select
                v-model="journalForm.subject_id"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                required
              >
                <option value="" disabled>-- Pilih Mapel --</option>
                <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
          </div>

          <!-- Row 2: Tanggal & Jam Ke -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Tanggal Mengajar *</label>
              <input
                v-model="journalForm.date"
                type="date"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                required
              />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Jam Pelajaran / Sesi Waktu *</label>
              <input
                v-model="journalForm.session_time"
                type="text"
                placeholder="Contoh: Jam 1-2 (07.00 - 08.20)"
                class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                required
              />
              <div class="flex flex-wrap gap-1 mt-1.5">
                <button
                  v-for="preset in sessionTimePresets"
                  :key="preset"
                  type="button"
                  @click="journalForm.session_time = preset"
                  class="px-2 py-0.5 rounded-md bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 text-[10px] text-slate-600 transition"
                >
                  {{ preset.split(' ')[0] + ' ' + preset.split(' ')[1] }}
                </button>
              </div>
            </div>
          </div>

          <!-- Row 3: Topik Materi -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Topik / Materi Pokok *</label>
            <input
              v-model="journalForm.topic"
              type="text"
              placeholder="Contoh: Bab 2 - Reaksi Redoks dan Elektrokimia"
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              required
            />
          </div>

          <!-- Row 4: Rangkuman Aktivitas -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Rangkuman Aktivitas & Capaian Pembelajaran *</label>
            <textarea
              v-model="journalForm.activity_summary"
              rows="3"
              placeholder="Jelaskan alur pembelajaran di kelas, metode yang digunakan, dan capaian siswa..."
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              required
            ></textarea>
          </div>

          <!-- Row 5: Catatan Hambatan / Refleksi -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Catatan Hambatan / Kejadian di Kelas (Opsional)</label>
            <textarea
              v-model="journalForm.notes"
              rows="2"
              placeholder="Misal: Terdapat 2 siswa terlambat karena piket, proyektor sempat bermasalah..."
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            ></textarea>
          </div>

          <!-- Row 6: Statistik Presensi Siswa -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Rekap Presensi Siswa</label>
            <div class="grid grid-cols-4 gap-2 bg-slate-50 p-3 rounded-2xl border border-slate-200/80">
              <div>
                <span class="block text-[10px] font-bold text-emerald-700">Hadir (H)</span>
                <input
                  v-model.number="journalForm.att_h"
                  type="number"
                  min="0"
                  class="mt-1 w-full p-1.5 bg-white border border-slate-200 rounded-lg text-center font-bold text-slate-800"
                />
              </div>
              <div>
                <span class="block text-[10px] font-bold text-amber-700">Sakit (S)</span>
                <input
                  v-model.number="journalForm.att_s"
                  type="number"
                  min="0"
                  class="mt-1 w-full p-1.5 bg-white border border-slate-200 rounded-lg text-center font-bold text-slate-800"
                />
              </div>
              <div>
                <span class="block text-[10px] font-bold text-sky-700">Izin (I)</span>
                <input
                  v-model.number="journalForm.att_i"
                  type="number"
                  min="0"
                  class="mt-1 w-full p-1.5 bg-white border border-slate-200 rounded-lg text-center font-bold text-slate-800"
                />
              </div>
              <div>
                <span class="block text-[10px] font-bold text-rose-700">Alpa (A)</span>
                <input
                  v-model.number="journalForm.att_a"
                  type="number"
                  min="0"
                  class="mt-1 w-full p-1.5 bg-white border border-slate-200 rounded-lg text-center font-bold text-slate-800"
                />
              </div>
            </div>
          </div>

          <!-- Row 7: Foto Bukti Kegiatan -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Unggah Foto Dokumentasi Pembelajaran (Opsional)</label>
            <div class="flex items-center gap-4">
              <input
                type="file"
                accept="image/*"
                @change="handlePhotoSelect"
                class="text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
              />
              <button
                v-if="photoPreview"
                @click="clearPhoto"
                type="button"
                class="text-rose-600 font-bold hover:underline text-xs"
              >
                Hapus Foto
              </button>
            </div>

            <!-- Preview thumbnail -->
            <div v-if="photoPreview" class="mt-3 relative inline-block rounded-2xl overflow-hidden border border-slate-200 max-h-48">
              <img :src="photoPreview" class="max-h-48 object-contain bg-black/5" alt="Preview foto" />
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <button
              type="button"
              @click="showFormModal = false"
              class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-100 transition"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-6 py-2.5 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 transition shadow-sm flex items-center gap-2 disabled:opacity-50"
            >
              <svg v-if="saving" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>{{ saving ? 'Menyimpan...' : 'Kirim Jurnal ke Kurikulum' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL DETAIL JURNAL -->
    <div v-if="showDetailModal && selectedJournal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs overflow-y-auto">
      <div class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl space-y-6 my-8 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <div>
            <div class="flex items-center gap-2">
              <span
                class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                :class="{
                  'bg-amber-100 text-amber-800': selectedJournal.approval_status === 'pending',
                  'bg-emerald-100 text-emerald-800': selectedJournal.approval_status === 'approved',
                  'bg-rose-100 text-rose-800': selectedJournal.approval_status === 'rejected'
                }"
              >
                {{ selectedJournal.approval_status === 'pending' ? 'Menunggu Verifikasi' : selectedJournal.approval_status === 'approved' ? 'Disetujui' : 'Perlu Revisi' }}
              </span>
              <span class="text-xs text-slate-400 font-semibold">{{ selectedJournal.date }}</span>
            </div>
            <h3 class="text-xl font-extrabold text-slate-900 mt-1">
              {{ selectedJournal.topic }}
            </h3>
          </div>
          <button @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600 p-2 rounded-xl hover:bg-slate-100 transition">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="space-y-4 text-xs">
          <!-- Meta Grid -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-4 rounded-2xl border border-slate-100">
            <div>
              <span class="text-[10px] font-bold text-slate-400 uppercase">Kelas</span>
              <p class="font-extrabold text-slate-800 mt-0.5">{{ selectedJournal.rombel?.class_name?.name || selectedJournal.rombel?.className?.name || '-' }}</p>
            </div>
            <div>
              <span class="text-[10px] font-bold text-slate-400 uppercase">Mata Pelajaran</span>
              <p class="font-extrabold text-slate-800 mt-0.5">{{ selectedJournal.subject?.name }}</p>
            </div>
            <div>
              <span class="text-[10px] font-bold text-slate-400 uppercase">Sesi Waktu</span>
              <p class="font-extrabold text-slate-800 mt-0.5">{{ selectedJournal.session_time }}</p>
            </div>
            <div>
              <span class="text-[10px] font-bold text-slate-400 uppercase">Guru Pengampu</span>
              <p class="font-extrabold text-slate-800 mt-0.5">{{ selectedJournal.teacher?.name || user?.name }}</p>
            </div>
          </div>

          <!-- Rangkuman Aktivitas -->
          <div>
            <h4 class="font-bold text-slate-900 text-xs mb-1">Rangkuman Aktivitas & Capaian Pembelajaran:</h4>
            <div class="p-4 bg-slate-50 rounded-2xl text-slate-700 leading-relaxed whitespace-pre-line border border-slate-100">
              {{ selectedJournal.activity_summary }}
            </div>
          </div>

          <!-- Catatan / Kendala -->
          <div v-if="selectedJournal.notes">
            <h4 class="font-bold text-slate-900 text-xs mb-1">Catatan Hambatan / Refleksi Kelas:</h4>
            <div class="p-3 bg-amber-50/60 rounded-xl text-amber-900 leading-relaxed border border-amber-100">
              {{ selectedJournal.notes }}
            </div>
          </div>

          <!-- Presensi -->
          <div v-if="selectedJournal.attendance_stats">
            <h4 class="font-bold text-slate-900 text-xs mb-1">Rekapitulasi Kehadiran Siswa:</h4>
            <div class="grid grid-cols-4 gap-2 text-center">
              <div class="bg-emerald-50 p-2.5 rounded-xl border border-emerald-100 font-bold text-emerald-800">
                <span class="block text-[10px] uppercase">Hadir</span>
                <span class="text-base">{{ selectedJournal.attendance_stats.H ?? 0 }}</span>
              </div>
              <div class="bg-amber-50 p-2.5 rounded-xl border border-amber-100 font-bold text-amber-800">
                <span class="block text-[10px] uppercase">Sakit</span>
                <span class="text-base">{{ selectedJournal.attendance_stats.S ?? 0 }}</span>
              </div>
              <div class="bg-sky-50 p-2.5 rounded-xl border border-sky-100 font-bold text-sky-800">
                <span class="block text-[10px] uppercase">Izin</span>
                <span class="text-base">{{ selectedJournal.attendance_stats.I ?? 0 }}</span>
              </div>
              <div class="bg-rose-50 p-2.5 rounded-xl border border-rose-100 font-bold text-rose-800">
                <span class="block text-[10px] uppercase">Alpa</span>
                <span class="text-base">{{ selectedJournal.attendance_stats.A ?? 0 }}</span>
              </div>
            </div>
          </div>

          <!-- Foto Dokumentasi -->
          <div v-if="selectedJournal.photo_evidence">
            <h4 class="font-bold text-slate-900 text-xs mb-1">Dokumentasi Foto Kegiatan:</h4>
            <div
              @click="previewImageModal = `${storageBase}/${selectedJournal.photo_evidence}`"
              class="relative rounded-2xl overflow-hidden border border-slate-200 cursor-pointer group"
            >
              <img :src="`${storageBase}/${selectedJournal.photo_evidence}`" class="w-full max-h-64 object-cover" alt="Dokumentasi Jurnal" />
              <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white font-bold transition">
                Klik untuk memperbesar 🔍
              </div>
            </div>
          </div>

          <!-- Status Verifikasi Note -->
          <div v-if="selectedJournal.approval_notes || selectedJournal.verified_at" class="p-4 rounded-2xl border" :class="selectedJournal.approval_status === 'approved' ? 'bg-emerald-50/70 border-emerald-200' : 'bg-rose-50/70 border-rose-200'">
            <div class="flex items-center justify-between">
              <span class="font-bold" :class="selectedJournal.approval_status === 'approved' ? 'text-emerald-800' : 'text-rose-800'">
                Catatan Verifikasi Kurikulum:
              </span>
              <span class="text-[10px] text-slate-400">{{ selectedJournal.verified_at }}</span>
            </div>
            <p class="mt-1 font-semibold" :class="selectedJournal.approval_status === 'approved' ? 'text-emerald-900' : 'text-rose-900'">
              {{ selectedJournal.approval_notes || 'Telah disetujui tanpa catatan khusus.' }}
            </p>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="flex items-center justify-between pt-4 border-t border-slate-100">
          <div>
            <button
              v-if="isVerifier"
              @click="openVerifyModal(selectedJournal)"
              class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition"
            >
              Ubah Status Verifikasi
            </button>
          </div>
          <button
            @click="showDetailModal = false"
            class="px-5 py-2 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL VERIFIKASI KURIKULUM / ADMIN -->
    <div v-if="showVerifyModal && verifyingJournal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
      <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 border border-slate-100">
        <h3 class="text-lg font-extrabold text-slate-900">Verifikasi Jurnal Mengajar</h3>
        <p class="text-xs text-slate-500">
          Tinjau jurnal <b>{{ verifyingJournal.topic }}</b> oleh {{ verifyingJournal.teacher?.name || 'Guru' }}.
        </p>

        <form @submit.prevent="handleVerifyJournal" class="space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Keputusan Status *</label>
            <div class="grid grid-cols-2 gap-3">
              <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition" :class="verifyForm.approval_status === 'approved' ? 'bg-emerald-50 border-emerald-500 text-emerald-800' : 'bg-slate-50 border-slate-200 text-slate-700'">
                <input type="radio" v-model="verifyForm.approval_status" value="approved" class="text-emerald-600" />
                <span class="font-bold">✓ Setujui (Approved)</span>
              </label>
              <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition" :class="verifyForm.approval_status === 'rejected' ? 'bg-rose-50 border-rose-500 text-rose-800' : 'bg-slate-50 border-slate-200 text-slate-700'">
                <input type="radio" v-model="verifyForm.approval_status" value="rejected" class="text-rose-600" />
                <span class="font-bold">✕ Tolak (Revisi)</span>
              </label>
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Catatan Verifikator / Masukan Kurikulum</label>
            <textarea
              v-model="verifyForm.approval_notes"
              rows="3"
              placeholder="Berikan catatan perbaikan jika ditolak, atau pesan apresiasi..."
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              @click="showVerifyModal = false"
              class="px-4 py-2 border rounded-xl text-slate-600 font-bold hover:bg-slate-50"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="verifying"
              class="px-5 py-2 bg-emerald-600 text-white font-bold rounded-xl hover:bg-emerald-700 transition flex items-center gap-1.5"
            >
              <svg v-if="verifying" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>{{ verifying ? 'Memproses...' : 'Simpan Verifikasi' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- LIGHTBOX PREVIEW FOTO MODAL -->
    <div
      v-if="previewImageModal"
      @click="previewImageModal = null"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm cursor-zoom-out"
    >
      <div class="relative max-w-4xl max-h-[90vh]">
        <img :src="previewImageModal" class="rounded-2xl max-h-[90vh] object-contain shadow-2xl" alt="Preview Foto Dokumentasi" />
        <button
          @click="previewImageModal = null"
          class="absolute top-3 right-3 bg-black/60 text-white rounded-full p-2 hover:bg-black/90 transition"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>
  </main>
</template>
