<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

definePageMeta({ layout: 'teacher', middleware: 'auth' })

const route = useRoute()
const courseId = computed(() => route.params.courseId)

const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'

interface ScoreComponent {
  id: number
  course_id: number
  type: 'formatif' | 'sumatif_materi' | 'sumatif_akhir'
  code: string
  title: string
  weight: number
}

interface StudentGradeRow {
  student_id: number
  nisn: string
  name: string
  grades: Record<number, { score: number; feedback: string | null }>
  avg_formatif: number
  avg_sumatif: number
  final_score: number
  is_passed: boolean
}

interface GradebookData {
  course: { id: number; subject_name: string; class_name: string }
  components: ScoreComponent[]
  kktp: number
  rows: StudentGradeRow[]
}

const loading = ref(true)
const saving = ref(false)
const gradebook = ref<GradebookData | null>(null)
const editedGrades = ref<Record<string, number>>({}) // Key: `${studentId}_${componentId}`
const saveFeedback = ref<{ message: string; type: 'success' | 'error' } | null>(null)

// Modal Tambah Komponen
const isComponentModalOpen = ref(false)
const compForm = ref({
  type: 'formatif' as 'formatif' | 'sumatif_materi' | 'sumatif_akhir',
  code: '',
  title: '',
  weight: 1.0,
})

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

const fetchGradebook = async () => {
  loading.value = true
  try {
    const res = await api<any>(`/courses/${courseId.value}/gradebook`)
    gradebook.value = res?.data || null
    // Inisialisasi editedGrades dari data server
    editedGrades.value = {}
    if (gradebook.value?.rows) {
      gradebook.value.rows.forEach(row => {
        gradebook.value!.components.forEach(comp => {
          const key = `${row.student_id}_${comp.id}`
          const val = row.grades[comp.id]?.score ?? 0
          editedGrades.value[key] = val
        })
      })
    }
  } catch (err: any) {
    console.error('Gagal memuat buku nilai:', err)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchGradebook()
})

// Hitung nilai akhir real-time di UI
const calculateLiveFinal = (row: StudentGradeRow) => {
  if (!gradebook.value) return 0
  let weightedSum = 0
  let totalWeight = 0

  gradebook.value.components.forEach(comp => {
    const key = `${row.student_id}_${comp.id}`
    const val = Number(editedGrades.value[key] ?? 0)
    const weight = Number(comp.weight)
    weightedSum += (val * weight)
    totalWeight += weight
  })

  return totalWeight > 0 ? Math.round((weightedSum / totalWeight) * 100) / 100 : 0
}

const getScoreValue = (studentId: number, compId: number) => {
  const key = `${studentId}_${compId}`
  return editedGrades.value[key] ?? 0
}

const setScoreValue = (studentId: number, compId: number, event: Event) => {
  const target = event.target as HTMLInputElement
  let val = parseFloat(target.value) || 0
  if (val < 0) val = 0
  if (val > 100) val = 100
  const key = `${studentId}_${compId}`
  editedGrades.value[key] = val
}

const saveAllGrades = async () => {
  if (!gradebook.value) return
  saving.value = true
  saveFeedback.value = null

  try {
    const gradesPayload: any[] = []
    gradebook.value.rows.forEach(row => {
      gradebook.value!.components.forEach(comp => {
        const key = `${row.student_id}_${comp.id}`
        gradesPayload.push({
          student_id: row.student_id,
          score_component_id: comp.id,
          score: editedGrades.value[key] ?? 0,
        })
      })
    })

    await api(`/courses/${courseId.value}/gradebook/batch-save`, {
      method: 'POST',
      body: { grades: gradesPayload },
    })

    saveFeedback.value = {
      message: 'Seluruh nilai berhasil disimpan ke dalam Buku Nilai!',
      type: 'success',
    }
    await fetchGradebook()
  } catch (err: any) {
    saveFeedback.value = {
      message: err?.data?.message || 'Gagal menyimpan nilai.',
      type: 'error',
    }
  } finally {
    saving.value = false
  }
}

const openAddComponentModal = () => {
  compForm.value = {
    type: 'formatif',
    code: `TP-${(gradebook.value?.components.filter(c => c.type === 'formatif').length || 0) + 1}`,
    title: '',
    weight: 1.0,
  }
  isComponentModalOpen.value = true
}

const saveComponent = async () => {
  if (!compForm.value.title || !compForm.value.code) return
  try {
    await api(`/courses/${courseId.value}/score-components`, {
      method: 'POST',
      body: compForm.value,
    })
    isComponentModalOpen.value = false
    await fetchGradebook()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal menambahkan komponen nilai')
  }
}

const deleteComponent = async (id: number) => {
  if (!confirm('Apakah Anda yakin ingin menghapus komponen nilai ini beserta seluruh nilai siswa di dalamnya?')) return
  try {
    await api(`/score-components/${id}`, { method: 'DELETE' })
    await fetchGradebook()
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal menghapus komponen nilai')
  }
}

const exportCsv = () => {
  if (!gradebook.value) return
  const headers = ['No', 'NISN', 'Nama Siswa']
  gradebook.value.components.forEach(c => headers.push(`${c.code} (${c.title})`))
  headers.push('Nilai Akhir', 'Status KKTP')

  const csvRows = [headers.join(',')]

  gradebook.value.rows.forEach((row, idx) => {
    const line = [idx + 1, `"${row.nisn}"`, `"${row.name}"`]
    gradebook.value!.components.forEach(c => {
      const val = editedGrades.value[`${row.student_id}_${c.id}`] ?? 0
      line.push(val)
    })
    const finalVal = calculateLiveFinal(row)
    line.push(finalVal, finalVal >= (gradebook.value?.kktp || 75) ? 'Tuntas' : 'Perlu Bimbingan')
    csvRows.push(line.join(','))
  })

  const blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `Buku_Nilai_${gradebook.value.course.class_name}_${gradebook.value.course.subject_name}.csv`
  a.click()
}
</script>

<template>
  <div class="space-y-6">
    <!-- Back & Navigation -->
    <div class="flex items-center gap-2">
      <NuxtLink
        to="/teacher/classes"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-600 hover:bg-slate-50 hover:text-emerald-700 transition"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Kembali ke Kelas Saya
      </NuxtLink>
      <span class="text-slate-300">/</span>
      <span class="text-xs font-semibold text-slate-500">Buku Nilai Kurikulum Merdeka</span>
    </div>

    <!-- Header Title & Quick Actions -->
    <div class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="px-2.5 py-0.5 rounded-md bg-emerald-50 border border-emerald-200/60 font-mono text-[10px] font-extrabold text-emerald-800">
            KKTP Standar: {{ gradebook?.kktp || 75 }}
          </span>
          <span class="text-xs font-bold text-emerald-700">
            {{ gradebook?.course?.class_name }}
          </span>
        </div>
        <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
          Buku Nilai: {{ gradebook?.course?.subject_name || 'Memuat Kelas...' }}
        </h1>
        <p class="text-xs text-slate-500 mt-1">
          Pengolahan nilai Formatif (Tujuan Pembelajaran) dan Sumatif (STS & SAS) sesuai panduan Kurikulum Merdeka.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <button
          @click="openAddComponentModal"
          class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 transition"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          + Komponen Nilai
        </button>

        <button
          @click="exportCsv"
          class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 transition"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          Ekspor CSV
        </button>

        <button
          @click="saveAllGrades"
          :disabled="saving"
          class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold shadow-md shadow-emerald-600/20 hover:bg-emerald-700 disabled:opacity-50 transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          {{ saving ? 'Menyimpan...' : 'Simpan Semua Nilai' }}
        </button>
      </div>
    </div>

    <!-- Alert Feedback -->
    <div
      v-if="saveFeedback"
      :class="[
        'p-3.5 rounded-2xl border text-xs font-semibold flex items-center justify-between',
        saveFeedback.type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-700'
      ]"
    >
      <span>{{ saveFeedback.message }}</span>
      <button @click="saveFeedback = null" class="font-bold opacity-60 hover:opacity-100">✕</button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="p-16 text-center text-xs text-slate-400 bg-white rounded-3xl border border-slate-100">
      <svg class="animate-spin h-6 w-6 text-emerald-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      Memuat buku nilai siswa...
    </div>

    <!-- Gradebook Matrix Table -->
    <div v-else-if="gradebook" class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <!-- Header Row 1: Kategori -->
            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-600">
              <th rowspan="2" class="px-4 py-3 border-r border-slate-200 w-12 text-center">No</th>
              <th rowspan="2" class="px-4 py-3 border-r border-slate-200 min-w-[200px] sticky left-0 bg-slate-50 z-10 shadow-xs">
                Nama Siswa & NISN
              </th>
              <th
                v-for="comp in gradebook.components"
                :key="comp.id"
                class="px-3 py-2 text-center border-r border-slate-200 min-w-[110px]"
              >
                <div class="flex items-center justify-between gap-1">
                  <span class="font-extrabold text-emerald-800">{{ comp.code }}</span>
                  <button
                    @click="deleteComponent(comp.id)"
                    class="text-slate-400 hover:text-rose-600 text-[10px]"
                    title="Hapus Kolom"
                  >
                    ✕
                  </button>
                </div>
                <span class="block text-[10px] font-normal text-slate-400 truncate" :title="comp.title">
                  {{ comp.title }}
                </span>
                <span class="block text-[9px] font-semibold text-slate-400 mt-0.5">
                  Bobot: {{ comp.weight }}x
                </span>
              </th>
              <th rowspan="2" class="px-4 py-3 border-r border-slate-200 text-center bg-emerald-50/70 text-emerald-900 min-w-[100px]">
                Nilai Akhir
              </th>
              <th rowspan="2" class="px-4 py-3 text-center min-w-[110px]">
                Status KKTP
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="(row, idx) in gradebook.rows"
              :key="row.student_id"
              class="hover:bg-slate-50/60 transition"
            >
              <td class="px-4 py-3 text-center font-bold text-slate-400 border-r border-slate-100">
                {{ idx + 1 }}
              </td>
              <td class="px-4 py-3 border-r border-slate-100 sticky left-0 bg-white z-10">
                <p class="font-bold text-slate-900">{{ row.name }}</p>
                <p class="text-[10px] text-slate-400 font-mono">{{ row.nisn }}</p>
              </td>

              <!-- Input Nilai per Komponen -->
              <td
                v-for="comp in gradebook.components"
                :key="comp.id"
                class="px-2 py-2 border-r border-slate-100 text-center"
              >
                <input
                  type="number"
                  min="0"
                  max="100"
                  step="0.5"
                  :value="getScoreValue(row.student_id, comp.id)"
                  @input="setScoreValue(row.student_id, comp.id, $event)"
                  class="w-16 px-2 py-1 text-center font-bold rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                  :class="[
                    getScoreValue(row.student_id, comp.id) < (gradebook.kktp || 75)
                      ? 'text-rose-600 bg-rose-50/30'
                      : 'text-slate-800'
                  ]"
                />
              </td>

              <!-- Nilai Akhir Terhitung Real-time -->
              <td class="px-4 py-3 text-center border-r border-slate-100 bg-emerald-50/30 font-extrabold text-sm text-emerald-900">
                {{ calculateLiveFinal(row) }}
              </td>

              <!-- Status KKTP Badge -->
              <td class="px-4 py-3 text-center">
                <span
                  v-if="calculateLiveFinal(row) >= (gradebook.kktp || 75)"
                  class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800"
                >
                  ✓ Tuntas
                </span>
                <span
                  v-else
                  class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800"
                >
                  Perlu Bimbingan
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Tambah Komponen Nilai -->
    <div
      v-if="isComponentModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in"
    >
      <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="font-extrabold text-slate-900 text-sm">Tambah Komponen Nilai Baru</h3>
          <button @click="isComponentModalOpen = false" class="text-slate-400 hover:text-slate-600">
            ✕
          </button>
        </div>

        <form @submit.prevent="saveComponent" class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Tipe Asesmen *</label>
            <select
              v-model="compForm.type"
              class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
            >
              <option value="formatif">Formatif (Tugas Harian / Kuis TP)</option>
              <option value="sumatif_materi">Sumatif Lingkup Materi (STS / Ulangan)</option>
              <option value="sumatif_akhir">Sumatif Akhir Semester (SAS / Rapor)</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Kode Kolom *</label>
              <input
                v-model="compForm.code"
                type="text"
                placeholder="Contoh: TP-3"
                required
                class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono"
              />
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Bobot Nilai *</label>
              <input
                v-model.number="compForm.weight"
                type="number"
                step="0.5"
                min="0.5"
                required
                class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Nama / Judul Asesmen *</label>
            <input
              v-model="compForm.title"
              type="text"
              placeholder="Contoh: Tugas Mandiri Polinomial"
              required
              class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="isComponentModalOpen = false"
              class="px-4 py-2 rounded-xl border border-slate-200 font-semibold text-slate-600 hover:bg-slate-50"
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700"
            >
              Tambahkan Kolom
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
