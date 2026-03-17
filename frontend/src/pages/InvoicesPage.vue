<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Invoices</h1>
      <p class="text-gray-600 dark:text-gray-400 mt-1">Order-based invoices ready for viewing, printing, and download.</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Invoice Register</h2>
        <button
          @click="loadInvoices"
          class="px-4 py-2 text-sm bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition"
        >
          Refresh
        </button>
      </div>

      <div v-if="isLoading" class="p-6 text-gray-600 dark:text-gray-400">Loading invoices...</div>
      <div v-else-if="error" class="p-6 text-red-600 dark:text-red-400">{{ error }}</div>

      <div v-else-if="invoices.length === 0" class="p-6 text-gray-600 dark:text-gray-400">No invoices found.</div>

      <div v-else class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Invoice ID</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Order</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Branch</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Created By</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Date</th>
              <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white">Grand Total</th>
              <th class="px-6 py-3 text-center text-xs font-semibold text-gray-900 dark:text-white">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="invoice in invoices" :key="invoice.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
              <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ invoiceNo(invoice) }}</td>
              <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">#{{ invoice.id }}</td>
              <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ invoice.branch_name || '—' }}</td>
              <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ invoice.creator_name || creatorFallback(invoice.user_id) }}</td>
              <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ formatDateTime(invoice.created_at) }}</td>
              <td class="px-6 py-4 text-sm text-right font-semibold text-gray-900 dark:text-white">${{ formatMoney(invoice.grand_total) }}</td>
              <td class="px-6 py-4 text-sm text-center space-x-3">
                <router-link
                  :to="{ name: 'invoice-details', params: { id: invoice.id } }"
                  class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 font-medium"
                >
                  View
                </router-link>
                <button
                  @click="openInvoiceAction(invoice.id, 'print')"
                  class="text-green-600 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300 font-medium"
                >
                  Print
                </button>
                <button
                  @click="downloadInvoicePdf(invoice.id, invoiceNo(invoice))"
                  class="text-purple-600 hover:text-purple-700 dark:text-purple-400 dark:hover:text-purple-300 font-medium"
                >
                  PDF
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '@/store/notifications'
import api from '@/services/api'

interface InvoiceRow {
  id: number
  invoice_id?: string
  branch_name: string | null
  user_id: number | null
  creator_name: string | null
  status: string
  created_at: string | null
  grand_total: number
}

const router = useRouter()
const notificationStore = useNotificationStore()

const isLoading = ref(false)
const error = ref<string | null>(null)
const invoices = ref<InvoiceRow[]>([])

const toNumber = (value: unknown): number => {
  if (typeof value === 'number') return value
  if (typeof value === 'string' && value.trim() !== '' && !Number.isNaN(Number(value))) return Number(value)
  return 0
}

const invoiceNo = (invoice: InvoiceRow) => invoice.invoice_id || `INV-${String(invoice.id).padStart(4, '0')}`

const formatMoney = (amount: number | string) => {
  const num = typeof amount === 'string' ? parseFloat(amount) : amount
  return Number.isFinite(num) ? num.toFixed(2) : '0.00'
}

const formatDateTime = (value: string | null | undefined) => {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString()
}

const creatorFallback = (userId: number | null) => (userId ? `User #${userId}` : '—')

const loadInvoices = async () => {
  isLoading.value = true
  error.value = null

  try {
    const response = await api.get('/invoices')
    const items = Array.isArray(response.data?.data?.items) ? response.data.data.items : []

    invoices.value = items.map((row: any) => ({
      id: Number(row.id),
      invoice_id: row.invoice_id ?? undefined,
      branch_name: row.branch_name ?? null,
      user_id: row.user_id ?? null,
      creator_name: row.creator_name ?? null,
      status: String(row.status ?? 'draft'),
      created_at: row.created_at ?? null,
      grand_total: toNumber(row.grand_total),
    }))
  } catch (e: any) {
    error.value = e?.response?.data?.error || e?.message || 'Failed to load invoices.'
    notificationStore.error(error.value || 'Failed to load invoices.')
  } finally {
    isLoading.value = false
  }
}

const openInvoiceAction = (orderId: number, action: 'print' | 'pdf') => {
  const target = router.resolve({
    name: 'invoice-details',
    params: { id: orderId },
    query: { [action]: '1' },
  })
  window.open(target.href, '_blank', 'noopener')
}

const downloadInvoicePdf = async (orderId: number, invoiceId: string) => {
  const response = await api.get(`/invoices/${orderId}/pdf`, {
    responseType: 'blob',
  })

  const blob = new Blob([response.data], { type: 'application/pdf' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `${invoiceId}.pdf`
  link.click()
  URL.revokeObjectURL(url)
}

onMounted(() => {
  loadInvoices()
})
</script>
