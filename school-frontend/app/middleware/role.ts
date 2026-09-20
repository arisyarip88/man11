export default defineNuxtRouteMiddleware((to) => {
  const { user, hasRole } = useAuth()

  // 1. Cek Login
  if (!user.value) {
    return navigateTo('/login')
  }

  // 2. Cek Role yang dibutuhkan dari meta halaman
  const requiredRoles = to.meta.roles as string | string[] | undefined

  if (requiredRoles && !hasRole(requiredRoles)) {
    return navigateTo('/unauthorized') // Redirect jika role tidak sesuai
  }
})