import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export interface User {
  id: number
  name: string
  email: string
  branch_id?: number
  role?: string
  is_active?: boolean
  created_at?: string
}

const USER_STORAGE_KEY = 'auth_user'
const TOKEN_STORAGE_KEY = 'auth_token'
const PERMISSIONS_STORAGE_KEY = 'auth_permissions'

const readStoredUser = (): User | null => {
  try {
    const raw = localStorage.getItem(USER_STORAGE_KEY)
    if (!raw) return null
    return JSON.parse(raw) as User
  } catch {
    return null
  }
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(readStoredUser())
  const token = ref<string | null>(localStorage.getItem(TOKEN_STORAGE_KEY) || null)
  const permissions = ref<string[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  try {
    const rawPermissions = localStorage.getItem(PERMISSIONS_STORAGE_KEY)
    if (rawPermissions) permissions.value = JSON.parse(rawPermissions) as string[]
  } catch {
    permissions.value = []
  }

  // Backend uses JWT for protected APIs.
  const isAuthenticated = computed(() => !!token.value)
  const userRole = computed(() => user.value?.role || 'user')
  const isSuperAdmin = computed(() => userRole.value === 'super_admin')
  const isAdmin = computed(() => userRole.value === 'admin' || isSuperAdmin.value)
  const isManager = computed(() => userRole.value === 'manager')
  const isSales = computed(() => userRole.value === 'sales')

  const setUser = (userData: User) => {
    user.value = userData
    localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(userData))
  }

  const setToken = (newToken: string | null) => {
    token.value = newToken
    if (newToken) {
      localStorage.setItem(TOKEN_STORAGE_KEY, newToken)
    } else {
      localStorage.removeItem(TOKEN_STORAGE_KEY)
    }
  }

  const setPermissions = (newPermissions: string[] | null | undefined) => {
    const normalized = Array.isArray(newPermissions) ? newPermissions.map(String) : []
    permissions.value = normalized
    localStorage.setItem(PERMISSIONS_STORAGE_KEY, JSON.stringify(normalized))
  }

  const logout = () => {
    user.value = null
    token.value = null
    permissions.value = []
    localStorage.removeItem(USER_STORAGE_KEY)
    localStorage.removeItem(TOKEN_STORAGE_KEY)
    localStorage.removeItem(PERMISSIONS_STORAGE_KEY)
  }

  const setLoading = (loading: boolean) => {
    isLoading.value = loading
  }

  const setError = (err: string | null) => {
    error.value = err
  }

  return {
    user,
    token,
    permissions,
    isLoading,
    error,
    isAuthenticated,
    userRole,
    isSuperAdmin,
    isAdmin,
    isManager,
    isSales,
    setUser,
    setToken,
    setPermissions,
    logout,
    setLoading,
    setError,
  }
})
