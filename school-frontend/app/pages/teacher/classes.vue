<script setup lang="ts">
import { onMounted, ref } from 'vue'

definePageMeta({ layout: 'teacher', middleware: 'auth' })

const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const loading = ref(true)
const courses = ref<any[]>([])

const subjectName = (course: any) => course?.subject?.name || 'Mata pelajaran'
const className = (course: any) => course?.class?.class_name?.name || course?.class?.className?.name || 'Kelas'

onMounted(async () => {
  try {
    const response: any = await $fetch(`${apiBase}/courses?my_courses=true&all=true`, {
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' }
    })
    courses.value = response?.data || response || []
  } catch (error) {
    console.error('Gagal memuat kelas pengampuan:', error)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-6xl">
      <NuxtLink to="/teacher/dashboard" class="text-xs font-semibold text-indigo-600 hover:underline">← Kembali ke Dashboard Guru</NuxtLink>
      <div class="mt-4 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><h1 class="text-2xl font-extrabold text-slate-900">Kelas Saya</h1><p class="mt-1 text-sm text-slate-500">Pilih kelas untuk membuat pertemuan, unggah materi, membuat tugas, dan memberi nilai.</p></div></div>
      <div v-if="loading" class="mt-6 rounded-3xl bg-white p-8 text-center text-sm text-slate-400 shadow-sm">Memuat kelas pengampuan…</div>
      <div v-else-if="courses.length" class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"><NuxtLink v-for="course in courses" :key="course.id" :to="`/elearning/courses/${course.id}`" class="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-indigo-300 hover:shadow-md"><span class="rounded-lg bg-indigo-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-indigo-700">Kelas Pengampuan</span><h2 class="mt-4 text-lg font-extrabold text-slate-900 group-hover:text-indigo-700">{{ subjectName(course) }}</h2><p class="mt-1 text-sm text-slate-500">{{ className(course) }}</p><div class="mt-6 border-t border-slate-100 pt-4 text-xs font-bold text-indigo-600">{{ course.meets_count || 0 }} pertemuan · Kelola kelas →</div></NuxtLink></div>
      <div v-else class="mt-6 rounded-3xl border border-slate-100 bg-white p-10 text-center shadow-sm"><p class="font-bold text-slate-700">Belum ada kelas pengampuan.</p><p class="mt-1 text-sm text-slate-500">Admin perlu memasangkan mata pelajaran kepada akun guru ini terlebih dahulu.</p></div>
    </div>
  </main>
</template>
