<template>
  <div class="space-y-6">
    <div v-if="isManager" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
      <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
        Branch: {{ managerBranchName }}
      </span>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Branch Filter -->
        <div v-if="canViewAllBranches">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Filter by Branch
          </label>
          <select
            v-model="selectedBranch"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">All Branches</option>
            <option v-for="b in branches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
          </select>
        </div>

        <!-- SKU Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Search by SKU
          </label>
          <input
            v-model="searchSku"
            type="text"
            placeholder="SKU code..."
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <!-- Action Buttons -->
        <div class="flex items-end gap-2">
          <button
            v-if="canManageStock"
            @click="openStockActionModal('add')"
            class="flex-1 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition font-medium text-sm"
          >
            + Add Stock
          </button>
          <button
            v-if="authStore.isAdmin"
            @click="openStockActionModal('transfer')"
            class="flex-1 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition font-medium text-sm"
          >
            ↔ Transfer
          </button>
        </div>
      </div>
    </div>

    <!-- Inventory Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <div class="p-6 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Inventory Balances</h2>
          <router-link
            to="/stock-ledger"
            class="px-3 py-2 text-sm font-medium rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
          >
            View Stock Ledger
          </router-link>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">SKU Code</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Product</th>
              <th v-if="!isManager" class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Branch</th>
              <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white">On Hand</th>
              <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white">Reserved</th>
              <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white">Available</th>
              <th class="px-6 py-3 text-center text-xs font-semibold text-gray-900 dark:text-white">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="isLoading">
              <td :colspan="isManager ? 6 : 7" class="px-6 py-6 text-sm text-gray-600 dark:text-gray-400">Loading…</td>
            </tr>
            <tr v-else-if="error">
              <td :colspan="isManager ? 6 : 7" class="px-6 py-6 text-sm text-red-600 dark:text-red-400">{{ error }}</td>
            </tr>
            <tr v-else-if="paginatedInventory.length === 0">
              <td :colspan="isManager ? 6 : 7" class="px-6 py-6 text-sm text-gray-600 dark:text-gray-400">No inventory found.</td>
            </tr>
            <tr v-for="item in paginatedInventory" :key="`${item.sku_id}-${item.branch_id}`" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
              <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ item.sku_code }}</td>
              <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ item.product_name || '—' }}</td>
              <td v-if="!isManager" class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ item.branch_name }}</td>
              <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white font-medium">{{ item.qty_on_hand }}</td>
              <td class="px-6 py-4 text-sm text-right text-yellow-600 dark:text-yellow-400 font-medium">{{ item.qty_reserved }}</td>
              <td class="px-6 py-4 text-sm text-right font-semibold" :class="item.qty_on_hand - item.qty_reserved >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                {{ item.qty_on_hand - item.qty_reserved }}
              </td>
              <td class="px-6 py-4 text-sm text-center">
                <div class="flex justify-center gap-2">
                  <button
                    v-if="canManageStock"
                    @click="openStockActionModal('adjust', item)"
                    class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 text-xs font-medium"
                  >
                    Adjust
                  </button>
                  <button
                    v-if="authStore.isAdmin"
                    @click="openStockActionModal('transfer', item)"
                    class="text-green-500 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300 text-xs font-medium"
                  >
                    Transfer
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination
        :current-page="currentPage"
        :total-pages="totalPages"
        :total-items="filteredInventory.length"
        :items-per-page="itemsPerPage"
        @update:current-page="currentPage = $event"
      />
    </div>

    <!-- Stock Action Modal -->
    <Modal v-model="showStockActionModal">
      <template #header>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ getModalTitle() }}</h2>
      </template>

      <form @submit.prevent="submitStockAction" class="space-y-4">
        <!-- Direction selector for Adjust (when selected item exists) -->
        <div v-if="selectedInventoryItem">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Action Direction</label>
          <div class="flex gap-2">
            <button
              type="button"
              @click="() => { stockAction.type = 'add' as any }"
              :class="stockAction.type === 'add' ? 'bg-green-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
              class="flex-1 px-4 py-2 rounded-lg transition font-medium"
            >
              ➕ Add Stock
            </button>
            <button
              type="button"
              @click="() => { stockAction.type = 'remove' as any }"
              :class="stockAction.type === 'add' ? 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300' : 'bg-red-500 text-white'"
              class="flex-1 px-4 py-2 rounded-lg transition font-medium"
            >
              ➖ Remove Stock
            </button>
          </div>
        </div>

        <!-- Action Type (hidden for row-specific actions) -->
        <div v-else-if="!selectedInventoryItem">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Action Type</label>
          <select
            v-model="stockAction.type"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select Action</option>
            <option value="add">Add Stock</option>
            <option value="remove">Remove Stock</option>
            <option value="transfer">Transfer Stock</option>
          </select>
        </div>

        <!-- From Branch -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ stockAction.type === 'transfer' ? 'From Branch' : 'Branch' }}
          </label>
          <select
            v-if="!isManager"
            v-model="stockAction.branch_id"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select Branch</option>
            <option v-for="b in branches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
          </select>
          <div v-else class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white">
            {{ managerBranchName }}
          </div>
        </div>

        <!-- To Branch (for transfer) -->
        <div v-if="stockAction.type === 'transfer'">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To Branch</label>
          <select
            v-model="stockAction.to_branch_id"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select Branch</option>
            <option v-for="b in branches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
          </select>
        </div>

        <!-- Product / SKU -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Product</label>
          <select
            v-model="stockAction.sku_id"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select product</option>
            <option v-for="s in availableSkus" :key="s.sku_id" :value="String(s.sku_id)">
              {{ s.product_name || 'Unknown Product' }} - {{ s.sku_code || `SKU #${s.sku_id}` }}
            </option>
          </select>
        </div>

        <!-- Quantity -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quantity</label>
          <input
            v-model.number="stockAction.quantity"
            type="number"
            :min="stockAction.type === 'remove' ? -999999 : 1"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <p v-if="selectedInventoryItem && stockAction.type !== 'transfer'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Current on-hand: {{ selectedInventoryItem.qty_on_hand }}, Available: {{ selectedInventoryItem.qty_on_hand - selectedInventoryItem.qty_reserved }}
          </p>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes (Optional)</label>
          <textarea
            v-model="stockAction.notes"
            rows="2"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          ></textarea>
        </div>
      </form>

      <template #footer>
        <button
          @click="showStockActionModal = false"
          class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg transition"
        >
          Cancel
        </button>
        <button
          @click="submitStockAction"
          :disabled="isSubmitting"
          class="ml-3 px-4 py-2 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white rounded-lg transition font-medium"
        >
          {{ isSubmitting ? 'Processing…' : 'Execute Action' }}
        </button>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import api from '@/services/api'
import { useNotificationStore } from '@/store/notifications'
import { useAuthStore } from '@/store/auth'
import Modal from '@/components/Modal.vue'
import Pagination from '@/components/Pagination.vue'

const selectedBranch = ref('')
const searchSku = ref('')
const showStockActionModal = ref(false)
const isSubmitting = ref(false)
const currentPage = ref(1)
const itemsPerPage = 10

const notificationStore = useNotificationStore()
const authStore = useAuthStore()
const isLoading = ref(false)
const error = ref<string | null>(null)
const canViewAllBranches = computed(() => authStore.isAdmin)
const isManager = computed(() => authStore.isManager)
const canManageStock = computed(() => authStore.isAdmin || authStore.isManager)

interface Branch {
  id: number
  name: string
}

interface InventoryRow {
  branch_id: number
  branch_name: string | null
  sku_id: number
  sku_code: string | null
  product_name?: string | null
  qty_on_hand: number
  qty_reserved: number
}

interface StockAction {
  type: 'add' | 'remove' | 'transfer' | ''
  branch_id: string
  to_branch_id: string
  sku_id: string
  quantity: number
  notes: string
}

const branches = ref<Branch[]>([])
const inventory = ref<InventoryRow[]>([])
const selectedInventoryItem = ref<InventoryRow | null>(null)
const managerBranchName = computed(() => {
  if (!isManager.value) return ''
  const fromData = inventory.value.find((row) => row.branch_name)?.branch_name
  if (fromData && fromData.trim() !== '') return fromData
  return authStore.user?.branch_id ? `Branch #${authStore.user.branch_id}` : 'Assigned Branch'
})

const stockAction = reactive<StockAction>({
  type: '',
  branch_id: '',
  to_branch_id: '',
  sku_id: '',
  quantity: 1,
  notes: '',
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

const filteredInventory = computed(() => {
  const branchId = canViewAllBranches.value && selectedBranch.value ? Number(selectedBranch.value) : null
  const q = searchSku.value.trim().toLowerCase()

  return inventory.value.filter((row) => {
    const branchOk = branchId === null ? true : row.branch_id === branchId
    const sku = (row.sku_code || '').toLowerCase()
    const product = (row.product_name || '').toLowerCase()
    const skuOk = q === '' ? true : sku.includes(q) || product.includes(q)
    return branchOk && skuOk
  })
})

const totalPages = computed(() => Math.ceil(filteredInventory.value.length / itemsPerPage))

const paginatedInventory = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredInventory.value.slice(start, end)
})

const availableSkus = computed(() => {
  const map = new Map<number, { sku_id: number; sku_code: string | null; product_name: string | null }>()
  for (const row of inventory.value) {
    if (!row.sku_id) continue
    if (!map.has(row.sku_id)) {
      map.set(row.sku_id, {
        sku_id: row.sku_id,
        sku_code: row.sku_code,
        product_name: row.product_name ?? null,
      })
    }
  }
  return Array.from(map.values()).sort((a, b) => a.sku_id - b.sku_id)
})

const getModalTitle = () => {
  const titles: Record<string, string> = {
    add: 'Add Stock',
    remove: 'Remove Stock',
    transfer: 'Transfer Stock Between Branches',
  }
  return titles[stockAction.type] || 'Stock Action'
}

const openStockActionModal = (actionType: 'add' | 'remove' | 'transfer' | 'adjust', item?: InventoryRow) => {
  // Reset form
  // For adjust, default to 'add' (user can enter negative for removal)
  stockAction.type = (actionType === 'adjust' ? 'add' : actionType) as 'add' | 'remove' | 'transfer'
  stockAction.branch_id = isManager.value
    ? String(authStore.user?.branch_id || item?.branch_id || '')
    : (item?.branch_id.toString() || '')
  stockAction.to_branch_id = ''
  stockAction.sku_id = item?.sku_id.toString() || ''
  stockAction.quantity = 1
  stockAction.notes = ''

  selectedInventoryItem.value = item || null
  showStockActionModal.value = true
}

const submitStockAction = async () => {
  if (!authStore.isAdmin) return

  // Validation
  if (!stockAction.branch_id || !stockAction.sku_id || !stockAction.quantity || stockAction.quantity === 0) {
    notificationStore.error('Please fill in all required fields')
    return
  }

  if (stockAction.type === 'transfer') {
    if (!stockAction.to_branch_id) {
      notificationStore.error('Please select destination branch')
      return
    }
    if (stockAction.branch_id === stockAction.to_branch_id) {
      notificationStore.error('Source and destination branches must be different')
      return
    }
  }

  isSubmitting.value = true

  try {
    let endpoint = ''
    let payload: any = {}

    switch (stockAction.type) {
      case 'add':
        endpoint = '/inventory/receive'
        payload = {
          branch_id: Number(stockAction.branch_id),
          sku_id: Number(stockAction.sku_id),
          quantity: stockAction.quantity,
          notes: stockAction.notes.trim() || null,
        }
        break

      case 'remove':
        endpoint = '/inventory/adjust'
        payload = {
          branch_id: Number(stockAction.branch_id),
          sku_id: Number(stockAction.sku_id),
          quantity_delta: -Math.abs(stockAction.quantity),
          notes: stockAction.notes.trim() || null,
        }
        break

      case 'transfer':
        endpoint = '/inventory/transfer'
        payload = {
          from_branch_id: Number(stockAction.branch_id),
          to_branch_id: Number(stockAction.to_branch_id),
          sku_id: Number(stockAction.sku_id),
          quantity: stockAction.quantity,
          notes: stockAction.notes.trim() || null,
        }
        break

      default:
        notificationStore.error('Invalid action type')
        return
    }

    await api.post(endpoint, payload)
    notificationStore.success('Stock action completed successfully')
    showStockActionModal.value = false
    await loadInventory()
  } catch (e: any) {
    notificationStore.error(e?.response?.data?.error || e?.message || 'Failed to execute stock action')
  } finally {
    isSubmitting.value = false
  }
}

const loadInventory = async () => {
  isLoading.value = true
  error.value = null

  try {
    const invRes = await api.get('/inventories')

    if (canViewAllBranches.value) {
      const branchesRes = await api.get('/branches')
      branches.value = getItems<Branch>(branchesRes.data)
    } else {
      branches.value = []
      selectedBranch.value = ''
    }

    const rowsRaw = getItems<any>(invRes.data)
    inventory.value = rowsRaw.map((r) => ({
      branch_id: Number(r.branch_id),
      branch_name: r.branch_name ?? null,
      sku_id: Number(r.sku_id),
      sku_code: r.sku_code ?? null,
      product_name: r.product_name ?? null,
      qty_on_hand: toNumber(r.qty_on_hand),
      qty_reserved: toNumber(r.qty_reserved),
    }))
  } catch (e: any) {
    const message = e?.response?.data?.error || e?.message || 'Failed to load inventory.'
    error.value = message
    notificationStore.error(message)
  } finally {
    isLoading.value = false
  }
}

// Reset pagination when filters change
watch([selectedBranch, searchSku], () => {
  currentPage.value = 1
})

onMounted(() => {
  loadInventory()
})
</script>
