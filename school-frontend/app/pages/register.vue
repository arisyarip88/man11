<script setup>
// Registrasi mandiri dinonaktifkan untuk menjaga integritas data akademik madrasah.
// Semua akun siswa dan guru dibuat secara resmi oleh Admin / Tata Usaha (TU).
definePageMeta({
  layout: false,
  middleware: [
    () => navigateTo('/login')
  ]
})
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-50 text-slate-500 text-xs">
    <span>Mengalihkan ke halaman masuk...</span>
  </div>
</template>
