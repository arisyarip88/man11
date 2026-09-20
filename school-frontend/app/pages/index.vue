<script setup>
definePageMeta({
  middleware: [
    async function () {
      const { token, user, fetchUser } = useAuth()

      // Jika ada token tapi user state kosong (akibat reload)
      if (token.value && !user.value) {
        await fetchUser()
      }

      // Jika terautentikasi -> ke dashboard, jika tidak -> ke login
      if (token.value && user.value) {
        return navigateTo('/admin/dashboard', { replace: true })
      }

      return navigateTo('/login', { replace: true })
    }
  ]
})
</script>