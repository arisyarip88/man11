<script setup lang="ts">
definePageMeta({ layout: 'student', middleware: 'auth' })
await navigateTo('/student/dashboard', { replace: true })
</script>

<template>
  <div></div>
</template>
