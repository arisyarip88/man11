export const useAuth = () => {
  // Tambahkan opsi path dan sameSite agar cookie tersimpan presisi di seluruh route
  const token = useCookie<string | null>('auth_token', { 
    maxAge: 60 * 60 * 24 * 7, // 7 Hari
    path: '/',
    sameSite: 'lax'
  })
  
  const user = useState<any>('user', () => null)
  const isLoading = useState<boolean>('auth_loading', () => false)

  const config = useRuntimeConfig()
  const apiBase = config.public.apiBase || 'http://localhost:8000/api'

  // Helper Cek Role
  const hasRole = (allowedRoles: string | string[]) => {
    if (!user.value) return false
    const roles = Array.isArray(allowedRoles) ? allowedRoles : [allowedRoles]
    
    const userRoleName = typeof user.value.role === 'object' 
      ? user.value.role?.name 
      : user.value.role

    return roles.includes(userRoleName)
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
    } catch {
      token.value = null
      user.value = null
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

      // Ambil token & user dari respon
      token.value = res.token || res.access_token
      user.value = res.user || null

      return navigateTo('/admin/dashboard')
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

      return navigateTo('/admin/dashboard')
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

  return { token, user, isLoading, fetchUser, login, register, logout, hasRole }
}