<script setup>
import { ref, onMounted } from 'vue'

definePageMeta({
  layout: 'admin',
  middleware: ['auth']
})

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie('auth_token')

const loading = ref(true)
const statsData = ref({
  total_students: 0,
  total_teachers: 0,
  total_subjects: 0,
  total_classes: 0,
  total_courses: 0,
  today_schedules: 0,
  recent_activities: []
})

const statsCards = ref([])
const recentActivities = ref([])

const fetchDashboardStats = async () => {
  loading.value = true
  try {
    const res = await $fetch(`${apiBase}/dashboard/stats`, {
      headers: {
        Authorization: `Bearer ${token.value}`,
        Accept: 'application/json'
      }
    })

    if (res?.data) {
      statsData.value = res.data
      recentActivities.value = res.data.recent_activities || []

      statsCards.value = [
        {
          title: 'Total Siswa',
          value: res.data.total_students?.toLocaleString('id-ID') || '0',
          subtitle: 'Siswa aktif terdaftar',
          icon: 'M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z',
          color: 'bg-brand-700'
        },
        {
          title: 'Total Guru / Pengajar',
          value: res.data.total_teachers?.toLocaleString('id-ID') || '0',
          subtitle: 'Tenaga pendidik',
          icon: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
          color: 'bg-brand-800'
        },
        {
          title: 'Mata Pelajaran Aktif',
          value: res.data.total_subjects?.toLocaleString('id-ID') || '0',
          subtitle: `${res.data.total_courses || 0} plotting kelas`,
          icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
          color: 'bg-brand-600'
        },
        {
          title: 'Jadwal Hari Ini',
          value: res.data.today_schedules?.toLocaleString('id-ID') || '0',
          subtitle: 'Sesi terjadwal',
          icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
          color: 'bg-amber-600'
        }
      ]
    }
  } catch (err) {
    console.error('Gagal mengambil statistik dashboard:', err)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchDashboardStats()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Dashboard -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Dashboard Utama</h1>
        <p class="text-xs text-slate-500 mt-1">Ringkasan operasional real-time MAN 11 Jakarta.</p>
      </div>
      <div class="flex items-center space-x-2">
        <button 
          @click="fetchDashboardStats" 
          :disabled="loading"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-1.5"
        >
          <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': loading }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Segarkan Data
        </button>
        <NuxtLink 
          to="/admin/users" 
          class="px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white rounded-xl text-xs font-semibold shadow-xs transition"
        >
          + Tambah Akun
        </NuxtLink>
      </div>
    </div>

    <!-- Grid Cards Statistik Utama -->
    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 animate-pulse">
      <div v-for="i in 4" :key="i" class="h-28 bg-white rounded-2xl border border-slate-100 p-5"></div>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
      <div 
        v-for="(item, index) in statsCards" 
        :key="index"
        class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition flex items-center justify-between"
      >
        <div class="space-y-1">
          <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">{{ item.title }}</p>
          <h3 class="text-2xl font-bold text-slate-800">{{ item.value }}</h3>
          <p class="text-[11px] font-semibold text-emerald-600 flex items-center gap-1">
            <span>{{ item.subtitle }}</span>
          </p>
        </div>
        <div :class="[item.color, 'p-3.5 rounded-2xl text-white shadow-md shadow-emerald-900/10']">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
          </svg>
        </div>
      </div>
    </div>

    <!-- Layout 2 Kolom: Ringkasan & Aktivitas Terbaru -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      
      <!-- Card Statistik Kelas & Aktivitas Terbaru (2/3 width) -->
      <div class="lg:col-span-2 bg-white rounded-2xl p-6 border border-slate-100 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-bold text-slate-800">Aktivitas Akademik Terkini</h2>
          <span class="text-[11px] font-medium text-slate-400">Sinkronisasi Realtime</span>
        </div>

        <div v-if="loading" class="py-12 text-center text-xs text-slate-400">
          Memuat aktivitas terbaru...
        </div>

        <div v-else-if="recentActivities.length === 0" class="py-12 text-center text-xs text-slate-400">
          Belum ada aktivitas akademik yang tercatat baru-baru ini.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="border-b border-slate-100 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                <th class="py-3 px-2">Pengguna</th>
                <th class="py-3 px-2">Aktivitas</th>
                <th class="py-3 px-2">Waktu</th>
                <th class="py-3 px-2 text-right">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 text-xs text-slate-600">
              <tr v-for="act in recentActivities" :key="act.id" class="hover:bg-slate-50/50 transition">
                <td class="py-3 px-2 font-medium text-slate-800">
                  <div>{{ act.user }}</div>
                  <div class="text-[10px] text-slate-400 font-normal">{{ act.role }}</div>
                </td>
                <td class="py-3 px-2">{{ act.action }}</td>
                <td class="py-3 px-2 text-slate-400">{{ act.time }}</td>
                <td class="py-3 px-2 text-right">
                  <span 
                    class="px-2.5 py-1 text-[10px] font-semibold rounded-full"
                    :class="act.status === 'Dinilai' || act.status === 'Terverifikasi' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'"
                  >
                    {{ act.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Card Akses Cepat / Menu Quick Access (1/3 width) -->
      <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm space-y-4">
        <h2 class="text-sm font-bold text-slate-800">Akses Cepat Admin</h2>
        <div class="grid grid-cols-2 gap-3">
          <NuxtLink 
            to="/admin/students" 
            class="flex flex-col items-center justify-center p-4 bg-emerald-50/40 hover:bg-emerald-50 rounded-xl border border-emerald-100/50 text-emerald-800 transition group text-center"
          >
            <div class="p-2.5 bg-emerald-500 text-white rounded-lg mb-2 group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
              </svg>
            </div>
            <span class="text-xs font-semibold">Data Siswa</span>
          </NuxtLink>

          <NuxtLink 
            to="/admin/schedules" 
            class="flex flex-col items-center justify-center p-4 bg-teal-50/40 hover:bg-teal-50 rounded-xl border border-teal-100/50 text-teal-800 transition group text-center"
          >
            <div class="p-2.5 bg-teal-500 text-white rounded-lg mb-2 group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <span class="text-xs font-semibold">Jadwal</span>
          </NuxtLink>

          <NuxtLink 
            to="/admin/scores" 
            class="flex flex-col items-center justify-center p-4 bg-cyan-50/40 hover:bg-cyan-50 rounded-xl border border-cyan-100/50 text-cyan-800 transition group text-center"
          >
            <div class="p-2.5 bg-cyan-500 text-white rounded-lg mb-2 group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
              </svg>
            </div>
            <span class="text-xs font-semibold">Laporan Nilai</span>
          </NuxtLink>

          <NuxtLink 
            to="/admin/majors" 
            class="flex flex-col items-center justify-center p-4 bg-slate-50 hover:bg-slate-100 rounded-xl border border-slate-200/60 text-slate-700 transition group text-center"
          >
            <div class="p-2.5 bg-slate-700 text-white rounded-lg mb-2 group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
              </svg>
            </div>
            <span class="text-xs font-semibold">Master Jurusan</span>
          </NuxtLink>
        </div>
      </div>

    </div>
  </div>
</template>