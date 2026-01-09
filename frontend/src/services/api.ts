import axios, { type AxiosInstance, type AxiosError } from 'axios'
import router from '@/router'

const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      // Don't redirect if we're checking auth (/auth/me) - let the auth store handle it
      const isAuthCheck = error.config?.url?.includes('/auth/me')
      const isLoginPage = router.currentRoute.value.name === 'login'

      if (!isAuthCheck && !isLoginPage) {
        // Clear storage and redirect for other 401 errors
        localStorage.removeItem('token')
        localStorage.removeItem('user')
        localStorage.removeItem('permissions')
        router.push({ name: 'login' })
      }
    }
    return Promise.reject(error)
  }
)

export default api
