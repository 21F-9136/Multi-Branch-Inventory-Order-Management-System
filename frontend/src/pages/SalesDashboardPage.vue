<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Sales</h1>
      <p class="text-gray-600 dark:text-gray-400 mt-1">Create and track orders and invoices for your branch.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-blue-500">
        <p class="text-sm text-gray-600 dark:text-gray-400">My Branch Orders</p>
        <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.totalOrders }}</p>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-green-500">
        <p class="text-sm text-gray-600 dark:text-gray-400">Draft Orders</p>
        <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.draftOrders }}</p>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-indigo-500">
        <p class="text-sm text-gray-600 dark:text-gray-400">Branch Invoices</p>
        <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ stats.totalInvoices }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Orders</h2>
          <router-link to="/orders" class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium">Manage Orders</router-link>
        </div>

        <div v-if="isLoading" class="p-6 text-gray-600 dark:text-gray-400">Loading...</div>
        <div v-else-if="recentOrders.length === 0" class="p-6 text-gray-600 dark:text-gray-400">No orders found.</div>

        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Order</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Status</th>
                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="order in recentOrders" :key="order.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">#{{ order.id }}</td>
                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ order.status }}</td>
                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">${{ formatMoney(order.grand_total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Invoices</h2>
          <router-link to="/invoices" class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium">View Invoices</router-link>
        </div>

        <div v-if="isLoading" class="p-6 text-gray-600 dark:text-gray-400">Loading...</div>
        <div v-else-if="recentInvoices.length === 0" class="p-6 text-gray-600 dark:text-gray-400">No invoices found.</div>

        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Invoice</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Date</th>
                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="invoice in recentInvoices" :key="invoice.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ invoice.invoice_id }}</td>
                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ formatDate(invoice.created_at) }}</td>
                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">${{ formatMoney(invoice.grand_total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import api from '@/services/api'
import { useNotificationStore } from '@/store/notifications'

interface OrderRow {
  id: number
  status: string
  grand_total: number
}

interface InvoiceRow {
  id: number
  invoice_id: string
  created_at: string | null
  grand_total: number
}

const notificationStore = useNotificationStore()
const isLoading = ref(false)

const stats = reactive({
  totalOrders: 0,
  draftOrders: 0,
  totalInvoices: 0,
})

const recentOrders = ref<OrderRow[]>([])
const recentInvoices = ref<InvoiceRow[]>([])

const toNumber = (value: unknown): number => {
  if (typeof value === 'number') return value
  if (typeof value === 'string' && value.trim() !== '' && !Number.isNaN(Number(value))) return Number(value)
  return 0
}

const formatMoney = (amount: number | string) => {
  const num = typeof amount === 'string' ? parseFloat(amount) : amount
  return Number.isFinite(num) ? num.toFixed(2) : '0.00'
}

const formatDate = (value: string | null | undefined) => {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleDateString()
}

const loadDashboard = async () => {
  isLoading.value = true
  try {
    const [ordersRes, invoicesRes] = await Promise.all([
      api.get('/orders'),
      api.get('/invoices'),
    ])

    const ordersRaw = Array.isArray(ordersRes.data?.data?.items) ? ordersRes.data.data.items : []
    const invoicesRaw = Array.isArray(invoicesRes.data?.data?.items) ? invoicesRes.data.data.items : []

    const orders = ordersRaw.map((row: any) => ({
      id: Number(row.id),
      status: String(row.status ?? 'draft'),
      grand_total: toNumber(row.grand_total),
    }))

    const invoices = invoicesRaw.map((row: any) => ({
      id: Number(row.id),
      invoice_id: String(row.invoice_id ?? `INV-${String(row.id ?? '').padStart(4, '0')}`),
      created_at: row.created_at ?? null,
      grand_total: toNumber(row.grand_total),
    }))

    stats.totalOrders = orders.length
    stats.draftOrders = orders.filter((o: OrderRow) => o.status === 'draft').length
    stats.totalInvoices = invoices.length

    recentOrders.value = orders.slice(0, 5)
    recentInvoices.value = invoices.slice(0, 5)
  } catch (e: any) {
    notificationStore.error(e?.response?.data?.error || e?.message || 'Failed to load sales dashboard.')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadDashboard()
})
</script>
