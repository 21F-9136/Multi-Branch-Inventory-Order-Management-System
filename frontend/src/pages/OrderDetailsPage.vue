<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Order Details</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1" v-if="order">Order #{{ order.id }}</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          v-if="canCompleteOrder"
          @click="showCompleteConfirm = true"
          :disabled="isCompleting"
          class="px-3 py-2 text-sm font-medium rounded-lg bg-emerald-500 hover:bg-emerald-600 disabled:bg-emerald-300 text-white"
        >
          {{ isCompleting ? 'Completing...' : 'Complete Order' }}
        </button>
        <button
          v-if="canCancelOrder"
          @click="showCancelConfirm = true"
          :disabled="isCancelling"
          class="px-3 py-2 text-sm font-medium rounded-lg bg-red-500 hover:bg-red-600 disabled:bg-red-300 text-white"
        >
          {{ isCancelling ? 'Cancelling...' : 'Cancel Order' }}
        </button>
        <button
          v-if="order"
          @click="openInvoiceView"
          class="px-3 py-2 text-sm font-medium rounded-lg bg-blue-500 hover:bg-blue-600 text-white"
        >
          View Invoice
        </button>
        <button
          v-if="order"
          @click="openInvoicePrint"
          class="px-3 py-2 text-sm font-medium rounded-lg bg-green-500 hover:bg-green-600 text-white"
        >
          Print Invoice
        </button>
        <button
          v-if="order"
          @click="openInvoicePdf"
          class="px-3 py-2 text-sm font-medium rounded-lg bg-indigo-500 hover:bg-indigo-600 text-white"
        >
          Download Invoice (PDF)
        </button>
        <router-link to="/orders" class="px-4 py-2 text-sm font-medium text-blue-500 hover:text-blue-700 dark:hover:text-blue-400">
          ← Back to Orders
        </router-link>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <p class="text-gray-600 dark:text-gray-400">Loading order details...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 rounded-lg shadow p-6 border border-red-200 dark:border-red-800">
      <p class="text-red-600 dark:text-red-400">{{ error }}</p>
    </div>

    <!-- Order Not Found -->
    <div v-else-if="!order" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <p class="text-gray-600 dark:text-gray-400">Order not found.</p>
    </div>

    <!-- Order Content -->
    <div v-else class="space-y-6">
      <!-- Order Information -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Order Info Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Order Information</h2>
          <div class="space-y-3">
            <div>
              <p class="text-xs text-gray-600 dark:text-gray-400">Order ID</p>
              <p class="text-lg font-medium text-gray-900 dark:text-white">#{{ order.id }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-600 dark:text-gray-400">Branch</p>
              <p class="text-lg font-medium text-gray-900 dark:text-white">{{ order.branch_name || '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-600 dark:text-gray-400">Created By</p>
              <p class="text-lg font-medium text-gray-900 dark:text-white">{{ order.creator_name || creatorFallback }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-600 dark:text-gray-400">Status</p>
              <span
                class="inline-block px-3 py-1 text-xs font-semibold rounded-full mt-1"
                :class="
                  order.status === 'placed'
                    ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400'
                    : order.status === 'draft'
                      ? 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'
                      : 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400'
                "
              >
                {{ order.status }}
              </span>
            </div>
          </div>
        </div>

        <!-- Timestamps Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Timeline</h2>
          <div class="space-y-3">
            <div>
              <p class="text-xs text-gray-600 dark:text-gray-400">Created</p>
              <p class="text-lg font-medium text-gray-900 dark:text-white">{{ formatDateTime(order.created_at) }}</p>
            </div>
            <div v-if="order.placed_at">
              <p class="text-xs text-gray-600 dark:text-gray-400">Placed</p>
              <p class="text-lg font-medium text-gray-900 dark:text-white">{{ formatDateTime(order.placed_at) }}</p>
            </div>
            <div v-if="order.completed_at">
              <p class="text-xs text-gray-600 dark:text-gray-400">Completed</p>
              <p class="text-lg font-medium text-gray-900 dark:text-white">{{ formatDateTime(order.completed_at) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Order Items -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Order Items</h2>
        </div>

        <div v-if="!order.items || order.items.length === 0" class="p-6 text-center text-gray-600 dark:text-gray-400">
          <p>No items in this order.</p>
        </div>

        <table v-else class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Product</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">SKU Code</th>
              <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white">Unit Price</th>
              <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white">Quantity</th>
              <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white">Line Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="item in order.items" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
              <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                <div>
                  <p>{{ item.product_name || item.sku_name || '—' }}</p>
                  <p v-if="item.sku_name && item.sku_name !== item.product_name" class="text-xs text-gray-600 dark:text-gray-400 mt-1">{{ item.sku_name }}</p>
                </div>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ item.sku_code || '—' }}</td>
              <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">${{ formatMoney(item.unit_price) }}</td>
              <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">{{ item.quantity }}</td>
              <td class="px-6 py-4 text-sm font-medium text-right text-gray-900 dark:text-white">${{ formatMoney(item.line_total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Order Totals -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Subtotal -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Subtotal</p>
          <p class="text-3xl font-bold text-gray-900 dark:text-white">${{ formatMoney(order.subtotal) }}</p>
        </div>

        <!-- Tax -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Tax Amount</p>
          <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">${{ formatMoney(order.tax_amount) }}</p>
        </div>

        <!-- Grand Total -->
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-600 dark:to-blue-700 rounded-lg shadow p-6">
          <p class="text-sm text-blue-100 mb-2">Grand Total</p>
          <p class="text-3xl font-bold text-white">${{ formatMoney(order.grand_total) }}</p>
        </div>
      </div>
    </div>

    <ConfirmDialog
      v-model="showCancelConfirm"
      title="Cancel order?"
      message="Are you sure you want to cancel this order?"
      icon="warning"
      :is-dangerous="true"
      yes-label="Cancel Order"
      no-label="Keep Order"
      @confirm="cancelOrder"
    />

    <ConfirmDialog
      v-model="showCompleteConfirm"
      title="Complete order?"
      message="Are you sure you want to complete this order?"
      icon="warning"
      :is-dangerous="false"
      yes-label="Confirm"
      no-label="Cancel"
      @confirm="completeOrder"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { useAuthStore } from '@/store/auth'
import { useNotificationStore } from '@/store/notifications'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const notificationStore = useNotificationStore()
const isLoading = ref(false)
const error = ref<string | null>(null)
const isCancelling = ref(false)
const showCancelConfirm = ref(false)
const isCompleting = ref(false)
const showCompleteConfirm = ref(false)

interface OrderItem {
  id: number
  order_id: number
  sku_id: number
  quantity: number
  unit_price: number
  line_total: number
  sku_name: string
  sku_code: string
  product_id: number
  product_name: string
  tax_percent: number
}

interface Order {
  id: number
  branch_id: number
  branch_name: string
  user_id: number | null
  creator_name: string | null
  status: string
  total_amount: number
  subtotal: number
  tax_amount: number
  grand_total: number
  placed_at: string | null
  completed_at: string | null
  created_at: string | null
  items: OrderItem[]
}

const order = ref<Order | null>(null)

const canCancelByRole = computed(() => authStore.isAdmin || authStore.isManager || authStore.isSales)
const canCancelByStatus = computed(() => {
  const status = (order.value?.status || '').toLowerCase()
  return status === 'draft' || status === 'placed'
})
const canCancelOrder = computed(() => !!order.value && canCancelByRole.value && canCancelByStatus.value)
const canCompleteByStatus = computed(() => {
  const status = (order.value?.status || '').toLowerCase()
  return status === 'placed'
})
const canCompleteOrder = computed(() => !!order.value && canCancelByRole.value && canCompleteByStatus.value)

const creatorFallback = ref('—')

const formatMoney = (amount: number | string) => {
  const num = typeof amount === 'string' ? parseFloat(amount) : amount
  return num.toFixed(2)
}

const formatDateTime = (value: string | null | undefined) => {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString()
}

const loadOrder = async () => {
  const orderId = route.params.id
  if (!orderId) {
    error.value = 'Order ID is missing'
    return
  }

  isLoading.value = true
  error.value = null

  try {
    const response = await api.get(`/orders/${orderId}`)
    const item = response.data?.data?.item

    if (item) {
      order.value = item as Order
      creatorFallback.value = item?.user_id ? `User #${item.user_id}` : '—'
    } else {
      error.value = 'Order not found'
    }
  } catch (e: any) {
    const message = e?.response?.data?.error || e?.message || 'Failed to load order'
    error.value = message
  } finally {
    isLoading.value = false
  }
}

const openInvoiceView = () => {
  if (!order.value) return
  router.push({ name: 'invoice-details', params: { id: order.value.id } })
}

const openInvoicePrint = () => {
  if (!order.value) return
  const target = router.resolve({
    name: 'invoice-details',
    params: { id: order.value.id },
    query: { print: '1' },
  })
  window.open(target.href, '_blank', 'noopener')
}

const openInvoicePdf = async () => {
  if (!order.value) return

  const response = await api.get(`/invoices/${order.value.id}/pdf`, {
    responseType: 'blob',
  })

  const blob = new Blob([response.data], { type: 'application/pdf' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `INV-${String(order.value.id).padStart(4, '0')}.pdf`
  link.click()
  URL.revokeObjectURL(url)
}

const cancelOrder = async () => {
  if (!order.value || !canCancelOrder.value || isCancelling.value) return

  isCancelling.value = true
  try {
    await api.post(`/orders/${order.value.id}/cancel`)
    notificationStore.success('Order cancelled successfully')
    await loadOrder()
  } catch (e: any) {
    const message = e?.response?.data?.error || e?.response?.data?.message || e?.message || 'Failed to cancel order'
    notificationStore.error(String(message))
  } finally {
    isCancelling.value = false
  }
}

const completeOrder = async () => {
  if (!order.value || !canCompleteOrder.value || isCompleting.value) return

  isCompleting.value = true
  try {
    await api.post(`/orders/${order.value.id}/complete`)
    notificationStore.success('Order completed successfully')
    await loadOrder()
  } catch (e: any) {
    const message = e?.response?.data?.error || e?.response?.data?.message || e?.message || 'Failed to complete order'
    notificationStore.error(String(message))
  } finally {
    isCompleting.value = false
  }
}

onMounted(() => {
  loadOrder()
})
</script>
