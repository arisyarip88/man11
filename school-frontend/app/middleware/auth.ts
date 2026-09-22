export default defineNuxtRouteMiddleware(async (to, from) => {
  const { token, user, fetchUser, getUserRole, getRoleHome } = useAuth()

  // 1. Cek keberadaan Token
  if (!token.value) {
    if (to.path !== '/login' && to.path !== '/register') {
      return navigateTo('/login')
    }
    return
  }

  // 2. Ambil data user jika belum ada di state
  if (!user.value) {
    const fetched = await fetchUser()
    if (!fetched) {
      return navigateTo('/login')
    }
  }

  const role = getUserRole()

  // 3. Route Guard Berdasarkan Prefix Path
  if (to.path.startsWith('/admin')) {
    if (role !== 'admin' && role !== 'super_admin') {
      return navigateTo(getRoleHome())
    }
  } else if (to.path.startsWith('/teacher')) {
    if (role !== 'teacher' && role !== 'admin' && role !== 'super_admin') {
      return navigateTo(getRoleHome())
    }
  } else if (to.path.startsWith('/student')) {
    if (role !== 'student' && role !== 'admin' && role !== 'super_admin') {
      return navigateTo(getRoleHome())
    }
  }
})