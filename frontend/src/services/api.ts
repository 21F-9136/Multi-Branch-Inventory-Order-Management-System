import axios from 'axios'
import type { AxiosError, AxiosInstance } from 'axios'
import { useAuthStore } from '@/store/auth'
import { useNotificationStore } from '@/store/notifications'

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8080'

const api: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
})

const isPublicAuthRoute = (url?: string) => {
  if (!url) return false
  // Axios may pass either '/auth/login' or 'auth/login' depending on usage.
  const normalized = url.startsWith('/') ? url : `/${url}`
  return normalized === '/auth/login' || normalized === '/auth/register'
}

// Request interceptor: add auth token
api.interceptors.request.use(
  (config) => {
    const authStore = useAuthStore()
    if (authStore.token && !isPublicAuthRoute(config.url)) {
      config.headers.Authorization = `Bearer ${authStore.token}`
    }
    return config
  },
  (error) => Promise.reject(error),
)

// Response interceptor: handle errors and auth failures
api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    const authStore = useAuthStore()
    const notificationStore = useNotificationStore()

    const url = (error.config?.url || '') as string
    const isLoginRequest = isPublicAuthRoute(url)

    // Handle 401 Unauthorized
    if (error.response?.status === 401 && !isLoginRequest) {
      authStore.logout()
      notificationStore.error('Session expired. Please login again.')
    }

    // Handle 403 Forbidden
    if (error.response?.status === 403) {
      notificationStore.error('You do not have permission to perform this action.')
    }

    // Handle 422 Validation Error
    if (error.response?.status === 422) {
      const data = error.response.data as any
      if (data.details) {
        const details = data.details
        const errors = Object.entries(details)
          .map(([key, msgs]) => `${key}: ${Array.isArray(msgs) ? msgs[0] : msgs}`)
          .join(', ')
        notificationStore.error(`Validation error: ${errors}`)
      }
    }

    // Handle 500+ Server Errors
    if (error.response?.status && error.response.status >= 500) {
      notificationStore.error('Server error. Please try again later.')
    }

    return Promise.reject(error)
  },
)

export default api
