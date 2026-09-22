<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'

definePageMeta({
  layout: 'admin'
})

const route = useRoute()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie('auth_token')

const rombel = ref(null)
const availableStudents = ref([])
const selectedStudentIds = ref([])

const loading = ref(true)
const modalAddStudent = ref(false)
const adding = ref(false)
const searchAvailable = ref('')

const fetchRombelDetail = async () => {
  loading.value = true
  try {
    const res = await $fetch(`${apiBase}/rombel/${route.params.id}`, {
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })
    rombel.value = res
  } catch (err) {
    console.error('Fetch Rombel detail error:', err)
  } finally {
    loading.value = false
  }
}

const fetchAvailableStudents = async () => {
  try {
    const res = await $fetch(`${apiBase}/students?paginate=false`, {
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })
    const currentStudentIds = new Set((rombel.value?.students || []).map(s => s.id))
    availableStudents.value = (res.data || res || []).filter(s => !currentStudentIds.has(s.id))
  } catch (err) {
    console.error('Fetch available students error:', err)
  }
}

const openAddModal = async () => {
  selectedStudentIds.value = []
  await fetchAvailableStudents()
  modalAddStudent.value = true
}

const handleAddStudents = async () => {
  if (selectedStudentIds.value.length === 0) {
    alert('Pilih setidaknya satu siswa untuk ditambahkan!')
    return
  }

  adding.value = true
  try {
    await $fetch(`${apiBase}/rombel/${route.params.id}/students`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token.value}`
      },
      body: {
        student_ids: selectedStudentIds.value
      }
    })

    modalAddStudent.value = false
    await fetchRombelDetail()
  } catch (err) {
    alert(err.data?.message || 'Gagal menambahkan siswa ke Rombel')
  } finally {
    adding.value = false
  }
}

const handleRemoveStudent = async (studentId, studentName) => {
  if (!confirm(`Keluarkan ${studentName} dari rombel ini?`)) return

  try {
    await $fetch(`${apiBase}/rombel/${route.params.id}/students/${studentId}`, {
      method: 'DELETE',
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })
    await fetchRombelDetail()
  } catch (err) {
    alert(err.data?.message || 'Gagal mengeluarkan siswa')
  }
}

onMounted(() => {
  fetchRombelDetail()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Breadcrumb & Back -->
    <div class="flex items-center gap-2 text-sm text-gray-500">
      <NuxtLink to="/admin/rombel" class="hover:text-emerald-600 transition flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Kembali ke Daftar Rombel
      </NuxtLink>
    </div>

    <div v-if="loading" class="bg-white rounded-2xl p-8 text-center text-gray-400">
      Memuat detail Rombel...
    </div>

    <div v-else-if="!rombel" class="bg-white rounded-2xl p-8 text-center text-rose-500">
      Data Rombel tidak ditemukan.
    </div>

    <template v-else>
      <!-- Header Detail Card -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900">{{ rombel.class_name?.name }}</h1>
            <span class="px-3 py-1 bg-emerald-100 text-emerald-700 text-xs font-semibold rounded-full">
              Ruang {{ rombel.room_number || '-' }}
            </span>
          </div>
          <p class="text-sm text-gray-500 mt-1">
            Tahun Ajaran {{ rombel.semester?.academic_year }} ({{ rombel.semester?.semester_type === 'odd' ? 'Ganjil' : 'Genap' }})
            • Wali Kelas: <span class="font-semibold text-gray-700">{{ rombel.homeroom_teacher?.name || 'Belum diatur' }}</span>
          </p>
        </div>

        <button 
          @click="openAddModal"
          class="inline-flex items-center justify-center px-4 py-2.5 bg-emerald-600 text-white font-medium text-sm rounded-xl hover:bg-emerald-700 transition shadow-sm gap-2"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
          </svg>
          Tambah Siswa ke Rombel
        </button>
      </div>

      <!-- Students Table Card -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex justify-between items-center">
          <h3 class="font-bold text-gray-900">
            Daftar Siswa ({{ rombel.students?.length || 0 }} / {{ rombel.capacity }})
          </h3>
          <span class="text-xs text-gray-400">Tingkat {{ rombel.class_name?.grade_level }}</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                <th class="py-3.5 px-6">NISN / NIS</th>
                <th class="py-3.5 px-6">Nama Siswa</th>
                <th class="py-3.5 px-6">L/P</th>
                <th class="py-3.5 px-6">Email</th>
                <th class="py-3.5 px-6 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
              <tr v-if="!rombel.students || rombel.students.length === 0">
                <td colspan="5" class="py-8 text-center text-gray-400">Belum ada siswa yang dimasukkan ke rombel ini.</td>
              </tr>
              <tr v-for="std in rombel.students" :key="std.id" class="hover:bg-gray-50/50 transition">
                <td class="py-4 px-6 font-mono text-gray-500">
                  {{ std.nisn }} / {{ std.nis || '-' }}
                </td>
                <td class="py-4 px-6 font-bold text-gray-900">{{ std.name }}</td>
                <td class="py-4 px-6">
                  <span class="px-2 py-0.5 text-xs rounded-full" :class="std.gender === 'male' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700'">
                    {{ std.gender === 'male' ? 'L' : 'P' }}
                  </span>
                </td>
                <td class="py-4 px-6 text-gray-500">{{ std.email }}</td>
                <td class="py-4 px-6 text-right">
                  <button 
                    @click="handleRemoveStudent(std.id, std.name)"
                    class="px-3 py-1.5 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-lg text-xs font-semibold transition"
                  >
                    Keluarkan
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- Modal Enrolment Siswa -->
    <div v-if="modalAddStudent" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-gray-100 space-y-4 max-h-[85vh] flex flex-col">
        <div class="flex justify-between items-center border-b border-gray-100 pb-3">
          <h3 class="text-lg font-bold text-gray-900">
            Pilih Siswa untuk Rombel {{ rombel.class_name?.name }}
          </h3>
          <button @click="modalAddStudent = false" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="relative">
          <input 
            v-model="searchAvailable"
            type="text" 
            placeholder="Cari siswa berdasarkan nama/NISN..."
            class="w-full pl-9 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
          >
          <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <div class="flex-1 overflow-y-auto divide-y divide-gray-100 border border-gray-100 rounded-xl max-h-60 p-2">
          <div v-if="availableStudents.length === 0" class="p-4 text-center text-xs text-gray-400">
            Tidak ada siswa tersedia untuk ditambahkan.
          </div>
          <label 
            v-for="std in availableStudents.filter(s => s.name.toLowerCase().includes(searchAvailable.toLowerCase()) || s.nisn.includes(searchAvailable))" 
            :key="std.id"
            class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer text-sm"
          >
            <input 
              type="checkbox" 
              :value="std.id" 
              v-model="selectedStudentIds"
              class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500"
            >
            <div>
              <div class="font-semibold text-gray-900">{{ std.name }}</div>
              <div class="text-xs text-gray-400">NISN: {{ std.nisn }}</div>
            </div>
          </label>
        </div>

        <div class="flex justify-between items-center pt-3 border-t border-gray-100 text-xs">
          <span class="text-gray-500 font-medium">Terpilih: {{ selectedStudentIds.length }} Siswa</span>
          <div class="flex gap-3">
            <button 
              type="button" 
              @click="modalAddStudent = false"
              class="px-4 py-2 border border-gray-200 text-gray-600 rounded-xl font-semibold hover:bg-gray-50 transition"
            >
              Batal
            </button>
            <button 
              @click="handleAddStudents"
              :disabled="adding || selectedStudentIds.length === 0"
              class="px-4 py-2 bg-emerald-600 text-white rounded-xl font-semibold hover:bg-emerald-700 transition disabled:opacity-50"
            >
              {{ adding ? 'Menambahkan...' : 'Tambahkan Ke Rombel' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
