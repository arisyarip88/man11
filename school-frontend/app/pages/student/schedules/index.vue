<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

definePageMeta({ layout: 'student', middleware: 'auth' })

const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'

interface ScheduleItem {
  id: number
  day: string
  start_time: string
  end_time: string
  room_number?: string
  course?: {
    id: number
    subject?: { name: string; code: string }
    class?: { className?: { name: string } }
    teacher?: { name: string }
  }
}

const loading = ref(true)
const schedules = ref<ScheduleItem[]>([])
const selectedDay = ref('all') // 'all', 'monday', 'tuesday', ...

const dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']
const dayLabels: Record<string, string> = {
  monday: 'Senin',
  tuesday: 'Selasa',
  wednesday: 'Rabu',
  thursday: 'Kamis',
  friday: 'Jumat',
  saturday: 'Sabtu'
}

const todayIndex = new Date().getDay()
const dayIndexToName: Record<number, string> = {
  1: 'monday',
  2: 'tuesday',
  3: 'wednesday',
  4: 'thursday',
  5: 'friday',
  6: 'saturday'
}
const todayDayName = dayIndexToName[todayIndex] || 'monday'

const api = async <T>(path: string) => {
  return await $fetch<T>(`${apiBase}${path}`, {
    headers: {
      Authorization: `Bearer ${token.value}`,
      Accept: 'application/json',
    },
  })
}

const fetchSchedules = async () => {
  loading.value = true
  try {
    const res: any = await api('/schedules?my_schedules=true&all=true')
    schedules.value = res.data || (Array.isArray(res) ? res : [])
  } catch (err) {
    console.error('Gagal mengambil jadwal siswa:', err)
  } finally {
    loading.value = false
  }
}

const filteredSchedules = computed(() => {
  if (selectedDay.value === 'all') return schedules.value
  return schedules.value.filter(s => s.day?.toLowerCase() === selectedDay.value.toLowerCase())
})

const groupedByDay = computed(() => {
  const groups: Record<string, ScheduleItem[]> = {}
  dayOrder.forEach(d => {
    const list = schedules.value.filter(s => s.day?.toLowerCase() === d)
    if (list.length > 0) {
      groups[d] = list.sort((a, b) => a.start_time.localeCompare(b.start_time))
    }
  })
  return groups
})

onMounted(() => {
  fetchSchedules()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Top Back Link -->
    <NuxtLink to="/student/dashboard" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-emerald-700 transition">
      <span>←</span>
      <span>Kembali ke Dashboard Belajar Siswa</span>
    </NuxtLink>

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
            Tahun Ajaran 2025/2026
          </span>
          <span class="text-xs text-slate-400">Jadwal Tatap Muka Kelas</span>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">Jadwal Pelajaran Siswa</h1>
        <p class="text-xs text-slate-500 mt-1">
          Informasi jam belajar, mata pelajaran, ruang kelas, dan guru pengampu Anda.
        </p>
      </div>

      <!-- Quick Day Filter -->
      <div class="flex items-center gap-1.5 bg-white p-1 rounded-2xl border border-slate-100 shadow-xs overflow-x-auto text-xs font-bold">
        <button
          type="button"
          @click="selectedDay = 'all'"
          class="px-3 py-1.5 rounded-xl transition whitespace-nowrap"
          :class="selectedDay === 'all' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-500 hover:text-slate-800'"
        >
          Semua Hari
        </button>
        <button
          v-for="d in dayOrder"
          :key="d"
          type="button"
          @click="selectedDay = d"
          class="px-3 py-1.5 rounded-xl transition whitespace-nowrap flex items-center gap-1"
          :class="selectedDay === d ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-500 hover:text-slate-800'"
        >
          <span>{{ dayLabels[d] }}</span>
          <span v-if="d === todayDayName" class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div v-for="i in 4" :key="i" class="h-32 bg-white rounded-2xl border border-slate-100 p-5 animate-pulse"></div>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredSchedules.length === 0" class="bg-white rounded-3xl border border-slate-100 p-12 text-center space-y-3">
      <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      </div>
      <h3 class="text-sm font-bold text-slate-800">Tidak ada jadwal pada hari ini</h3>
      <p class="text-xs text-slate-500 max-w-sm mx-auto">
        Jadwal pelajaran untuk hari yang dipilih belum diterbitkan oleh bagian kurikulum.
      </p>
    </div>

    <!-- Grouped View (All Days) -->
    <div v-else-if="selectedDay === 'all'" class="space-y-6">
      <div v-for="(items, d) in groupedByDay" :key="d" class="space-y-3">
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
          <h2 class="text-base font-extrabold text-slate-800">{{ dayLabels[d] }}</h2>
          <span v-if="d === todayDayName" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
            Hari Ini
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="s in items"
            :key="s.id"
            class="bg-white rounded-2xl border border-slate-100 p-5 shadow-xs hover:shadow-md hover:border-emerald-200 transition space-y-3"
          >
            <div class="flex items-center justify-between">
              <span class="font-mono text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg">
                {{ s.start_time }} – {{ s.end_time }}
              </span>
              <span class="text-[11px] font-medium text-slate-400">
                {{ s.room_number || 'Ruang Kelas' }}
              </span>
            </div>

            <div>
              <h3 class="text-sm font-bold text-slate-900">
                {{ s.course?.subject?.name || 'Mata Pelajaran' }}
              </h3>
              <p class="text-xs text-slate-500 mt-0.5">
                Pengampu: {{ s.course?.teacher?.name || 'Guru Pengampu' }}
              </p>
            </div>

            <div class="pt-2 border-t border-slate-50 flex items-center justify-between text-[11px]">
              <span class="text-slate-400">Kelas: {{ s.course?.class?.className?.name || 'Rombel' }}</span>
              <NuxtLink
                :to="`/elearning/courses/${s.course?.id}`"
                class="font-semibold text-emerald-600 hover:text-emerald-700 transition"
              >
                Buka E-Learning →
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Single Day View -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="s in filteredSchedules"
        :key="s.id"
        class="bg-white rounded-2xl border border-slate-100 p-5 shadow-xs hover:shadow-md hover:border-emerald-200 transition space-y-3"
      >
        <div class="flex items-center justify-between">
          <span class="font-mono text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg">
            {{ s.start_time }} – {{ s.end_time }}
          </span>
          <span class="text-[11px] font-medium text-slate-400">
            {{ s.room_number || 'Ruang Kelas' }}
          </span>
        </div>

        <div>
          <h3 class="text-sm font-bold text-slate-900">
            {{ s.course?.subject?.name || 'Mata Pelajaran' }}
          </h3>
          <p class="text-xs text-slate-500 mt-0.5">
            Pengampu: {{ s.course?.teacher?.name || 'Guru Pengampu' }}
          </p>
        </div>

        <div class="pt-2 border-t border-slate-50 flex items-center justify-between text-[11px]">
          <span class="text-slate-400">Kelas: {{ s.course?.class?.className?.name || 'Rombel' }}</span>
          <NuxtLink
            :to="`/elearning/courses/${s.course?.id}`"
            class="font-semibold text-emerald-600 hover:text-emerald-700 transition"
          >
            Buka E-Learning →
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>
