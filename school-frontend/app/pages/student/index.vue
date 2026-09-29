<script setup lang="ts">
definePageMeta({
  middleware: [
    () => {
      return navigateTo('/student/dashboard')
    }
  ]
})

await navigateTo('/student/dashboard')
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-50 text-xs text-slate-400">
    Mengalihkan ke Dashboard Siswa...
  </div>
</template>
