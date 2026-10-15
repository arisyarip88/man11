<script setup lang="ts">
definePageMeta({ middleware: 'auth' })
const { getRoleHome } = useAuth()
await navigateTo(getRoleHome(), { replace: true })
</script>

<template>
  <div></div>
</template>