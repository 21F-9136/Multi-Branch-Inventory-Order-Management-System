<template>
  <div class="space-y-6">
    <div v-if="authStore.isManager" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
      <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
        Branch: {{ managerBranchName }}
      </span>
    </div>

    <!-- Create Order Section -->
    <div v-if="canCreateOrders" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Create New Order</h2>

      <form @submit.prevent="handleCreateOrder" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <!-- Branch -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Branch
            </label>
            <select
              v-model="formData.branch_id"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Select Branch</option>
              <option v-for="b in createBranchOptions" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
            </select>
          </div>

          <!-- Product -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Product
            </label>
            <select
              v-model="formData.product_id"
              @change="handleProductChange"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Select Product</option>
              <option v-for="p in products" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
            </select>
          </div>

          <!-- SKU -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              SKU
            </label>
            <select
              v-model="formData.sku_id"
              @change="handleSkuChange"
              :disabled="availableSkus.length === 0"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60"
            >
              <option value="">Select SKU</option>
              <option v-for="sku in availableSkus" :key="sku.sku_id" :value="String(sku.sku_id)">
                {{ sku.sku_code }} - {{ formatMoney(sku.price) }}
              </option>
            </select>
          </div>

          <!-- Quantity -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Quantity
            </label>
            <input
              v-model.number="formData.quantity"
              type="number"
              min="1"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>

        <div class="flex justify-end">
          <button
            type="button"
            @click="addDraftItem"
            class="px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg transition font-medium"
          >
            Add Item
          </button>
        </div>

        <div class="overflow-x-auto" v-if="draftItems.length > 0">
          <table class="w-full text-sm border border-gray-200 dark:border-gray-700 rounded-lg">
            <thead class="bg-gray-50 dark:bg-gray-900">
              <tr>
                <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Product</th>
                <th class="px-4 py-2 text-right text-gray-900 dark:text-white">Qty</th>
                <th class="px-4 py-2 text-right text-gray-900 dark:text-white">Unit Price</th>
                <th class="px-4 py-2 text-right text-gray-900 dark:text-white">Line Total</th>
                <th class="px-4 py-2 text-center text-gray-900 dark:text-white">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in draftItems" :key="item.sku_id" class="border-t border-gray-200 dark:border-gray-700">
                <td class="px-4 py-2 text-gray-900 dark:text-white">{{ item.product_name }} <span class="text-xs text-gray-500 dark:text-gray-400">({{ item.sku_code }})</span></td>
                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ item.quantity }}</td>
                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatMoney(item.unit_price) }}</td>
                <td class="px-4 py-2 text-right text-gray-900 dark:text-white font-medium">{{ formatMoney(item.line_total) }}</td>
                <td class="px-4 py-2 text-center">
                  <button
                    type="button"
                    @click="removeDraftItem(item.sku_id)"
                    class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 font-medium"
                  >
                    Remove
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Order Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
          <div>
            <p class="text-xs text-gray-600 dark:text-gray-400">Unit Price</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ formData.unit_price }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-600 dark:text-gray-400">Subtotal</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ calculateSubtotal() }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-600 dark:text-gray-400">Tax (10%)</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ calculateTax() }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-600 dark:text-gray-400">Total</p>
            <p class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ calculateTotal() }}</p>
          </div>
        </div>

        <button
          type="submit"
          :disabled="isLoading"
          class="w-full px-4 py-2 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white rounded-lg transition font-medium"
        >
          {{ isLoading ? 'Creating...' : 'Create Order' }}
        </button>
      </form>
    </div>

    <!-- Orders Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <div class="p-6 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Orders</h2>
      </div>

      <!-- Loading State -->
      <div v-if="isTableLoading" class="p-6 text-center text-gray-600 dark:text-gray-400">
        <p>Loading orders...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="tableError" class="p-6 text-center text-red-600 dark:text-red-400">
        <p>{{ tableError }}</p>
      </div>

      <!-- No Orders -->
      <div v-else-if="paginatedOrders.length === 0" class="p-6 text-center text-gray-600 dark:text-gray-400">
        <p>No orders found.</p>
      </div>

      <!-- Orders Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Order ID</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Branch</th>
              <th class="px-6 py-3 text-center text-xs font-semibold text-gray-900 dark:text-white">Items</th>
              <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white">Total</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Status</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Date</th>
              <th class="px-6 py-3 text-center text-xs font-semibold text-gray-900 dark:text-white">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="order in paginatedOrders" :key="order.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
              <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">#{{ order.id }}</td>
              <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ order.branch_name || '—' }}</td>
              <td class="px-6 py-4 text-sm text-center text-gray-600 dark:text-gray-400">{{ order.items?.length || 0 }}</td>
              <td class="px-6 py-4 text-sm text-right font-semibold text-gray-900 dark:text-white">${{ formatMoney(order.grand_total) }}</td>
              <td class="px-6 py-4 text-sm">
                <span
                  class="inline-block px-2 py-1 text-xs font-semibold rounded-full"
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
              </td>
              <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ formatDate(order.created_at) }}</td>
              <td class="px-6 py-4 text-sm text-center">
                <router-link
                  :to="{ name: 'order-details', params: { id: order.id } }"
                  class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 font-medium transition-colors"
                >
                  View
                </router-link>
                <button
                  v-if="canPlaceOrders && order.status !== 'placed'"
                  @click="placeOrder(order.id)"
                  class="ml-3 text-green-600 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300 font-medium transition-colors"
                >
                  Place
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination
        :current-page="currentPage"
        :total-pages="totalPages"
        :total-items="orders.length"
        :items-per-page="itemsPerPage"
        @update:current-page="currentPage = $event"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import Pagination from '@/components/Pagination.vue'
import { useAuthStore } from '@/store/auth'
import { useNotificationStore } from '@/store/notifications'
import api from '@/services/api'

const authStore = useAuthStore()
const notificationStore = useNotificationStore()
const isLoading = ref(false)
const isTableLoading = ref(false)
const tableError = ref<string | null>(null)
const currentPage = ref(1)
const itemsPerPage = 10

// Super Admin must not create orders.
// Keep order creation limited to sales for now.
const canCreateOrders = computed(() => authStore.isSales || authStore.isManager)
const canPlaceOrders = computed(() => authStore.isAdmin || authStore.isManager || authStore.isSales)

interface Branch {
  id: number
  name: string
}

interface Product {
  id: number
  name: string
  sku?: string | null
  sale_price?: number | null
}

interface ProductSku {
  sku_id: number
  sku_code: string
  price: number
}

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

interface OrderRow {
  id: number
  branch_id: number
  branch_name: string | null
  user_id: number | null
  status: string
  total_amount: number
  subtotal: number
  tax_amount: number
  grand_total: number
  placed_at: string | null
  created_at: string | null
  items: OrderItem[]
}

const formData = reactive({
  branch_id: '',
  product_id: '',
  sku_id: '',
  quantity: 1,
  unit_price: 0,
  lines: [],
})

interface DraftOrderItem {
  sku_id: number
  sku_code: string
  product_id: number
  product_name: string
  quantity: number
  unit_price: number
  line_total: number
}

const branches = ref<Branch[]>([])
const products = ref<Product[]>([])
const orders = ref<OrderRow[]>([])
const draftItems = ref<DraftOrderItem[]>([])
const availableSkus = ref<ProductSku[]>([])

const managerBranchName = computed(() => {
  const fromRows = orders.value.find((row) => row.branch_name)?.branch_name
  if (fromRows && fromRows.trim() !== '') return fromRows
  return authStore.user?.branch_id ? `Branch #${authStore.user.branch_id}` : 'Assigned Branch'
})

const createBranchOptions = computed(() => {
  const options = [...branches.value]
  const ownId = authStore.user?.branch_id

  if (ownId && !options.some((b) => b.id === ownId)) {
    options.push({ id: ownId, name: managerBranchName.value })
  }

  return options
})

const totalPages = computed(() => Math.ceil(orders.value.length / itemsPerPage))

const paginatedOrders = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return orders.value.slice(start, end)
})

const getItems = <T,>(payload: any): T[] => {
  if (Array.isArray(payload?.data?.items)) return payload.data.items as T[]
  if (Array.isArray(payload?.items)) return payload.items as T[]
  if (Array.isArray(payload?.data)) return payload.data as T[]
  return []
}

const toNumber = (value: unknown): number => {
  if (typeof value === 'number') return value
  if (typeof value === 'string' && value.trim() !== '' && !Number.isNaN(Number(value))) return Number(value)
  return 0
}

const formatMoney = (amount: number | string) => {
  const num = typeof amount === 'string' ? parseFloat(amount) : amount
  return num.toFixed(2)
}

const formatDate = (value: string | null | undefined) => {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleDateString()
}

const handleProductChange = async () => {
  formData.sku_id = ''
  formData.unit_price = 0
  availableSkus.value = []

  const productId = Number(formData.product_id)
  if (!productId) {
    return
  }

  try {
    const response = await api.get(`/products/${productId}/skus`)
    const rows = getItems<any>(response.data)
    availableSkus.value = rows.map((row) => ({
      sku_id: Number(row.id ?? row.sku_id),
      sku_code: String(row.sku_code ?? ''),
      price: row.price !== undefined && row.price !== null
        ? toNumber(row.price)
        : (row.sale_price !== undefined && row.sale_price !== null
          ? toNumber(row.sale_price)
          : toNumber(row.unit_price)),
    })).filter((row) => row.sku_id > 0)

    if (availableSkus.value.length > 0) {
      formData.sku_id = String(availableSkus.value[0].sku_id)
      formData.unit_price = availableSkus.value[0].price
    }
  } catch {
    notificationStore.error('Failed to load SKU pricing for selected product.')
  }
}

const handleSkuChange = () => {
  const sku = availableSkus.value.find((item) => item.sku_id === Number(formData.sku_id))
  formData.unit_price = sku ? sku.price : 0
}

const calculateSubtotal = () => {
  const subtotal = draftItems.value.reduce((sum, item) => sum + item.line_total, 0)
  return subtotal.toFixed(2)
}

const calculateTax = () => {
  const subtotal = parseFloat(calculateSubtotal())
  return (subtotal * 0.1).toFixed(2)
}

const calculateTotal = () => {
  const subtotal = parseFloat(calculateSubtotal())
  const tax = parseFloat(calculateTax())
  return (subtotal + tax).toFixed(2)
}

const loadOrdersPageData = async () => {
  tableError.value = null
  isTableLoading.value = true

  try {
    const ordersResPromise = api.get('/orders')
    const branchesResPromise = canCreateOrders.value ? api.get('/branches').catch(() => null) : Promise.resolve(null)
    const productsResPromise = canCreateOrders.value ? api.get('/products') : Promise.resolve(null)

    const [branchesRes, productsRes, ordersRes] = await Promise.all([
      branchesResPromise,
      productsResPromise,
      ordersResPromise,
    ])

    branches.value = branchesRes ? getItems<Branch>(branchesRes.data) : []

    if (!formData.branch_id && authStore.user?.branch_id) {
      formData.branch_id = String(authStore.user.branch_id)
    }

    if (!formData.branch_id && branches.value.length > 0) {
      formData.branch_id = String(branches.value[0].id)
    }
    products.value = productsRes
      ? getItems<any>(productsRes.data).map((p) => ({
          id: Number(p.id),
          name: String(p.name ?? ''),
          sku: p.sku ?? null,
          sale_price: p.sale_price !== undefined && p.sale_price !== null ? toNumber(p.sale_price) : null,
        }))
      : []

    const ordersRaw = getItems<any>(ordersRes.data)
    orders.value = ordersRaw.map((o) => ({
      id: Number(o.id),
      branch_id: Number(o.branch_id),
      branch_name: o.branch_name ?? null,
      user_id: o.user_id ?? null,
      status: String(o.status ?? 'draft'),
      total_amount: toNumber(o.total_amount),
      subtotal: toNumber(o.subtotal ?? o.total_amount),
      tax_amount: toNumber(o.tax_amount ?? 0),
      grand_total: toNumber(o.grand_total ?? o.total_amount),
      placed_at: o.placed_at ?? null,
      created_at: o.created_at ?? null,
      items: Array.isArray(o.items) ? o.items : [],
    }))
  } catch (e: any) {
    const message = e?.response?.data?.error || e?.message || 'Failed to load orders.'
    tableError.value = message
    notificationStore.error(message)
  } finally {
    isTableLoading.value = false
  }
}

const addDraftItem = () => {
  const productId = Number(formData.product_id)
  const skuId = Number(formData.sku_id)
  const qty = Number(formData.quantity)

  if (!productId || !skuId || !qty || qty <= 0) {
    notificationStore.error('Select product, SKU and quantity before adding item.')
    return
  }

  const product = products.value.find((p) => p.id === productId)
  if (!product) {
    notificationStore.error('Selected product is invalid.')
    return
  }

  const sku = availableSkus.value.find((item) => item.sku_id === skuId)
  if (!sku) {
    notificationStore.error('Selected SKU is invalid.')
    return
  }

  const unitPrice = sku.price

  const existing = draftItems.value.find((item) => item.sku_id === skuId)
  if (existing) {
    existing.quantity += qty
    existing.line_total = existing.quantity * existing.unit_price
  } else {
    draftItems.value.push({
      sku_id: skuId,
      sku_code: sku.sku_code,
      product_id: productId,
      product_name: product.name,
      quantity: qty,
      unit_price: unitPrice,
      line_total: unitPrice * qty,
    })
  }

  formData.product_id = ''
  formData.sku_id = ''
  formData.quantity = 1
  formData.unit_price = 0
  availableSkus.value = []
}

const removeDraftItem = (skuId: number) => {
  draftItems.value = draftItems.value.filter((item) => item.sku_id !== skuId)
}

const handleCreateOrder = async () => {
  const resolvedBranchId = formData.branch_id

  if (!resolvedBranchId) {
    notificationStore.error('Branch is required.')
    return
  }

  if (draftItems.value.length === 0) {
    notificationStore.error('Add at least one item before creating order.')
    return
  }

  isLoading.value = true

  try {
    const payload: Record<string, unknown> = {
      status: 'draft',
      branch_id: Number(resolvedBranchId),
      items: draftItems.value.map((item) => ({
        sku_id: item.sku_id,
        quantity: item.quantity,
      })),
    }

    await api.post('/orders', payload)

    notificationStore.success('Order created successfully')
    formData.product_id = ''
    formData.sku_id = ''
    formData.quantity = 1
    formData.unit_price = 0
    availableSkus.value = []
    draftItems.value = []
    await loadOrdersPageData()
  } catch (error: any) {
    notificationStore.error(error?.response?.data?.error || error?.message || 'Failed to create order')
  } finally {
    isLoading.value = false
  }
}

const placeOrder = async (orderId: number) => {
  try {
    await api.post('/orders/place', { order_id: orderId })
    notificationStore.success('Order placed successfully')
    await loadOrdersPageData()
  } catch (error: any) {
    notificationStore.error(error?.response?.data?.error || error?.message || 'Failed to place order')
  }
}

onMounted(() => {
  if (authStore.user?.branch_id) {
    formData.branch_id = String(authStore.user.branch_id)
  }
  loadOrdersPageData()
})
</script>
