// middleware/role.ts
export default defineNuxtRouteMiddleware(async (to) => {
  const { token, user, fetchUser, hasRole } = useAuth()

  // 1. Jika tidak ada token, langsung arahkan ke login
  if (!token.value) {
    return navigateTo('/login')
  }

  // 2. Jika ada token tetapi data user belum dimuat (misal setelah halaman di-reload),
  //    panggil fetchUser() terlebih dahulu sebelum mengecek halaman
  if (!user.value) {
    await fetchUser()
  }

  // 3. Jika setelah fetchUser() user masih null (token tidak valid/expired), tendang ke login
  if (!user.value) {
    return navigateTo('/login')
  }

  // 4. Ambil role yang diizinkan dari meta halaman
  const allowedRoles = to.meta.roles as string[] | string | undefined

  // 5. Jika halaman membutuhkan role tertentu dan user tidak memiliki akses
  if (allowedRoles && !hasRole(allowedRoles)) {
    return navigateTo('/unauthorized') // Halaman 403 / Ditolak
  }
})