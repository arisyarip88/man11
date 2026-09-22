<script setup lang="ts">
const { logout } = useAuth()
</script>

<template>
  <div class="min-h-screen pb-20">
    <slot />
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-4 py-2 backdrop-blur">
      <div class="mx-auto flex max-w-md items-center justify-around text-xs font-semibold text-slate-500">
        <NuxtLink to="/teacher/dashboard" class="rounded-xl px-4 py-2 hover:bg-indigo-50 hover:text-indigo-700">Dashboard</NuxtLink>
        <NuxtLink to="/teacher/classes" class="rounded-xl px-4 py-2 hover:bg-indigo-50 hover:text-indigo-700">Kelas Saya</NuxtLink>
        <button @click="logout" class="rounded-xl px-4 py-2 hover:bg-rose-50 hover:text-rose-600">Keluar</button>
      </div>
    </nav>
  </div>
</template>
