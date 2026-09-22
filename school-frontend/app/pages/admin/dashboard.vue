<script setup>
import { ref } from 'vue'

definePageMeta({
  layout: 'admin',
  middleware: ['auth']
})

// Data Dummy Statistik
const stats = ref([
  {
    title: 'Total Siswa',
    value: '1,240',
    change: '+12%',
    isPositive: true,
    icon: 'M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z',
    color: 'bg-emerald-500'
  },
  {
    title: 'Total Guru / Pengajar',
    value: '86',
    change: '+3%',
    isPositive: true,
    icon: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    color: 'bg-teal-500'
  },
  {
    title: 'Mata Pelajaran Active',
    value: '42',
    change: '0%',
    isPositive: true,
    icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
    color: 'bg-cyan-500'
  },
  {
    title: 'Jadwal Hari Ini',
    value: '18',
    change: '-2',
    isPositive: false,
    icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    color: 'bg-amber-500'
  }
])

// Data Dummy Aktivitas Terbaru
const recentActivities = ref([
  { id: 1, user: 'Ahmad Fauzi', role: 'Student', action: 'Mengumpulkan Tugas Matematika', time: '10 menit yang lalu', status: 'Selesai' },
  { id: 2, user: 'Dr. Siti Nurhaliza', role: 'Lecturer', action: 'Input Nilai UTS Fisika XI IPA 1', time: '25 menit yang lalu', status: 'Diproses' },
  { id: 3, user: 'Budi Santoso', role: 'Admin', action: 'Menambahkan 3 User Baru', time: '1 jam yang lalu', status: 'Selesai' },
  { id: 4, user: 'Rina Wijaya', role: 'Student', action: 'Memperbarui Profil', time: '3 jam yang lalu', status: 'Selesai' }
])
</script>

<template>
  <div class="space-y-6">
    <!-- Header Dashboard -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Dashboard Utama</h1>
        <p class="text-xs text-slate-500 mt-1">Selamat datang kembali! Ringkasan sistem MAN 11 Jakarta.</p>
      </div>
      <div class="flex items-center space-x-2">
        <button class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
          + Tambah Data
        </button>
      </div>
    </div>

    <!-- Grid Cards Statistik Utama -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
      <div 
        v-for="(item, index) in stats" 
        :key="index"
        class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition flex items-center justify-between"
      >
        <div class="space-y-1">
          <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">{{ item.title }}</p>
          <h3 class="text-2xl font-bold text-slate-800">{{ item.value }}</h3>
          <p class="text-[11px] font-semibold flex items-center gap-1" :class="item.isPositive ? 'text-emerald-600' : 'text-rose-500'">
            <span>{{ item.change }}</span>
            <span class="text-slate-400 font-normal">dari bulan lalu</span>
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
      
      <!-- Card Statistik Kelas (2/3 width) -->
      <div class="lg:col-span-2 bg-white rounded-2xl p-6 border border-slate-100 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-bold text-slate-800">Aktivitas & Log Sistem</h2>
          <NuxtLink to="/admin/logs" class="text-xs font-semibold text-emerald-600 hover:underline">Lihat Semua</NuxtLink>
        </div>

        <div class="overflow-x-auto">
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
                    :class="act.status === 'Selesai' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'"
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
        <h2 class="text-sm font-bold text-slate-800">Akses Cepat</h2>
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
            <span class="text-xs font-semibold">Input Nilai</span>
          </NuxtLink>

          <NuxtLink 
            to="/admin/master/major" 
            class="flex flex-col items-center justify-center p-4 bg-slate-50 hover:bg-slate-100 rounded-xl border border-slate-200/60 text-slate-700 transition group text-center"
          >
            <div class="p-2.5 bg-slate-700 text-white rounded-lg mb-2 group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
              </svg>
            </div>
            <span class="text-xs font-semibold">Master Data</span>
          </NuxtLink>
        </div>
      </div>

    </div>
  </div>
</template>