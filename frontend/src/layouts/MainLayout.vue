<template>
  <div class="flex h-screen bg-gray-50 dark:bg-gray-950">
    <Sidebar class="w-64" />

    <main class="flex-1 flex flex-col">
      <Topbar :page-title="pageTitle" />

      <div class="flex-1 overflow-auto p-6">
        <router-view />
      </div>
    </main>

    <!-- Toast Container -->
    <div class="fixed bottom-4 right-4 z-50 space-y-2">
      <div
        v-for="toast in notificationStore.toasts"
        :key="toast.id"
        class="animate-in fade-in slide-in-from-right-4 duration-300"
        :class="getToastClasses(toast.type)"
      >
        <div class="flex items-center space-x-3 px-4 py-3 rounded-lg shadow-lg">
          <component :is="getToastIcon(toast.type)" class="w-5 h-5 flex-shrink-0" />
          <p>{{ toast.message }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import Sidebar from './Sidebar.vue'
import Topbar from './Topbar.vue'
import { useNotificationStore } from '@/store/notifications'

const route = useRoute()

const notificationStore = useNotificationStore()

const pageTitle = computed(() => {
  if (route.meta?.hideTopbarTitle === true) return ''

  const title = route.meta?.title
  if (typeof title === 'string' && title.trim() !== '') return title
  return 'Dashboard'
})

const getToastClasses = (type: string) => {
  const base = 'text-white font-medium'
  const variants = {
    success: 'bg-green-500 dark:bg-green-600',
    error: 'bg-red-500 dark:bg-red-600',
    warning: 'bg-yellow-500 dark:bg-yellow-600',
    info: 'bg-blue-500 dark:bg-blue-600',
  }
  return `${base} ${variants[type as keyof typeof variants] || variants.info}`
}

const getToastIcon = (type: string) => {
  const icons = {
    success: 'CheckCircleIcon',
    error: 'XCircleIcon',
    warning: 'ExclamationIcon',
    info: 'InformationCircleIcon',
  }
  return icons[type as keyof typeof icons] || icons.info
}
</script>
