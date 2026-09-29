<script setup lang="ts">
definePageMeta({
  middleware: [
    () => {
      return navigateTo('/teacher/dashboard')
    }
  ]
})

await navigateTo('/teacher/dashboard')
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-50 text-xs text-slate-400">
    Mengalihkan ke Dashboard Guru...
  </div>
</template>
