// middleware/auth.ts
export default defineNuxtRouteMiddleware((to, from) => {
  const { token } = useAuth()

  if (!token.value) {
    return navigateTo('/')
  }
})