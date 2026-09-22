export const useAuth = () => {
  const token = useCookie<string | null>('auth_token', { 
    maxAge: 60 * 60 * 24 * 7, // 7 Hari
    path: '/',
    sameSite: 'lax'
  })
  
  const user = useState<any>('user', () => null)
  const isLoading = useState<boolean>('auth_loading', () => false)

  const config = useRuntimeConfig()
  const apiBase = config.public.apiBase || 'http://localhost:8000/api'

  // Helper Get User Role Name
  const getUserRole = () => {
    if (!user.value) return ''
    if (typeof user.value.role === 'object' && user.value.role !== null) {
      return (user.value.role.name || '').toLowerCase()
    }
    if (typeof user.value.role === 'string') {
      return user.value.role.toLowerCase()
    }
    if (user.value.role_id === 1) return 'admin'
    if (user.value.role_id === 2) return 'teacher'
    if (user.value.role_id === 3) return 'student'
    return ''
  }

  // Helper Cek Role
  const hasRole = (allowedRoles: string | string[]) => {
    if (!user.value) return false
    const roles = Array.isArray(allowedRoles) ? allowedRoles : [allowedRoles]
    const currentRole = getUserRole()
    return roles.map(r => r.toLowerCase()).includes(currentRole)
  }

  // Helper Dapatkan Halaman Utama Berdasarkan Role
  const getRoleHome = () => {
    const role = getUserRole()
    if (role === 'admin' || role === 'super_admin') {
      return '/admin/dashboard'
    } else if (role === 'teacher' || role === 'guru') {
      return '/teacher/dashboard'
    } else if (role === 'student' || role === 'siswa') {
      return '/student/dashboard'
    }
    return '/elearning'
  }

  const fetchUser = async () => {
    if (!token.value) return null
    try {
      user.value = await $fetch(`${apiBase}/me`, {
        headers: { 
          Authorization: `Bearer ${token.value}`,
          Accept: 'application/json'
        }
      })
      return user.value
    } catch {
      token.value = null
      user.value = null
      return null
    }
  }

  const login = async (credentials: any) => {
    isLoading.value = true
    try {
      const res: any = await $fetch(`${apiBase}/login`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: credentials
      })

      token.value = res.token || res.access_token
      user.value = res.user || null

      const targetPath = getRoleHome()
      return navigateTo(targetPath)
    } finally {
      isLoading.value = false
    }
  }

  const register = async (data: any) => {
    isLoading.value = true
    try {
      const res: any = await $fetch(`${apiBase}/register`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: data
      })

      token.value = res.token || res.access_token
      user.value = res.user || null

      const targetPath = getRoleHome()
      return navigateTo(targetPath)
    } finally {
      isLoading.value = false
    }
  }

  const logout = async () => {
    if (token.value) {
      await $fetch(`${apiBase}/logout`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token.value}` }
      }).catch(() => {})
    }
    token.value = null
    user.value = null
    return navigateTo('/login')
  }

  return { token, user, isLoading, fetchUser, login, register, logout, hasRole, getUserRole, getRoleHome }
}