<template>
  <div class="space-y-6 invoice-page">
    <div class="flex items-center justify-between no-print">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Invoice</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1" v-if="invoice">{{ invoiceNumber }}</p>
      </div>
      <div class="space-x-2">
        <router-link
          to="/invoices"
          class="px-4 py-2 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
        >
          Back to Invoices
        </router-link>
      </div>
    </div>

    <div v-if="isLoading" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-gray-600 dark:text-gray-400">
      Loading invoice...
    </div>

    <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 rounded-lg shadow p-6 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400">
      {{ error }}
    </div>

    <div v-else-if="invoice" class="invoice-sheet bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden border border-gray-200 dark:border-gray-700">
      <div class="p-6 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ invoiceNumber }}</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Order #{{ invoice.id }}</p>
          </div>
          <span
            class="inline-block px-3 py-1 text-xs font-semibold rounded-full"
            :class="invoice.status === 'placed' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
          >
            {{ invoice.status }}
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6 text-sm">
          <div>
            <p class="text-gray-500 dark:text-gray-400">Branch</p>
            <p class="font-semibold text-gray-900 dark:text-white">{{ invoice.branch_name || '—' }}</p>
          </div>
          <div>
            <p class="text-gray-500 dark:text-gray-400">Created By</p>
            <p class="font-semibold text-gray-900 dark:text-white">{{ invoice.creator_name || creatorFallback }}</p>
          </div>
          <div>
            <p class="text-gray-500 dark:text-gray-400">Date</p>
            <p class="font-semibold text-gray-900 dark:text-white">{{ formatDateTime(invoice.created_at) }}</p>
          </div>
          <div>
            <p class="text-gray-500 dark:text-gray-400">Invoice ID</p>
            <p class="font-semibold text-gray-900 dark:text-white">{{ invoiceNumber }}</p>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Product</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">SKU</th>
              <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white">Unit Price</th>
              <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white">Quantity</th>
              <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white">Line Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="item in invoice.items" :key="item.id">
              <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ item.product_name || item.sku_name || '—' }}</td>
              <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ item.sku_code || '—' }}</td>
              <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">${{ formatMoney(item.unit_price) }}</td>
              <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">{{ item.quantity }}</td>
              <td class="px-6 py-4 text-sm text-right font-semibold text-gray-900 dark:text-white">${{ formatMoney(item.line_total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="p-6 border-t border-gray-200 dark:border-gray-700">
        <div class="max-w-sm ml-auto space-y-3 text-sm">
          <div class="flex items-center justify-between">
            <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
            <span class="font-semibold text-gray-900 dark:text-white">${{ formatMoney(invoice.subtotal) }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-gray-600 dark:text-gray-400">Tax Amount</span>
            <span class="font-semibold text-gray-900 dark:text-white">${{ formatMoney(invoice.tax_amount) }}</span>
          </div>
          <div class="flex items-center justify-between text-base border-t border-gray-200 dark:border-gray-700 pt-3">
            <span class="font-bold text-gray-900 dark:text-white">Grand Total</span>
            <span class="font-bold text-blue-600 dark:text-blue-400">${{ formatMoney(invoice.grand_total) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="invoice" class="no-print flex items-center justify-end gap-2">
      <button
        @click="printInvoice"
        class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition"
      >
        Print Invoice
      </button>
      <button
        @click="downloadPdf"
        class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition"
      >
        Download PDF
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'

interface InvoiceItem {
  id: number
  sku_code: string | null
  sku_name: string | null
  product_name: string | null
  quantity: number
  unit_price: number
  line_total: number
}

interface Invoice {
  id: number
  invoice_id?: string
  branch_name: string | null
  creator_name: string | null
  user_id: number | null
  status: string
  created_at: string | null
  subtotal: number
  tax_amount: number
  grand_total: number
  items: InvoiceItem[]
}

const route = useRoute()

const isLoading = ref(false)
const error = ref<string | null>(null)
const invoice = ref<Invoice | null>(null)

const invoiceNumber = computed(() => {
  if (!invoice.value) return ''
  return invoice.value.invoice_id || `INV-${String(invoice.value.id).padStart(4, '0')}`
})

const creatorFallback = computed(() => {
  if (!invoice.value?.user_id) return '—'
  return `User #${invoice.value.user_id}`
})

const toNumber = (value: unknown): number => {
  if (typeof value === 'number') return value
  if (typeof value === 'string' && value.trim() !== '' && !Number.isNaN(Number(value))) return Number(value)
  return 0
}

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

const loadInvoice = async () => {
  const orderId = Number(route.params.id)
  if (!orderId) {
    error.value = 'Order ID is missing.'
    return
  }

  isLoading.value = true
  error.value = null

  try {
    const response = await api.get(`/invoices/${orderId}`)
    const item = response.data?.data?.item
    if (!item) {
      error.value = 'Invoice source order not found.'
      return
    }

    invoice.value = {
      id: Number(item.id),
      invoice_id: item.invoice_id ?? undefined,
      branch_name: item.branch_name ?? null,
      creator_name: item.creator_name ?? null,
      user_id: item.user_id ?? null,
      status: String(item.status ?? 'draft'),
      created_at: item.created_at ?? null,
      subtotal: toNumber(item.subtotal),
      tax_amount: toNumber(item.tax_amount),
      grand_total: toNumber(item.grand_total),
      items: Array.isArray(item.items)
        ? item.items.map((row: any) => ({
            id: Number(row.id),
            sku_code: row.sku_code ?? null,
            sku_name: row.sku_name ?? null,
            product_name: row.product_name ?? null,
            quantity: toNumber(row.quantity),
            unit_price: toNumber(row.unit_price),
            line_total: toNumber(row.line_total),
          }))
        : [],
    }
  } catch (e: any) {
    error.value = e?.response?.data?.error || e?.message || 'Failed to load invoice.'
  } finally {
    isLoading.value = false
  }
}

const printInvoice = () => {
  window.print()
}

const downloadPdf = async () => {
  if (!invoice.value) return

  const response = await api.get(`/invoices/${invoice.value.id}/pdf`, {
    responseType: 'blob',
  })

  const blob = new Blob([response.data], { type: 'application/pdf' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `${invoiceNumber.value}.pdf`
  link.click()
  URL.revokeObjectURL(url)
}

onMounted(async () => {
  await loadInvoice()

  const shouldPrint = route.query.print === '1'
  const shouldPdf = route.query.pdf === '1'

  if (!invoice.value) return

  if (shouldPdf) {
    downloadPdf()
  }

  if (shouldPrint) {
    await nextTick()
    setTimeout(() => {
      printInvoice()
    }, 150)
  }
})
</script>

<style scoped>
@media print {
  :global(aside),
  :global(.h-16.border-b),
  :global(.fixed.bottom-4.right-4),
  .no-print {
    display: none !important;
  }

  :global(main) {
    padding: 0 !important;
  }

  .invoice-page {
    padding: 0 !important;
  }

  .invoice-sheet {
    border: none !important;
    box-shadow: none !important;
  }
}
</style>
