<template>
  <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex-1">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search products..."
            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
        <button
          v-if="authStore.isAdmin"
          @click="openCreateModal"
          class="ml-4 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition font-medium"
        >
          + Add Product
        </button>
      </div>

      <!-- Products Table -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Name</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Brand</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Category</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">SKUs</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Status</th>
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
            <tr v-else-if="paginatedProducts.length === 0">
              <td colspan="6" class="px-6 py-6 text-sm text-gray-600 dark:text-gray-400">No products found.</td>
            </tr>
            <tr v-for="product in paginatedProducts" :key="product.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
              <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ product.name }}</td>
              <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ product.brand_name || '—' }}</td>
              <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ product.category_name || '—' }}</td>
              <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ product.sku_count }}</td>
              <td class="px-6 py-4 text-sm">
                <span
                  class="px-2 py-1 text-xs font-semibold rounded-full"
                  :class="
                    product.is_active
                      ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400'
                      : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'
                  "
                >
                  {{ product.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm">
                <button
                  v-if="authStore.isAdmin"
                  @click="editProduct(product)"
                  class="text-blue-500 hover:text-blue-700 dark:hover:text-blue-400 mr-3"
                >
                  Edit
                </button>
                <button
                  v-if="authStore.isAdmin"
                  @click="confirmDelete(product)"
                  class="text-red-500 hover:text-red-700 dark:hover:text-red-400"
                >
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination
        :current-page="currentPage"
        :total-pages="totalPages"
        :total-items="filteredProducts.length"
        :items-per-page="itemsPerPage"
        @update:current-page="currentPage = $event"
      />

      <Modal v-model="showModal">
        <template #header>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ editingProductId ? 'Edit Product' : 'Create Product' }}
          </h2>
        </template>

        <form @submit.prevent="saveProduct" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
              <input
                v-model="form.name"
                type="text"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Brand</label>
              <select
                v-model="form.brand_id"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option :value="null">Select brand</option>
                <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
              <select
                v-model="form.category_id"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option :value="null">Select category</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
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
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tax %</label>
              <input
                v-model.number="form.tax_percent"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cost Price</label>
              <input
                v-model.number="form.cost_price"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sale Price</label>
              <input
                v-model.number="form.sale_price"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
              <select
                v-model="form.status"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>

            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
              <textarea
                v-model="form.description"
                rows="3"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              ></textarea>
            </div>
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
            @click="saveProduct"
            :disabled="isSaving"
            class="ml-3 px-4 py-2 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white rounded-lg transition"
          >
            {{ isSaving ? 'Saving...' : 'Save' }}
          </button>
        </template>
      </Modal>

      <ConfirmDialog
        v-model="showDeleteConfirm"
        title="Delete product?"
        message="This will remove the product from the system."
        icon="warning"
        :is-dangerous="true"
        yes-label="Delete"
        no-label="Cancel"
        @confirm="deleteProduct"
      />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import Modal from '@/components/Modal.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import Pagination from '@/components/Pagination.vue'
import { useAuthStore } from '@/store/auth'
import { useNotificationStore } from '@/store/notifications'
import api from '@/services/api'

const authStore = useAuthStore()
const notificationStore = useNotificationStore()
const searchQuery = ref('')
const isLoading = ref(false)
const error = ref<string | null>(null)
const currentPage = ref(1)
const itemsPerPage = 10

interface ProductRow {
  id: number
  name: string
  brand_id: number | null
  brand_name: string | null
  category_id: number | null
  category_name: string | null
  description: string | null
  status: string | null
  cost_price: number | null
  tax_percent: number | null
  is_active: boolean
  sku_count: number
}

const products = ref<ProductRow[]>([])

interface MasterRef {
  id: number
  name: string
}

const brands = ref<MasterRef[]>([])
const categories = ref<MasterRef[]>([])

const showModal = ref(false)
const isSaving = ref(false)
const editingProductId = ref<number | null>(null)
const editingSkuId = ref<number | null>(null)

const showDeleteConfirm = ref(false)
const deleteTargetId = ref<number | null>(null)

const form = reactive({
  name: '',
  brand_id: null as number | null,
  category_id: null as number | null,
  sku_code: '',
  cost_price: 0,
  sale_price: 0,
  tax_percent: 0,
  status: 'active',
  description: '',
})

const isNumericLike = (value: unknown): boolean => {
  if (value === null || value === undefined || value === '') return false
  const n = typeof value === 'number' ? value : Number(value)
  return Number.isFinite(n)
}

const getItems = <T,>(payload: any): T[] => {
  if (Array.isArray(payload?.data?.items)) return payload.data.items as T[]
  if (Array.isArray(payload?.items)) return payload.items as T[]
  if (Array.isArray(payload?.data)) return payload.data as T[]
  return []
}

const getItem = <T,>(payload: any): T | null => {
  if (payload?.data?.item) return payload.data.item as T
  if (payload?.item) return payload.item as T
  if (payload?.data && !Array.isArray(payload.data)) return payload.data as T
  return null
}

const toNumber = (value: unknown): number => {
  if (typeof value === 'number') return value
  if (typeof value === 'string' && value.trim() !== '' && !Number.isNaN(Number(value))) return Number(value)
  return 0
}

const loadProducts = async () => {
  isLoading.value = true
  error.value = null
  try {
    const res = await api.get('/products')
    const items = getItems<any>(res.data)
    products.value = items.map((p) => ({
      id: Number(p.id),
      name: String(p.name ?? ''),
      brand_id: p.brand_id !== undefined && p.brand_id !== null ? Number(p.brand_id) : null,
      brand_name: p.brand_name ?? null,
      category_id: p.category_id !== undefined && p.category_id !== null ? Number(p.category_id) : null,
      category_name: p.category_name ?? null,
      description: p.description ?? null,
      status: (p.status ?? null) as string | null,
      cost_price: p.cost_price !== undefined && p.cost_price !== null && p.cost_price !== '' ? Number(p.cost_price) : null,
      tax_percent: p.tax_percent !== undefined && p.tax_percent !== null && p.tax_percent !== '' ? Number(p.tax_percent) : null,
      is_active: toNumber(p.is_active) === 1,
      sku_count: toNumber(p.sku_count),
    }))
  } catch (e: any) {
    error.value = e?.response?.data?.error || e?.message || 'Failed to load products.'
  } finally {
    isLoading.value = false
  }
}

const loadMasters = async () => {
  try {
    const [brandsRes, categoriesRes] = await Promise.all([
      api.get('/brands'),
      api.get('/categories'),
    ])

    brands.value = getItems<any>(brandsRes.data).map((b) => ({ id: Number(b.id), name: String(b.name ?? '') }))
    categories.value = getItems<any>(categoriesRes.data).map((c) => ({ id: Number(c.id), name: String(c.name ?? '') }))
  } catch {
    brands.value = []
    categories.value = []
  }
}

const filteredProducts = computed(() => {
  return products.value.filter((p) => p.name.toLowerCase().includes(searchQuery.value.toLowerCase()))
})

const totalPages = computed(() => Math.ceil(filteredProducts.value.length / itemsPerPage))

const paginatedProducts = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredProducts.value.slice(start, end)
})

const resetForm = () => {
  form.name = ''
  form.brand_id = null
  form.category_id = null
  form.sku_code = ''
  form.cost_price = 0
  form.sale_price = 0
  form.tax_percent = 0
  form.status = 'active'
  form.description = ''
}

const openCreateModal = () => {
  editingProductId.value = null
  editingSkuId.value = null
  resetForm()
  showModal.value = true
}

const editProduct = async (product: ProductRow) => {
  const productId = product.id
  editingProductId.value = productId
  editingSkuId.value = null
  resetForm()

  isSaving.value = true
  try {
    const [productRes, skusRes] = await Promise.all([
      api.get(`/products/${productId}`),
      api.get(`/products/${productId}/skus`),
    ])

    const productItem = getItem<any>(productRes.data)
    if (!productItem) {
      throw new Error('Failed to load product details')
    }

    form.name = String(productItem.name ?? '')
    form.brand_id = productItem.brand_id !== undefined && productItem.brand_id !== null ? Number(productItem.brand_id) : null
    form.category_id = productItem.category_id !== undefined && productItem.category_id !== null ? Number(productItem.category_id) : null
    form.description = productItem.description ?? ''
    form.status = (productItem.status || (toNumber(productItem.is_active) === 1 ? 'active' : 'inactive')) as string
    form.tax_percent =
      productItem.tax_percent !== undefined && productItem.tax_percent !== null && productItem.tax_percent !== ''
        ? Number(productItem.tax_percent)
        : 0
    form.cost_price =
      productItem.cost_price !== undefined && productItem.cost_price !== null && productItem.cost_price !== ''
        ? Number(productItem.cost_price)
        : 0

    const skuItems = getItems<any>(skusRes.data)
    if (skuItems.length === 0) {
      throw new Error('No SKUs found for this product')
    }

    const sku = skuItems[0]
    editingSkuId.value = Number(sku.id)
    form.sku_code = String(sku.sku_code ?? '')
    // Keep backward compatibility when legacy rows only have SKU-level cost populated.
    if (!isNumericLike(form.cost_price) || Number(form.cost_price) <= 0) {
      form.cost_price = sku.cost_price !== undefined && sku.cost_price !== null && sku.cost_price !== '' ? Number(sku.cost_price) : 0
    }
    form.sale_price = sku.sale_price !== undefined && sku.sale_price !== null && sku.sale_price !== '' ? Number(sku.sale_price) : 0

    showModal.value = true
  } catch (e: any) {
    editingProductId.value = null
    editingSkuId.value = null
    notificationStore.error(e?.response?.data?.error || e?.message || 'Failed to load product')
  } finally {
    isSaving.value = false
  }
}

const saveProduct = async () => {
  if (!form.name.trim()) {
    notificationStore.error('Product name is required')
    return
  }

  if (!form.sku_code.trim()) {
    notificationStore.error('SKU code is required')
    return
  }

  if (!isNumericLike(form.cost_price)) {
    notificationStore.error('Cost price must be numeric')
    return
  }

  if (!isNumericLike(form.sale_price)) {
    notificationStore.error('Sale price must be numeric')
    return
  }

  if (!isNumericLike(form.tax_percent)) {
    notificationStore.error('Tax % must be numeric')
    return
  }

  isSaving.value = true
  try {
    if (editingProductId.value) {
      if (!editingSkuId.value) {
        throw new Error('SKU not loaded for this product')
      }

      const payload = {
        name: form.name.trim(),
        brand_id: form.brand_id,
        category_id: form.category_id,
        description: form.description.trim() ? form.description.trim() : null,
        status: form.status,
        cost_price: isNumericLike(form.cost_price) ? Number(form.cost_price) : null,
        tax_percent: isNumericLike(form.tax_percent) ? Number(form.tax_percent) : null,
        // keep legacy field in sync
        is_active: form.status === 'active' ? 1 : 0,
      }

      await api.put(`/products/${editingProductId.value}`, payload)

      const skuPayload = {
        sku_code: form.sku_code.trim(),
        cost_price: Number(form.cost_price),
        sale_price: Number(form.sale_price),
      }
      await api.put(`/skus/${editingSkuId.value}`, skuPayload)

      notificationStore.success('Product updated')
    } else {
      const createProductPayload = {
        name: form.name.trim(),
        brand_id: form.brand_id,
        category_id: form.category_id,
        description: form.description.trim() ? form.description.trim() : null,
        status: form.status,
        cost_price: isNumericLike(form.cost_price) ? Number(form.cost_price) : null,
        tax_percent: isNumericLike(form.tax_percent) ? Number(form.tax_percent) : null,
        // keep legacy field in sync
        is_active: form.status === 'active' ? 1 : 0,
      }

      const res = await api.post('/products', createProductPayload)
      const productId =
        res?.data?.data?.product_id ??
        res?.data?.product_id ??
        res?.data?.data?.id ??
        res?.data?.id

      const newProductId = Number(productId)
      if (!newProductId) {
        throw new Error('Product created but no product_id was returned')
      }

      const createSkuPayload = {
        product_id: newProductId,
        sku_code: form.sku_code.trim(),
        cost_price: Number(form.cost_price),
        sale_price: Number(form.sale_price),
      }

      await api.post('/products/sku', createSkuPayload)
      notificationStore.success('Product created')
    }

    showModal.value = false
    await loadProducts()
  } catch (e: any) {
    notificationStore.error(e?.response?.data?.error || e?.message || 'Failed to save product')
  } finally {
    isSaving.value = false
  }
}

const confirmDelete = (product: ProductRow) => {
  deleteTargetId.value = product.id
  showDeleteConfirm.value = true
}

const deleteProduct = async () => {
  if (!deleteTargetId.value) return
  try {
    await api.delete(`/products/${deleteTargetId.value}`)
    notificationStore.success('Product deleted')
    await loadProducts()
  } catch (e: any) {
    notificationStore.error(e?.response?.data?.error || e?.message || 'Failed to delete product')
  } finally {
    deleteTargetId.value = null
  }
}

// Reset pagination when search changes
watch(searchQuery, () => {
  currentPage.value = 1
})

onMounted(() => {
  loadMasters()
  loadProducts()
})
</script>
