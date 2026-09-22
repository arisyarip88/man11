export default defineNuxtRouteMiddleware(async (to) => {
  const protectedPrefix = ['/admin', '/teacher', '/student']
  if (!protectedPrefix.some(prefix => to.path.startsWith(prefix))) return

  const { token, user, fetchUser, getUserRole, getRoleHome } = useAuth()
  if (!token.value) return navigateTo('/login')
  if (!user.value && !await fetchUser()) return navigateTo('/login')

  const role = getUserRole()
  const allowed =
    (to.path.startsWith('/admin') && ['admin', 'super_admin'].includes(role)) ||
    (to.path.startsWith('/teacher') && ['teacher', 'guru', 'admin', 'super_admin'].includes(role)) ||
    (to.path.startsWith('/student') && ['student', 'siswa', 'admin', 'super_admin'].includes(role))

  if (!allowed) return navigateTo(getRoleHome())
})
