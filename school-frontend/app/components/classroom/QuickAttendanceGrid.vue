<script setup lang="ts">
import { computed, ref, watch } from 'vue'

const props = defineProps<{
  meetId: number | string
  meetTitle?: string
  initialAttendances?: any[]
  students?: any[]
}>()

const emit = defineEmits<{
  (e: 'saved', data: any): void
}>()

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie<string | null>('auth_token')

const saving = ref(false)
const searchQuery = ref('')
const successNotice = ref('')

interface RowItem {
  student_id: number
  nisn: string
  name: string
  status: 'H' | 'S' | 'I' | 'A'
  notes: string
}

const attendanceMap = ref<Record<number, RowItem>>({})

const initializeData = () => {
  const map: Record<number, RowItem> = {}

  // 1. Populate from students list or initialAttendances
  const sourceStudents = (props.students && props.students.length > 0)
    ? props.students
    : (props.initialAttendances || []).map((a: any) => a.student || { id: a.student_id, name: a.student_name, nisn: a.nisn })

  sourceStudents.forEach((st: any) => {
    if (!st || !st.id) return
    map[st.id] = {
      student_id: st.id,
      nisn: st.nisn || st.username || '-',
      name: st.name || 'Siswa',
      status: 'H', // default Hadir
      notes: ''
    }
  })

  // 2. Override with existing saved attendances if any
  if (props.initialAttendances && props.initialAttendances.length > 0) {
    props.initialAttendances.forEach((att: any) => {
      if (att && att.student_id && map[att.student_id]) {
        map[att.student_id].status = att.status || 'H'
        map[att.student_id].notes = att.notes || ''
      }
    })
  }

  attendanceMap.value = map
}

watch(() => [props.meetId, props.initialAttendances, props.students], () => {
  initializeData()
}, { immediate: true })

const rowsList = computed(() => {
  const all = Object.values(attendanceMap.value)
  if (!searchQuery.value.trim()) return all
  const q = searchQuery.value.toLowerCase()
  return all.filter(r => r.name.toLowerCase().includes(q) || r.nisn.toLowerCase().includes(q))
})

const stats = computed(() => {
  const all = Object.values(attendanceMap.value)
  const total = all.length
  let H = 0, S = 0, I = 0, A = 0
  all.forEach(r => {
    if (r.status === 'H') H++
    else if (r.status === 'S') S++
    else if (r.status === 'I') I++
    else if (r.status === 'A') A++
  })
  const rate = total > 0 ? Math.round((H / total) * 100) : 0
  return { total, H, S, I, A, rate }
})

const markAll = (status: 'H' | 'S' | 'I' | 'A') => {
  Object.keys(attendanceMap.value).forEach((idStr) => {
    const id = Number(idStr)
    if (attendanceMap.value[id]) {
      attendanceMap.value[id].status = status
    }
  })
}

const setStatus = (studentId: number, status: 'H' | 'S' | 'I' | 'A') => {
  if (attendanceMap.value[studentId]) {
    attendanceMap.value[studentId].status = status
  }
}

const saveAttendances = async () => {
  if (!props.meetId) return
  saving.value = true
  successNotice.value = ''

  try {
    const payload = {
      attendances: Object.values(attendanceMap.value).map(r => ({
        student_id: r.student_id,
        status: r.status,
        notes: r.notes || null
      }))
    }

    const res: any = await $fetch(`${apiBase}/meets/${props.meetId}/attendances/batch`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token.value}`,
        Accept: 'application/json'
      },
      body: payload
    })

    successNotice.value = res?.message || 'Presensi kelas berhasil disimpan!'
    emit('saved', res)
    setTimeout(() => {
      successNotice.value = ''
    }, 4000)
  } catch (err: any) {
    alert(err?.data?.message || err?.message || 'Gagal menyimpan presensi.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <!-- Header Control & Stats Bar -->
    <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-bold text-slate-700">Tingkat Kehadiran:</span>
        <span class="px-2.5 py-1 rounded-xl text-xs font-black bg-emerald-600 text-white shadow-xs">
          {{ stats.rate }}% Hadir
        </span>
        <div class="flex items-center gap-1.5 ml-2 text-xs font-semibold">
          <span class="px-2 py-0.5 rounded-lg bg-emerald-100 text-emerald-800">H: {{ stats.H }}</span>
          <span class="px-2 py-0.5 rounded-lg bg-blue-100 text-blue-800">S: {{ stats.S }}</span>
          <span class="px-2 py-0.5 rounded-lg bg-amber-100 text-amber-800">I: {{ stats.I }}</span>
          <span class="px-2 py-0.5 rounded-lg bg-rose-100 text-rose-800">A: {{ stats.A }}</span>
          <span class="text-slate-400">/ {{ stats.total }} Siswa</span>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button
          type="button"
          class="px-3 py-1.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold transition flex items-center gap-1.5"
          @click="markAll('H')"
        >
          <span>✓</span>
          <span>Semua Hadir</span>
        </button>
        <button
          type="button"
          :disabled="saving"
          class="px-4 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition flex items-center gap-1.5 disabled:opacity-50"
          @click="saveAttendances"
        >
          <span v-if="saving" class="animate-spin">⌛</span>
          <span v-else>💾</span>
          <span>{{ saving ? 'Menyimpan...' : 'Simpan Presensi' }}</span>
        </button>
      </div>
    </div>

    <!-- Alert / Toast -->
    <div
      v-if="successNotice"
      class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs font-bold text-emerald-800 flex items-center justify-between transition"
    >
      <div class="flex items-center gap-2">
        <span>✅</span>
        <span>{{ successNotice }}</span>
      </div>
      <button type="button" class="text-emerald-700 hover:text-emerald-900" @click="successNotice = ''">✕</button>
    </div>

    <!-- Search Input -->
    <div class="relative">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Cari siswa berdasarkan nama atau NISN..."
        class="w-full px-3.5 py-2 pl-9 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
      >
      <span class="absolute left-3 top-2.5 text-xs text-slate-400">🔍</span>
    </div>

    <!-- Students Grid / Table -->
    <div class="border border-slate-200 rounded-2xl bg-white overflow-hidden shadow-xs">
      <div v-if="rowsList.length === 0" class="p-8 text-center text-xs text-slate-400">
        Tidak ada data siswa ditemukan.
      </div>
      <div v-else class="divide-y divide-slate-100 max-h-[460px] overflow-y-auto">
        <div
          v-for="(row, idx) in rowsList"
          :key="row.student_id"
          class="p-3 sm:px-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 hover:bg-slate-50/80 transition"
        >
          <!-- Student Info -->
          <div class="flex items-center gap-3 min-w-0">
            <span class="w-6 text-[11px] font-bold text-slate-400 text-center">{{ idx + 1 }}</span>
            <div class="min-w-0">
              <p class="text-xs font-bold text-slate-900 truncate">{{ row.name }}</p>
              <p class="text-[11px] text-slate-400 font-mono">NISN: {{ row.nisn }}</p>
            </div>
          </div>

          <!-- Action Buttons: H S I A -->
          <div class="flex items-center gap-1.5 self-end sm:self-center">
            <button
              type="button"
              class="w-8 h-8 rounded-xl text-xs font-black transition flex items-center justify-center border"
              :class="row.status === 'H'
                ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs ring-2 ring-emerald-500/20'
                : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-emerald-50 hover:text-emerald-700'"
              title="Hadir"
              @click="setStatus(row.student_id, 'H')"
            >
              H
            </button>
            <button
              type="button"
              class="w-8 h-8 rounded-xl text-xs font-black transition flex items-center justify-center border"
              :class="row.status === 'S'
                ? 'bg-blue-600 text-white border-blue-600 shadow-xs ring-2 ring-blue-500/20'
                : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-blue-50 hover:text-blue-700'"
              title="Sakit"
              @click="setStatus(row.student_id, 'S')"
            >
              S
            </button>
            <button
              type="button"
              class="w-8 h-8 rounded-xl text-xs font-black transition flex items-center justify-center border"
              :class="row.status === 'I'
                ? 'bg-amber-500 text-white border-amber-500 shadow-xs ring-2 ring-amber-500/20'
                : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-amber-50 hover:text-amber-700'"
              title="Izin"
              @click="setStatus(row.student_id, 'I')"
            >
              I
            </button>
            <button
              type="button"
              class="w-8 h-8 rounded-xl text-xs font-black transition flex items-center justify-center border"
              :class="row.status === 'A'
                ? 'bg-rose-600 text-white border-rose-600 shadow-xs ring-2 ring-rose-500/20'
                : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-rose-50 hover:text-rose-700'"
              title="Alpa / Tanpa Keterangan"
              @click="setStatus(row.student_id, 'A')"
            >
              A
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
