<template>
  <div class="h-16 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 sticky top-0 z-40">
    <div class="h-full px-4 sm:px-6 lg:px-8 flex items-center justify-between">
      <!-- Left: Page Title -->
      <div class="flex-1">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
          {{ pageTitle }}
        </h1>
      </div>

      <!-- Right: Theme Toggle & User Menu -->
      <div class="flex items-center space-x-4">
        <!-- Theme Toggle -->
        <button
          @click="themeStore.toggleTheme()"
          class="p-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition"
          :title="`Switch to ${themeStore.isDark ? 'light' : 'dark'} mode`"
        >
          <svg v-if="themeStore.isDark" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
          </svg>
          <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path
              fill-rule="evenodd"
              d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.536l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.707.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zm5.657-9.193a1 1 0 00-1.414 0l-.707.707A1 1 0 005.05 6.464l.707-.707a1 1 0 001.414-1.414l-.707-.707zM3 11a1 1 0 100-2H2a1 1 0 100 2h1z"
              clip-rule="evenodd"
            ></path>
          </svg>
        </button>

        <!-- User Menu -->
        <div class="relative">
          <button
            @click="userMenuOpen = !userMenuOpen"
            class="p-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition flex items-center space-x-2"
          >
            <div class="w-8 h-8 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center">
              <span class="text-white text-sm font-semibold">
                {{ authStore.user?.name?.charAt(0).toUpperCase() || 'U' }}
              </span>
            </div>
          </button>

          <transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
          >
            <div
              v-if="userMenuOpen"
              class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700"
            >
              <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                  {{ authStore.user?.name }}
                </p>
                <p class="text-xs text-gray-600 dark:text-gray-400">
                  {{ authStore.user?.email }}
                </p>
              </div>
              <button
                @click="handleLogout"
                class="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition"
              >
                Logout
              </button>
            </div>
          </transition>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/store/auth'
import { useThemeStore } from '@/store/theme'
import { useNotificationStore } from '@/store/notifications'

defineProps<{
  pageTitle?: string
}>()

const router = useRouter()
const authStore = useAuthStore()
const themeStore = useThemeStore()
const notificationStore = useNotificationStore()
const userMenuOpen = ref(false)

const handleLogout = () => {
  authStore.logout()
  notificationStore.success('Logged out successfully')
  router.push('/login')
}
</script>
