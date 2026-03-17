<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800 flex items-center justify-center p-4">
    <!-- Login Card -->
    <div class="w-full max-w-md">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 space-y-6">
        <!-- Logo -->
        <div class="flex justify-center">
          <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
            <span class="text-white font-bold text-2xl">I</span>
          </div>
        </div>

        <!-- Title -->
        <div class="text-center">
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Welcome Back
          </h1>
          <p class="text-gray-600 dark:text-gray-400 mt-2">
            Sign in to manage your inventory
          </p>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleLogin" class="space-y-4">
          <!-- Email -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Email
            </label>
            <input
              v-model="form.email"
              type="email"
              placeholder="you@example.com"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-0 dark:focus:ring-offset-gray-800 transition"
              :disabled="isLoading"
            />
            <p v-if="errors.email" class="text-red-500 text-sm mt-1">{{ errors.email }}</p>
          </div>

          <!-- Password -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Password
            </label>
            <input
              v-model="form.password"
              type="password"
              placeholder="••••••••"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-0 dark:focus:ring-offset-gray-800 transition"
              :disabled="isLoading"
            />
            <p v-if="errors.password" class="text-red-500 text-sm mt-1">{{ errors.password }}</p>
          </div>

          <!-- Error Message -->
          <div
            v-if="apiError"
            class="px-4 py-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg"
          >
            <p class="text-sm text-red-600 dark:text-red-400">{{ apiError }}</p>
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="isLoading"
            class="w-full py-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 disabled:from-gray-400 disabled:to-gray-400 text-white font-semibold rounded-lg transition duration-200 flex items-center justify-center space-x-2"
          >
            <span v-if="isLoading" class="animate-spin">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" opacity="0.5" />
              </svg>
            </span>
            <span>{{ isLoading ? 'Signing in...' : 'Sign In' }}</span>
          </button>
        </form>

        <!-- Footer -->
        <div class="text-center text-xs text-gray-500 dark:text-gray-400 space-y-1 pt-2">
          <p>© 2026 DigitalSofts ERP</p>
          <p>Secure Inventory Management System</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '@/store/auth'
import { useNotificationStore } from '@/store/notifications'
import api from '@/services/api'

const router = useRouter()
const authStore = useAuthStore()
const notificationStore = useNotificationStore()

const isLoading = ref(false)
const apiError = ref<string | null>(null)

const form = reactive({
  email: '',
  password: '',
})

const errors = reactive({
  email: '',
  password: '',
})

const validateForm = (): boolean => {
  errors.email = ''
  errors.password = ''

  if (!form.email) {
    errors.email = 'Email is required'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
    errors.email = 'Please enter a valid email'
  }

  if (!form.password) {
    errors.password = 'Password is required'
  } else if (form.password.length < 6) {
    errors.password = 'Password must be at least 6 characters'
  }

  return !errors.email && !errors.password
}

const handleLogin = async () => {
  if (!validateForm()) return

  isLoading.value = true
  apiError.value = null

  try {
    const response = await api.post(
      '/auth/login',
      {
        email: form.email,
        password: form.password,
      },
      {
        headers: {
          'Content-Type': 'application/json',
        },
      },
    )

    const data: any = response.data

    if (data?.success === true) {
      const user = data?.data?.user
      const token = data?.data?.token
      const permissions = data?.data?.permissions
      if (!user) {
        apiError.value = 'Login succeeded but no user was returned.'
        notificationStore.error(apiError.value)
        return
      }

      if (!token) {
        apiError.value = 'Login succeeded but no token was returned.'
        notificationStore.error(apiError.value)
        return
      }

      authStore.setUser(user)
      authStore.setToken(String(token))
      authStore.setPermissions(Array.isArray(permissions) ? permissions : [])

      notificationStore.success('Login successful')
      await router.push('/dashboard')
      return
    }

    const backendMessage = data?.error || data?.message || 'Invalid email or password.'
    apiError.value = String(backendMessage)
    notificationStore.error(apiError.value)
  } catch (err: unknown) {
    if (axios.isAxiosError(err)) {
      const status = err.response?.status
      const data: any = err.response?.data

      apiError.value =
        data?.error ||
        data?.message ||
        (status ? `Login failed (HTTP ${status}).` : 'Login failed. Please try again.')
    } else {
      apiError.value = 'Login failed. Please try again.'
    }

    notificationStore.error(apiError.value ?? 'Login failed. Please try again.')
  } finally {
    isLoading.value = false
  }
}
</script>
