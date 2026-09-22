<script setup>
definePageMeta({
  middleware: 'auth'
})

const { getRoleHome } = useAuth()
await navigateTo(getRoleHome(), { replace: true })
</script>
