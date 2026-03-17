<template>
  <aside class="h-screen bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 pt-6 overflow-y-auto">
    <!-- Logo -->
    <div class="px-6 pb-6 border-b border-gray-200 dark:border-gray-800">
      <div class="flex items-center space-x-2">
        <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
          <span class="text-white font-bold text-sm">DS</span>
        </div>
        <span class="text-lg font-bold text-gray-900 dark:text-white">DigitalSofts</span>
      </div>
    </div>

    <!-- Navigation -->
    <div class="px-4 py-6">
      <nav class="space-y-2">
        <router-link
          v-for="item in visibleNavItems"
          :key="item.path"
          :to="item.path"
          class="flex items-center space-x-3 px-4 py-2 rounded-lg transition"
          :class="
            isActive(item.path)
              ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400'
              : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'
          "
        >
          <!-- Icons (minimal inline set; keyed by the provided icon names) -->
          <svg v-if="item.icon === 'home'" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4z"></path>
            <path
              fill-rule="evenodd"
              d="M3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zm8 0a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"
              clip-rule="evenodd"
            ></path>
          </svg>

          <svg v-else-if="item.icon === 'building'" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
          </svg>

          <svg v-else-if="item.icon === 'box'" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 6H6.28l-.31-1.243A1 1 0 005 4H3z"></path>
            <path d="M16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"></path>
          </svg>

          <svg v-else-if="item.icon === 'layers'" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6-4a2 2 0 100-4 2 2 0 000 4z"></path>
          </svg>

          <svg v-else-if="item.icon === 'shopping-cart'" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path
              fill-rule="evenodd"
              d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
              clip-rule="evenodd"
            ></path>
          </svg>

          <svg v-else-if="item.icon === 'users'" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path
              fill-rule="evenodd"
              d="M10 2a4 4 0 100 8 4 4 0 000-8zM4 14a6 6 0 1112 0v1a1 1 0 11-2 0v-1a4 4 0 00-8 0v1a1 1 0 11-2 0v-1z"
              clip-rule="evenodd"
            ></path>
          </svg>

          <svg v-else-if="item.icon === 'document'" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path
              fill-rule="evenodd"
              d="M4 3a2 2 0 012-2h5.586a1 1 0 01.707.293l3.414 3.414A1 1 0 0116 5.414V17a2 2 0 01-2 2H6a2 2 0 01-2-2V3zm3 6a1 1 0 000 2h6a1 1 0 100-2H7zm0 4a1 1 0 000 2h6a1 1 0 100-2H7z"
              clip-rule="evenodd"
            ></path>
          </svg>

          <span>{{ item.name }}</span>
        </router-link>
      </nav>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/store/auth'

type AppRole = 'super_admin' | 'admin' | 'manager' | 'sales'

type NavItem = {
  name: string
  path: string
  icon: 'home' | 'building' | 'box' | 'layers' | 'shopping-cart' | 'users' | 'document'
}

const route = useRoute()
const authStore = useAuthStore()

const isActive = (path: string) => {
  return route.path === path || route.path.startsWith(path + '/')
}

const normalizeRole = (value: unknown): AppRole | '' => {
  if (!value) return ''
  const normalized = String(value).trim().toLowerCase().replace(/\s+/g, '_')
  if (normalized === 'super_admin') return 'super_admin'
  if (normalized === 'admin') return 'admin'
  if (normalized === 'manager') return 'manager'
  if (normalized === 'sales') return 'sales'
  return ''
}

const role = computed(() => normalizeRole(authStore.user?.role))

const adminNavItems: NavItem[] = [
  { name: 'Dashboard', path: '/dashboard', icon: 'home' },
  { name: 'Branches', path: '/branches', icon: 'building' },
  { name: 'Products', path: '/products', icon: 'box' },
  { name: 'Inventory', path: '/inventory', icon: 'layers' },
  { name: 'Stock Ledger', path: '/stock-ledger', icon: 'layers' },
  { name: 'Orders', path: '/orders', icon: 'shopping-cart' },
  { name: 'Users', path: '/users', icon: 'users' },
]

const managerNavItems: NavItem[] = [
  { name: 'Dashboard', path: '/manager/dashboard', icon: 'home' },
  { name: 'Inventory', path: '/inventory', icon: 'layers' },
  { name: 'Stock Ledger', path: '/stock-ledger', icon: 'layers' },
  { name: 'Orders', path: '/orders', icon: 'shopping-cart' },
  { name: 'Invoices', path: '/invoices', icon: 'document' },
]

const salesNavItems: NavItem[] = [
  { name: 'Dashboard', path: '/sales/dashboard', icon: 'home' },
  { name: 'Orders', path: '/orders', icon: 'shopping-cart' },
  { name: 'Invoices', path: '/invoices', icon: 'document' },
]

const visibleNavItems = computed(() => {
  if (role.value === 'manager') return managerNavItems
  if (role.value === 'sales') return salesNavItems
  return adminNavItems
})
</script>
