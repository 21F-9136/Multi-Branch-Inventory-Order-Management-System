<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div class="flex-1">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search SKUs..."
          class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>
      <button
        v-if="authStore.isAdmin"
        @click="openCreateModal"
        class="ml-4 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition font-medium"
      >
        + Add SKU
      </button>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-900">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Product</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">SKU Code</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Barcode</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Name</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Unit Price</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <tr v-if="isLoading">
            <td colspan="6" class="px-6 py-6 text-sm text-gray-600 dark:text-gray-400">Loading…</td>
          </tr>
          <tr v-else-if="error">
            <td colspan="6" class="px-6 py-6 text-sm text-red-600 dark:text-red-400">{{ error }}</td>
          </tr>
          <tr v-else-if="filteredSkus.length === 0">
            <td colspan="6" class="px-6 py-6 text-sm text-gray-600 dark:text-gray-400">No SKUs found.</td>
          </tr>
          <tr v-for="sku in filteredSkus" :key="sku.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ sku.product_name }}</td>
            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ sku.sku_code }}</td>
            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ sku.barcode || '—' }}</td>
            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ sku.name || '—' }}</td>
            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ formatMoney(sku.unit_price) }}</td>
            <td class="px-6 py-4 text-sm">
              <button
                v-if="authStore.isAdmin"
                @click="editSku(sku)"
                class="text-blue-500 hover:text-blue-700 dark:hover:text-blue-400 mr-3"
              >
                Edit
              </button>
              <button
                v-if="authStore.isAdmin"
                @click="confirmDelete(sku)"
                class="text-red-500 hover:text-red-700 dark:hover:text-red-400"
              >
                Delete
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Modal v-model="showModal">
      <template #header>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ editingSkuId ? 'Edit SKU' : 'Create SKU' }}
        </h2>
      </template>

      <form @submit.prevent="saveSku" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Product</label>
          <select
            v-model.number="form.product_id"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option :value="0" disabled>Select a product…</option>
            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SKU Code</label>
          <input
            v-model="form.sku_code"
            type="text"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barcode</label>
          <input
            v-model="form.barcode"
            type="text"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
          <input
            v-model="form.name"
            type="text"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Unit Price</label>
          <input
            v-model.number="form.unit_price"
            type="number"
            step="0.01"
            min="0"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
      </form>

      <template #footer>
        <button
          @click="showModal = false"
          class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg transition"
        >
          Cancel
        </button>
        <button
          @click="saveSku"
          :disabled="isSaving"
          class="ml-3 px-4 py-2 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white rounded-lg transition"
        >
          {{ isSaving ? 'Saving...' : 'Save' }}
        </button>
      </template>
    </Modal>

    <ConfirmDialog
      v-model="showDeleteConfirm"
      title="Delete SKU?"
      message="This will remove the SKU from the system."
      icon="warning"
      :is-dangerous="true"
      yes-label="Delete"
      no-label="Cancel"
      @confirm="deleteSku"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import Modal from '@/components/Modal.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { useAuthStore } from '@/store/auth'
import { useNotificationStore } from '@/store/notifications'
import api from '@/services/api'

const authStore = useAuthStore()
const notificationStore = useNotificationStore()

const searchQuery = ref('')
const isLoading = ref(false)
const error = ref<string | null>(null)

interface SkuRow {
  id: number
  product_id: number
  product_name: string
  sku_code: string
  barcode: string | null
  name: string | null
  unit_price: number
}

interface ProductOption {
  id: number
  name: string
}

const skus = ref<SkuRow[]>([])
const products = ref<ProductOption[]>([])

const showModal = ref(false)
const isSaving = ref(false)
const editingSkuId = ref<number | null>(null)

const showDeleteConfirm = ref(false)
const deleteTargetId = ref<number | null>(null)

const form = reactive({
  product_id: 0,
  sku_code: '',
  barcode: '',
  name: '',
  unit_price: 0,
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

const formatMoney = (value: number) => {
  if (!Number.isFinite(value)) return '0.00'
  return value.toFixed(2)
}

const loadProducts = async () => {
  try {
    const res = await api.get('/products')
    const items = getItems<any>(res.data)
    products.value = items.map((p) => ({ id: Number(p.id), name: String(p.name ?? '') }))
  } catch {
    products.value = []
  }
}

const loadSkus = async () => {
  isLoading.value = true
  error.value = null
  try {
    const params: Record<string, string> = {}
    if (searchQuery.value.trim()) params.q = searchQuery.value.trim()

    const res = await api.get('/skus', { params })
    const items = getItems<any>(res.data)

    skus.value = items.map((s) => ({
      id: Number(s.id),
      product_id: Number(s.product_id),
      product_name: String(s.product_name ?? ''),
      sku_code: String(s.sku_code ?? ''),
      barcode: s.barcode ?? null,
      name: s.name ?? null,
      unit_price: toNumber(s.unit_price),
    }))
  } catch (e: any) {
    error.value = e?.response?.data?.error || e?.message || 'Failed to load SKUs.'
  } finally {
    isLoading.value = false
  }
}

const filteredSkus = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return skus.value

  return skus.value.filter((s) => {
    return (
      s.product_name.toLowerCase().includes(q) ||
      s.sku_code.toLowerCase().includes(q) ||
      (s.barcode || '').toLowerCase().includes(q) ||
      (s.name || '').toLowerCase().includes(q)
    )
  })
})

const resetForm = () => {
  form.product_id = 0
  form.sku_code = ''
  form.barcode = ''
  form.name = ''
  form.unit_price = 0
}

const openCreateModal = async () => {
  editingSkuId.value = null
  resetForm()
  showModal.value = true
  if (products.value.length === 0) {
    await loadProducts()
  }
}

const editSku = async (sku: SkuRow) => {
  editingSkuId.value = sku.id
  form.product_id = sku.product_id
  form.sku_code = sku.sku_code
  form.barcode = sku.barcode || ''
  form.name = sku.name || ''
  form.unit_price = sku.unit_price
  showModal.value = true
  if (products.value.length === 0) {
    await loadProducts()
  }
}

const saveSku = async () => {
  if (!form.product_id) {
    notificationStore.error('Product is required')
    return
  }

  if (!form.sku_code.trim()) {
    notificationStore.error('SKU code is required')
    return
  }

  isSaving.value = true
  try {
    const payload = {
      product_id: form.product_id,
      sku_code: form.sku_code.trim(),
      barcode: form.barcode.trim() ? form.barcode.trim() : null,
      name: form.name.trim() ? form.name.trim() : null,
      unit_price: form.unit_price,
    }

    if (editingSkuId.value) {
      await api.put(`/skus/${editingSkuId.value}`, payload)
      notificationStore.success('SKU updated')
    } else {
      await api.post('/products/sku', payload)
      notificationStore.success('SKU created')
    }

    showModal.value = false
    await loadSkus()
  } catch (e: any) {
    notificationStore.error(e?.response?.data?.error || e?.message || 'Failed to save SKU')
  } finally {
    isSaving.value = false
  }
}

const confirmDelete = (sku: SkuRow) => {
  deleteTargetId.value = sku.id
  showDeleteConfirm.value = true
}

const deleteSku = async () => {
  if (!deleteTargetId.value) return
  try {
    await api.delete(`/skus/${deleteTargetId.value}`)
    notificationStore.success('SKU deleted')
    await loadSkus()
  } catch (e: any) {
    notificationStore.error(e?.response?.data?.error || e?.message || 'Failed to delete SKU')
  } finally {
    deleteTargetId.value = null
  }
}

onMounted(async () => {
  await Promise.all([loadProducts(), loadSkus()])
})
</script>
