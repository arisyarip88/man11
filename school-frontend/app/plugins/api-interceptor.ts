import { ofetch } from 'ofetch'

export default defineNuxtPlugin((nuxtApp) => {
  const token = useCookie<string | null>('auth_token')
  const user = useState<any>('user')

  // Interceptor global ofetch
  globalThis.$fetch = ofetch.create({
    onResponseError({ response }) {
      if (response?.status === 401) {
        const data = response._data
        if (data?.error_code === 'session_replaced') {
          // A6: Token dibatalkan karena login di perangkat lain
          token.value = null
          user.value = null
          if (import.meta.client) {
            // Hindari looping bila sudah di halaman login
            if (!window.location.pathname.includes('/login')) {
              window.location.href = '/login?reason=session_replaced'
            }
          }
        }
      }
    }
  })
})
