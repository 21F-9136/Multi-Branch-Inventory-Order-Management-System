import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/store/auth'

import MainLayout from '@/layouts/MainLayout.vue'
import LoginPage from '@/pages/LoginPage.vue'
import DashboardPage from '@/pages/DashboardPage.vue'
import ManagerDashboardPage from '@/pages/ManagerDashboardPage.vue'
import SalesDashboardPage from '@/pages/SalesDashboardPage.vue'
import ProductsPage from '@/pages/ProductsPage.vue'
import SKUsPage from '@/pages/SKUsPage.vue'
import InventoryPage from '@/pages/InventoryPage.vue'
import StockLedgerPage from '@/pages/StockLedgerPage.vue'
import OrdersPage from '@/pages/OrdersPage.vue'
import OrderDetailsPage from '@/pages/OrderDetailsPage.vue'
import InvoicesPage from '@/pages/InvoicesPage.vue'
import InvoicePage from '@/pages/InvoicePage.vue'
import UsersPage from '@/pages/UsersPage.vue'
import BranchesPage from '@/pages/BranchesPage.vue'

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: LoginPage,
    meta: { requiresAuth: false },
  },
  {
    path: '/',
    component: MainLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: 'dashboard',
      },
      {
        path: 'dashboard',
        name: 'dashboard',
        component: DashboardPage,
        meta: { title: 'Dashboard', roles: ['super_admin', 'admin'] },
      },
      {
        path: 'manager/dashboard',
        name: 'manager-dashboard',
        component: ManagerDashboardPage,
        meta: { title: 'Manager Dashboard', roles: ['manager'] },
      },
      {
        path: 'sales/dashboard',
        name: 'sales-dashboard',
        component: SalesDashboardPage,
        meta: { title: 'Sales', hideTopbarTitle: true, roles: ['sales'] },
      },
      {
        path: 'products',
        name: 'products',
        component: ProductsPage,
        meta: { title: 'Products', roles: ['super_admin', 'admin'] },
      },
      {
        path: 'skus',
        name: 'skus',
        component: SKUsPage,
        meta: { title: 'SKUs', roles: ['super_admin', 'admin', 'manager'] },
      },
      {
        path: 'inventory',
        name: 'inventory',
        component: InventoryPage,
        meta: { title: 'Inventory', roles: ['super_admin', 'admin', 'manager'] },
      },
      {
        path: 'stock-ledger',
        name: 'stock-ledger',
        component: StockLedgerPage,
        meta: { title: 'Stock Ledger', roles: ['super_admin', 'admin', 'manager'] },
      },
      {
        path: 'inventory/ledger',
        redirect: '/stock-ledger',
      },
      {
        path: 'orders',
        name: 'orders',
        component: OrdersPage,
        meta: { title: 'Orders', roles: ['super_admin', 'admin', 'manager', 'sales'] },
      },
      {
        path: 'orders/:id',
        name: 'order-details',
        component: OrderDetailsPage,
        meta: { title: 'Order Details', roles: ['super_admin', 'admin', 'manager', 'sales'] },
      },
      {
        path: 'invoices',
        name: 'invoices',
        component: InvoicesPage,
        meta: { title: 'Invoices', roles: ['super_admin', 'admin', 'manager', 'sales'] },
      },
      {
        path: 'invoices/:id',
        name: 'invoice-details',
        component: InvoicePage,
        meta: { title: 'Invoice', roles: ['super_admin', 'admin', 'manager', 'sales'] },
      },
      {
        path: 'users',
        name: 'users',
        component: UsersPage,
        meta: { title: 'Users', roles: ['super_admin', 'admin'] },
      },
      {
        path: 'branches',
        name: 'branches',
        component: BranchesPage,
        meta: { title: 'Branches', roles: ['super_admin', 'admin'] },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/dashboard',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Auth guard
router.beforeEach((to) => {
  const authStore = useAuthStore()

  const debug = (...args: unknown[]) => {
    if (import.meta.env.DEV) {
      // eslint-disable-next-line no-console
      console.debug('[router]', ...args)
    }
  }

  const normalizeRole = (value: unknown) => {
    if (!value) return ''
    return String(value)
      .trim()
      // handle common formats like "SuperAdmin" in addition to "Super Admin"
      .replace(/([a-z])([A-Z])/g, '$1 $2')
      .toLowerCase()
      // collapse whitespace, hyphens, and other separators to underscores
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '')
  }

  const homeForRole = (role: string) => {
    if (role === 'manager') return '/manager/dashboard'
    if (role === 'sales') return '/sales/dashboard'
    return '/dashboard'
  }

  // Treat every route except /login as protected.
  const requiresAuth = to.path !== '/login'

  if (!authStore.isAuthenticated && requiresAuth) {
    debug('redirect:unauthenticated', { to: to.fullPath })
    return '/login'
  }

  if (authStore.isAuthenticated && to.path === '/login') {
    debug('redirect:already-authenticated', { to: to.fullPath })
    return homeForRole(normalizeRole(authStore.user?.role))
  }

  const role = normalizeRole(authStore.user?.role)
  if (role === 'manager' && to.path === '/dashboard') {
    debug('redirect:manager-dashboard', { to: to.fullPath })
    return '/manager/dashboard'
  }
  if (role === 'sales' && to.path === '/dashboard') {
    debug('redirect:sales-dashboard', { to: to.fullPath })
    return '/sales/dashboard'
  }

  const roleMeta = [...to.matched]
    .reverse()
    .find((record) => Array.isArray(record.meta.roles))?.meta.roles

  if (roleMeta && authStore.isAuthenticated) {
    const allowedRoles = roleMeta as string[]
    const knownRoles = new Set(['super_admin', 'admin', 'manager', 'sales'])

    if (role && knownRoles.has(role) && !allowedRoles.includes(role)) {
      debug('redirect:role-denied', {
        to: to.fullPath,
        role,
        rawRole: authStore.user?.role,
        allowedRoles,
      })
      return homeForRole(role)
    }
  }

  return true
})

export default router
