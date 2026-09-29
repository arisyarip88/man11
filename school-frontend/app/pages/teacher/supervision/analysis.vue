<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'

definePageMeta({
  layout: 'teacher',
  middleware: 'auth'
})

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie<string | null>('auth_token')

const classes = ref<any[]>([])
const selectedRombelId = ref<string>('')
const riskFilter = ref<string>('')
const searchQuery = ref<string>('')
const loading = ref(true)
const dataLoading = ref(false)

const rombelData = ref<any>(null)
const summaryData = ref<any>(null)
const analysesData = ref<any[]>([])

const selectedStudent = ref<any>(null)
const showActionModal = ref(false)
const actionNote = ref('')
const actionType = ref('bk_counseling')
const actionSuccessMsg = ref('')

// Fetch Classes
const fetchClasses = async () => {
  loading.value = true
  try {
    const res: any = await $fetch(`${apiBase}/supervision/classes`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    classes.value = res.data || res || []
    if (classes.value.length > 0) {
      selectedRombelId.value = String(classes.value[0].id)
      await fetchAnalysisData()
    }
  } catch (err) {
    console.error('Fetch supervision classes error:', err)
  } finally {
    loading.value = false
  }
}

// Fetch Analysis Data
const fetchAnalysisData = async () => {
  if (!selectedRombelId.value) return
  dataLoading.value = true
  try {
    const res: any = await $fetch(`${apiBase}/supervision/student-analysis/${selectedRombelId.value}`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })

    if (res?.data) {
      rombelData.value = res.data.rombel
      summaryData.value = res.data.summary
      analysesData.value = res.data.analyses || []
    }
  } catch (err) {
    console.error('Fetch analysis data error:', err)
  } finally {
    dataLoading.value = false
  }
}

watch(selectedRombelId, () => {
  fetchAnalysisData()
})

// Filtered Analyses
const filteredAnalyses = computed(() => {
  return analysesData.value.filter(item => {
    const matchRisk = !riskFilter.value || item.risk_level === riskFilter.value
    const matchQuery = !searchQuery.value.trim() ||
      (item.name && item.name.toLowerCase().includes(searchQuery.value.toLowerCase())) ||
      (item.nisn && item.nisn.toLowerCase().includes(searchQuery.value.toLowerCase()))
    return matchRisk && matchQuery
  })
})

const openActionModal = (student: any) => {
  selectedStudent.value = student
  actionType.value = student.risk_level === 'high' ? 'parent_meeting' : 'remedial'
  actionNote.value = ''
  actionSuccessMsg.value = ''
  showActionModal.value = true
}

const handleSaveAction = () => {
  actionSuccessMsg.value = `Rekomendasi tindak lanjut untuk ${selectedStudent.value?.name} berhasil dicatat dan diteruskan ke Guru BK / Kurikulum.`
  setTimeout(() => {
    showActionModal.value = false
    actionSuccessMsg.value = ''
  }, 2500)
}

onMounted(() => {
  fetchClasses()
})
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
      <!-- Breadcrumb -->
      <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <NuxtLink to="/teacher/dashboard" class="hover:text-emerald-700 transition">Dashboard</NuxtLink>
        <span>/</span>
        <span class="text-slate-400">Supervisi Wali Kelas</span>
        <span>/</span>
        <span class="text-slate-800">Analisa Siswa & EWS</span>
      </nav>

      <!-- Header Section -->
      <div class="mt-3 flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
          <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800 border border-emerald-200/60">
            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
            Early Warning System (EWS)
          </div>
          <h1 class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
            Analisa Siswa & Peringatan Dini
          </h1>
          <p class="mt-1 text-xs sm:text-sm text-slate-500">
            Deteksi otomatis siswa yang membutuhkan penanganan khusus dalam hal presensi kehadiran atau nilai akademik.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <NuxtLink
            to="/teacher/supervision/attendance"
            class="inline-flex items-center justify-center gap-1.5 rounded-2xl bg-white border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-xs transition"
          >
            📋 Rekap Presensi
          </NuxtLink>
          <NuxtLink
            to="/teacher/supervision/ledger"
            class="inline-flex items-center justify-center gap-1.5 rounded-2xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition"
          >
            📊 Ledger Nilai →
          </NuxtLink>
        </div>
      </div>

      <!-- Controls Bar -->
      <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-4 shadow-xs">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Pilih Rombel / Kelas Binaan</label>
            <select
              v-model="selectedRombelId"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            >
              <option v-for="c in classes" :key="c.id" :value="c.id">
                {{ c.class_name?.name || c.className?.name || c.name || 'Kelas' }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Status Level Risiko</label>
            <select
              v-model="riskFilter"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            >
              <option value="">Semua Siswa</option>
              <option value="high">🔴 Risiko Tinggi (Perlu Intervensi)</option>
              <option value="medium">🟡 Perlu Perhatian (Sedang)</option>
              <option value="low">🟢 Aman & Berprestasi</option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Cari Nama Siswa</label>
            <div class="relative">
              <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Cari nama atau NISN..."
                class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- KPI Summary Cards -->
      <div v-if="summaryData" class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs">
          <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Siswa Teranalisa</p>
          <p class="mt-2 text-3xl font-black text-slate-900">{{ summaryData.total_students }}</p>
          <p class="mt-1 text-[11px] text-slate-500">{{ rombelData?.class_name }}</p>
        </div>

        <div class="rounded-3xl border border-rose-200/80 bg-rose-50/60 p-5 shadow-xs">
          <p class="text-[11px] font-bold uppercase tracking-wider text-rose-700">Risiko Tinggi</p>
          <p class="mt-2 text-3xl font-black text-rose-800">{{ summaryData.high_risk_count }}</p>
          <p class="mt-1 text-[11px] text-rose-600">Perlu intervensi intensif & BK</p>
        </div>

        <div class="rounded-3xl border border-amber-200/80 bg-amber-50/60 p-5 shadow-xs">
          <p class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Perlu Perhatian</p>
          <p class="mt-2 text-3xl font-black text-amber-800">{{ summaryData.medium_risk_count }}</p>
          <p class="mt-1 text-[11px] text-amber-600">Pendampingan wali kelas</p>
        </div>

        <div class="rounded-3xl border border-emerald-200/80 bg-emerald-50/60 p-5 shadow-xs">
          <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Aman & Berprestasi</p>
          <p class="mt-2 text-3xl font-black text-emerald-800">{{ summaryData.safe_count }}</p>
          <p class="mt-1 text-[11px] text-emerald-600">Kinerja pembelajaran prima</p>
        </div>
      </div>

      <!-- Analysis List / Table -->
      <div class="mt-6 rounded-3xl border border-slate-200 bg-white shadow-xs overflow-hidden">
        <div v-if="dataLoading" class="p-12 text-center text-xs text-slate-400">
          Menjalankan kalkulasi Early Warning System...
        </div>

        <div v-else-if="filteredAnalyses.length === 0" class="p-12 text-center text-xs text-slate-400">
          Tidak ada data siswa yang memenuhi kriteria filter.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-100 text-slate-500 uppercase tracking-wider text-[10px]">
                <th class="py-3.5 px-4 font-bold text-center w-12">No</th>
                <th class="py-3.5 px-4 font-bold w-48">Nama Siswa</th>
                <th class="py-3.5 px-3 font-bold text-center w-28">Presensi</th>
                <th class="py-3.5 px-3 font-bold text-center w-24">Rerata Nilai</th>
                <th class="py-3.5 px-4 font-bold min-w-[220px]">Faktor Indikator Peringatan</th>
                <th class="py-3.5 px-4 font-bold text-center w-36">Tingkat Risiko</th>
                <th class="py-3.5 px-4 font-bold min-w-[240px]">Rekomendasi Tindak Lanjut</th>
                <th class="py-3.5 px-4 font-bold text-center w-28">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="(item, idx) in filteredAnalyses"
                :key="item.student_id"
                class="hover:bg-slate-50/70 transition"
                :class="{
                  'bg-rose-50/25': item.risk_level === 'high',
                  'bg-amber-50/15': item.risk_level === 'medium'
                }"
              >
                <td class="py-4 px-4 text-center font-bold text-slate-400">{{ idx + 1 }}</td>
                <td class="py-4 px-4">
                  <div class="font-bold text-slate-900">{{ item.name }}</div>
                  <div class="text-[10px] font-mono text-slate-400 mt-0.5">NISN: {{ item.nisn || '-' }}</div>
                </td>
                <td class="py-4 px-3 text-center">
                  <div class="font-extrabold" :class="item.attendance_rate < 85 ? 'text-rose-600' : 'text-slate-800'">
                    {{ item.attendance_rate }}%
                  </div>
                  <div class="text-[10px] text-slate-400">Alpa: {{ item.alpa_count }}</div>
                </td>
                <td class="py-4 px-3 text-center">
                  <div class="font-black" :class="item.average_score < 75 ? 'text-rose-600' : 'text-emerald-700'">
                    {{ item.average_score }}
                  </div>
                  <div v-if="item.below_kktp_subjects.length > 0" class="text-[9px] text-rose-500 font-bold">
                    {{ item.below_kktp_subjects.length }} mapel &lt; 75
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div v-if="item.risk_factors.length === 0" class="text-emerald-700 font-semibold text-[11px] flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Tidak ada indikasi masalah
                  </div>
                  <ul v-else class="space-y-1">
                    <li v-for="(rf, rIdx) in item.risk_factors" :key="rIdx" class="text-[11px] text-slate-700 flex items-start gap-1.5">
                      <span class="text-rose-500 font-bold">•</span>
                      <span>{{ rf }}</span>
                    </li>
                  </ul>
                </td>
                <td class="py-4 px-4 text-center">
                  <span
                    class="inline-block px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"
                    :class="{
                      'bg-rose-100 text-rose-800 border border-rose-200': item.risk_level === 'high',
                      'bg-amber-100 text-amber-800 border border-amber-200': item.risk_level === 'medium',
                      'bg-emerald-100 text-emerald-800 border border-emerald-200': item.risk_level === 'low'
                    }"
                  >
                    {{ item.risk_level === 'high' ? '🔴 Risiko Tinggi' : item.risk_level === 'medium' ? '🟡 Perlu Perhatian' : '🟢 Aman / Prima' }}
                  </span>
                </td>
                <td class="py-4 px-4 text-[11px] text-slate-600 leading-relaxed">
                  {{ item.recommendation }}
                </td>
                <td class="py-4 px-4 text-center">
                  <button
                    @click="openActionModal(item)"
                    class="px-3 py-1.5 rounded-xl font-bold text-xs transition"
                    :class="item.risk_level === 'high' ? 'bg-rose-600 text-white hover:bg-rose-700 shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                  >
                    Tindak Lanjut
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL TINDAK LANJUT WALI KELAS -->
    <div v-if="showActionModal && selectedStudent" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
      <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Rekomendasi Tindak Lanjut</span>
            <h3 class="text-lg font-extrabold text-slate-900 mt-0.5">{{ selectedStudent.name }}</h3>
          </div>
          <button @click="showActionModal = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div v-if="actionSuccessMsg" class="p-3 bg-emerald-50 text-emerald-800 rounded-2xl text-xs font-bold border border-emerald-200">
          {{ actionSuccessMsg }}
        </div>

        <form v-else @submit.prevent="handleSaveAction" class="space-y-3.5 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Bentuk Intervensi / Tindak Lanjut *</label>
            <select
              v-model="actionType"
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              required
            >
              <option value="parent_meeting">Pemanggilan Orang Tua / Wali Murid</option>
              <option value="bk_counseling">Konseling Bimbingan Konseling (BK)</option>
              <option value="remedial">Penugasan Remedial Akademik Terbimbing</option>
              <option value="peer_tutoring">Tutor Sebaya di Kelas</option>
              <option value="achievement_award">Apresiasi Prestasi / Duta Belajar</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Catatan Tambahan Wali Kelas</label>
            <textarea
              v-model="actionNote"
              rows="3"
              placeholder="Catatan perkembangan siswa, hasil wawancara, atau kesepakatan bimbingan..."
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showActionModal = false"
              class="px-4 py-2 border rounded-xl font-bold text-slate-600 hover:bg-slate-50"
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-sm transition"
            >
              Simpan Tindak Lanjut
            </button>
          </div>
        </form>
      </div>
    </div>
  </main>
</template>
