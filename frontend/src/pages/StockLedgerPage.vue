<template>
  <div class="space-y-6">
    <div v-if="authStore.isManager" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
      <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
        Branch: {{ managerBranchName }}
      </span>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Stock Ledger (Inventory Audit Trail)</h2>
          <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Read-only audit trail of every inventory movement.
          </p>
        </div>
        <router-link
          to="/inventory"
          class="inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition"
        >
          Back to Inventory
        </router-link>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 mt-6">
        <div v-if="canViewAllBranches">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Branch</label>
          <select
            v-model="filters.branchId"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">All Branches</option>
            <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option>
          </select>
        </div>

        <div v-if="canViewProductsFilter">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Product</label>
          <select
            v-model="filters.productId"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">All Products</option>
            <option v-for="product in products" :key="product.id" :value="String(product.id)">{{ product.name }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date From</label>
          <input
            v-model="filters.dateFrom"
            type="date"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date To</label>
          <input
            v-model="filters.dateTo"
            type="date"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div class="flex items-end gap-2">
          <button
            @click="applyFilters"
            class="flex-1 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition font-medium"
          >
            Apply
          </button>
          <button
            @click="resetFilters"
            class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-100 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition font-medium"
          >
            Reset
          </button>
          <button
            @click="exportCsv"
            :disabled="isExporting"
            class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-300 text-white rounded-lg transition font-medium"
          >
            {{ isExporting ? 'Exporting...' : 'Export CSV' }}
          </button>
        </div>
      </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <div class="p-6 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Ledger Entries</h3>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full min-w-[1100px]">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Date</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Branch</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Product</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">SKU Code</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Movement Type</th>
              <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white">Quantity</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Reference Type</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Reference ID</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">User</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="isLoading">
              <td colspan="9" class="px-6 py-6 text-sm text-gray-600 dark:text-gray-400">Loading ledger...</td>
            </tr>
            <tr v-else-if="error">
              <td colspan="9" class="px-6 py-6 text-sm text-red-600 dark:text-red-400">{{ error }}</td>
            </tr>
            <tr v-else-if="ledgerItems.length === 0">
              <td colspan="9" class="px-6 py-6 text-sm text-gray-600 dark:text-gray-400">No stock movement records found.</td>
            </tr>
            <tr v-for="item in ledgerItems" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
              <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ formatDateTime(item.created_at) }}</td>
              <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ item.branch_name || '—' }}</td>
              <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ item.product_name || '—' }}</td>
              <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ item.sku_code || '—' }}</td>
              <td class="px-6 py-4 text-sm">
                <span
                  class="inline-block px-2 py-1 text-xs font-semibold rounded-full"
                  :class="movementBadgeClass(item.movement_group)"
                >
                  {{ item.movement_group }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-right font-semibold" :class="item.quantity >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                {{ formatQuantity(item.quantity) }}
              </td>
              <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ item.reference_label || '—' }}</td>
              <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ item.reference_id ?? '—' }}</td>
              <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ item.user_name || 'System' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination
        :current-page="currentPage"
        :total-pages="totalPages"
        :total-items="totalItems"
        :items-per-page="itemsPerPage"
        @update:current-page="handlePageChange"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import api from '@/services/api'
import Pagination from '@/components/Pagination.vue'
import { useNotificationStore } from '@/store/notifications'
import { useAuthStore } from '@/store/auth'

interface Branch {
  id: number
  name: string
}

interface Product {
  id: number
  name: string
}

interface StockLedgerItem {
  id: number
  created_at: string | null
  branch_name: string | null
  product_name: string | null
  sku_code: string | null
  movement_group: 'IN' | 'OUT' | 'TRANSFER' | 'ADJUSTMENT' | string
  quantity: number
  reference_label: string | null
  reference_id: number | null
  user_name: string | null
}

const notificationStore = useNotificationStore()
const authStore = useAuthStore()
const canViewAllBranches = computed(() => authStore.isAdmin)
const canViewProductsFilter = computed(() => authStore.isAdmin)

const isLoading = ref(false)
const isExporting = ref(false)
const error = ref<string | null>(null)

const ledgerItems = ref<StockLedgerItem[]>([])
const branches = ref<Branch[]>([])
const products = ref<Product[]>([])

const currentPage = ref(1)
const itemsPerPage = 20
const totalItems = ref(0)

const filters = reactive({
  branchId: '',
  productId: '',
  dateFrom: '',
  dateTo: '',
})

const managerBranchName = computed(() => {
  const fromRows = ledgerItems.value.find((row) => row.branch_name)?.branch_name
  if (fromRows && fromRows.trim() !== '') return fromRows
  return authStore.user?.branch_id ? `Branch #${authStore.user.branch_id}` : 'Assigned Branch'
})

const totalPages = computed(() => {
  return Math.max(1, Math.ceil(totalItems.value / itemsPerPage))
})

const toNumber = (value: unknown): number => {
  if (typeof value === 'number') return value
  if (typeof value === 'string' && value.trim() !== '' && !Number.isNaN(Number(value))) return Number(value)
  return 0
}

const getItems = <T,>(payload: any): T[] => {
  if (Array.isArray(payload?.data?.items)) return payload.data.items as T[]
  if (Array.isArray(payload?.items)) return payload.items as T[]
  if (Array.isArray(payload?.data)) return payload.data as T[]
  return []
}

const movementBadgeClass = (movement: string) => {
  if (movement === 'IN') return 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300'
  if (movement === 'OUT') return 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300'
  if (movement === 'TRANSFER') return 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'
  if (movement === 'ADJUSTMENT') return 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300'
  return 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
}

const formatQuantity = (qty: number) => {
  return qty > 0 ? `+${qty}` : `${qty}`
}

const formatDateTime = (value: string | null) => {
  if (!value) return '—'
  const dt = new Date(value)
  if (Number.isNaN(dt.getTime())) return value
  return dt.toLocaleString()
}

const loadFilterData = async () => {
  if (!canViewAllBranches.value) {
    branches.value = []
    products.value = []
    filters.branchId = ''
    filters.productId = ''
    return
  }

  const [branchesRes, productsRes] = await Promise.all([
    api.get('/branches'),
    api.get('/products'),
  ])

  branches.value = getItems<Branch>(branchesRes.data)
  products.value = getItems<Product>(productsRes.data)
}

const loadLedger = async () => {
  isLoading.value = true
  error.value = null

  try {
    const params: Record<string, string | number> = {
      page: currentPage.value,
      per_page: itemsPerPage,
    }

    if (canViewAllBranches.value && filters.branchId) params.branch_id = filters.branchId
    if (canViewProductsFilter.value && filters.productId) params.product_id = filters.productId
    if (filters.dateFrom) params.date_from = filters.dateFrom
    if (filters.dateTo) params.date_to = filters.dateTo

    const response = await api.get('/inventory/ledger', { params })
    const payload = response.data?.data || {}

    const rows = Array.isArray(payload.items) ? payload.items : []
    ledgerItems.value = rows.map((row: any) => ({
      id: toNumber(row.id),
      created_at: row.created_at ?? null,
      branch_name: row.branch_name ?? null,
      product_name: row.product_name ?? null,
      sku_code: row.sku_code ?? null,
      movement_group: String(row.movement_group || 'IN'),
      quantity: toNumber(row.quantity),
      reference_label: row.reference_label ?? null,
      reference_id: row.reference_id === null || row.reference_id === undefined ? null : toNumber(row.reference_id),
      user_name: row.user_name ?? null,
    }))

    totalItems.value = toNumber(payload.total)
  } catch (e: any) {
    const message = e?.response?.data?.error || e?.message || 'Failed to load stock ledger.'
    error.value = message
    notificationStore.error(message)
  } finally {
    isLoading.value = false
  }
}

const applyFilters = () => {
  currentPage.value = 1
  loadLedger()
}

const resetFilters = () => {
  if (canViewAllBranches.value) {
    filters.branchId = ''
    filters.productId = ''
  }
  filters.dateFrom = ''
  filters.dateTo = ''
  currentPage.value = 1
  loadLedger()
}

const handlePageChange = (page: number) => {
  currentPage.value = page
  loadLedger()
}

const extractFilename = (contentDisposition?: string): string => {
  if (!contentDisposition) return `stock-ledger-${new Date().toISOString().slice(0, 10)}.csv`

  const match = contentDisposition.match(/filename="?([^\"]+)"?/i)
  if (!match || !match[1]) return `stock-ledger-${new Date().toISOString().slice(0, 10)}.csv`
  return match[1]
}

const exportCsv = async () => {
  isExporting.value = true

  try {
    const params: Record<string, string> = {}
    if (filters.branchId) params.branch_id = filters.branchId
    if (filters.productId) params.product_id = filters.productId
    if (filters.dateFrom) params.date_from = filters.dateFrom
    if (filters.dateTo) params.date_to = filters.dateTo

    const response = await api.get('/inventory/ledger/export', {
      params,
      responseType: 'blob',
    })

    const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    const filename = extractFilename(response.headers?.['content-disposition'] as string | undefined)

    link.href = url
    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)

    notificationStore.success('Stock ledger CSV exported successfully.')
  } catch (e: any) {
    notificationStore.error(e?.response?.data?.error || e?.message || 'Failed to export stock ledger CSV.')
  } finally {
    isExporting.value = false
  }
}

onMounted(async () => {
  try {
    await loadFilterData()
  } catch (e: any) {
    notificationStore.error(e?.response?.data?.error || e?.message || 'Failed to load ledger filters.')
  }

  await loadLedger()
})
</script>
