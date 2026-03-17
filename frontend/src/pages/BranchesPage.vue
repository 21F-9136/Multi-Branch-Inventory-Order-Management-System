<template>
  <div class="space-y-6">
      <!-- Header with Create Button -->
      <div class="flex items-center justify-between">
        <div class="flex-1">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search branches..."
            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
        <button
          v-if="authStore.isAdmin"
          @click="openCreateModal"
          class="ml-4 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition font-medium"
        >
          + Create Branch
        </button>
      </div>

      <!-- Branches Table -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Branch Name</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Address</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Manager Name</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Status</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="isTableLoading">
              <td colspan="5" class="px-6 py-6 text-sm text-gray-600 dark:text-gray-400">Loading…</td>
            </tr>
            <tr v-else-if="tableError">
              <td colspan="5" class="px-6 py-6 text-sm text-red-600 dark:text-red-400">{{ tableError }}</td>
            </tr>
            <tr v-else-if="paginatedBranches.length === 0">
              <td colspan="5" class="px-6 py-6 text-sm text-gray-600 dark:text-gray-400">No branches found.</td>
            </tr>
            <tr v-for="branch in paginatedBranches" :key="branch.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
              <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ branch.name }}</td>
              <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ branch.address }}</td>
              <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ branch.manager_name || '-' }}</td>
              <td class="px-6 py-4 text-sm">
                <span
                  class="px-2 py-1 text-xs font-semibold rounded-full"
                  :class="
                    branch.status === 'active'
                      ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400'
                      : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'
                  "
                >
                  {{ branch.status === 'active' ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm">
                <button
                  v-if="authStore.isAdmin"
                  @click="openEditModal(branch)"
                  class="text-blue-500 hover:text-blue-700 dark:hover:text-blue-400 transition"
                >
                  Edit
                </button>
                <button
                  v-if="authStore.isAdmin"
                  @click="confirmDelete(branch)"
                  class="ml-3 text-red-500 hover:text-red-700 dark:hover:text-red-400 transition"
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
        :total-items="filteredBranches.length"
        :items-per-page="itemsPerPage"
        @update:current-page="currentPage = $event"
      />

      <!-- Create Branch Modal -->
      <Modal v-model="showCreateModal">
        <template #header>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Create Branch</h2>
        </template>

        <form @submit.prevent="handleCreateBranch" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Branch Name</label>
              <input
                v-model="formData.name"
                type="text"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
              <input
                v-model="formData.address"
                type="text"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Manager</label>
              <select
                v-model="formData.manager_id"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option :value="null">Unassigned</option>
                <option v-for="u in managers" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <div v-if="managers.length === 0" class="mt-2 space-y-2">
                <p class="text-xs text-amber-600 dark:text-amber-400">
                  No Branch Manager users available. Please create or assign a Branch Manager role.
                </p>
                <button
                  type="button"
                  @click="goToCreateBranchManager"
                  class="px-3 py-1.5 text-xs font-medium rounded-md bg-blue-500 hover:bg-blue-600 text-white transition"
                >
                  Create Branch Manager
                </button>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
              <select
                v-model="formData.status"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
        </form>

        <template #footer>
          <button
            @click="showCreateModal = false"
            class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg transition"
          >
            Cancel
          </button>
          <button
            @click="handleCreateBranch"
            :disabled="isSaving"
            class="ml-3 px-4 py-2 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white rounded-lg transition"
          >
            {{ isSaving ? 'Creating...' : 'Create' }}
          </button>
        </template>
      </Modal>

      <!-- Edit Branch Modal -->
      <Modal v-model="showEditModal">
        <template #header>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Branch</h2>
        </template>

        <form @submit.prevent="handleUpdateBranch" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Branch Name</label>
              <input
                v-model="editForm.name"
                type="text"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
              <input
                v-model="editForm.address"
                type="text"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Manager</label>
              <select
                v-model="editForm.manager_id"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option :value="null">Unassigned</option>
                <option v-for="u in managers" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <p v-if="managers.length === 0" class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                No Branch Manager users available. Please create or assign a Branch Manager role.
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
              <select
                v-model="editForm.status"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
        </form>

        <template #footer>
          <button
            @click="showEditModal = false"
            class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg transition"
          >
            Cancel
          </button>
          <button
            @click="handleUpdateBranch"
            :disabled="isSaving"
            class="ml-3 px-4 py-2 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white rounded-lg transition"
          >
            {{ isSaving ? 'Saving...' : 'Save' }}
          </button>
        </template>
      </Modal>

      <ConfirmDialog
        v-model="showDeleteConfirm"
        title="Delete branch?"
        message="This will remove the branch from the system."
        icon="warning"
        :is-dangerous="true"
        yes-label="Delete"
        no-label="Cancel"
        @confirm="deleteBranch"
      />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Modal from '@/components/Modal.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import Pagination from '@/components/Pagination.vue'
import { useAuthStore } from '@/store/auth'
import { useNotificationStore } from '@/store/notifications'
import api from '@/services/api'

const router = useRouter()
const authStore = useAuthStore()
const notificationStore = useNotificationStore()

const searchQuery = ref('')
const showCreateModal = ref(false)
const showEditModal = ref(false)
const isSaving = ref(false)
const isTableLoading = ref(false)
const tableError = ref<string | null>(null)
const currentPage = ref(1)
const itemsPerPage = 10

const showDeleteConfirm = ref(false)
const deleteTargetId = ref<number | null>(null)
const editingBranchId = ref<number | null>(null)

interface ManagerOption {
  id: number
  name: string
}

const managers = ref<ManagerOption[]>([])

const formData = reactive({
  name: '',
  address: '',
  manager_id: null as number | null,
  status: 'active',
})

const editForm = reactive({
  name: '',
  address: '',
  manager_id: null as number | null,
  status: 'active',
})

interface BranchRow {
  id: number
  name: string
  address: string | null
  manager_id: number | null
  manager_name: string | null
  status: 'active' | 'inactive' | string | null
}

const branches = ref<BranchRow[]>([])

const getItems = <T,>(payload: any): T[] => {
  if (Array.isArray(payload?.data?.items)) return payload.data.items as T[]
  if (Array.isArray(payload?.items)) return payload.items as T[]
  if (Array.isArray(payload?.data)) return payload.data as T[]
  return []
}

const loadBranches = async () => {
  isTableLoading.value = true
  tableError.value = null
  try {
    const res = await api.get('/branches')
    const items = getItems<any>(res.data)
    branches.value = items.map((b) => ({
      id: Number(b.id),
      name: String(b.name ?? ''),
      address: b.address ?? null,
      manager_id: b.manager_id !== undefined && b.manager_id !== null && b.manager_id !== '' ? Number(b.manager_id) : null,
      manager_name: b.manager_name ?? null,
      status: b.status ?? null,
    }))
  } catch (e: any) {
    tableError.value = e?.response?.data?.error || e?.message || 'Failed to load branches.'
  } finally {
    isTableLoading.value = false
  }
}

const loadManagers = async (includeManagerId: number | null) => {
  try {
    const params: any = {}
    if (includeManagerId) params.include_manager_id = includeManagerId

    const res = await api.get('/api/managers', { params })
    const items = getItems<any>(res.data)
    managers.value = items
      .map((u) => ({
        id: Number(u.id),
        name: String(u.name ?? ''),
      }))
      .filter((u) => u.id && u.name)
  } catch (e: any) {
    notificationStore.error(e?.response?.data?.error || e?.message || 'Failed to load managers')
  }
}

const openCreateModal = async () => {
  formData.name = ''
  formData.address = ''
  formData.manager_id = null
  formData.status = 'active'
  await loadManagers(null)
  showCreateModal.value = true
}

const goToCreateBranchManager = () => {
  showCreateModal.value = false
  router.push('/users')
}

const filteredBranches = computed(() => {
  const q = searchQuery.value.toLowerCase()
  return branches.value.filter((b) => {
    const name = b.name.toLowerCase()
    const address = (b.address || '').toLowerCase()
    const managerName = (b.manager_name || '').toLowerCase()
    return name.includes(q) || address.includes(q) || managerName.includes(q)
  })
})

const totalPages = computed(() => Math.ceil(filteredBranches.value.length / itemsPerPage))

const paginatedBranches = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredBranches.value.slice(start, end)
})

const handleCreateBranch = async () => {
  if (!formData.name.trim()) {
    notificationStore.error('Branch Name is required')
    return
  }

  if (!formData.address.trim()) {
    notificationStore.error('Address is required')
    return
  }

  if (!formData.status) {
    notificationStore.error('Status is required')
    return
  }

  isSaving.value = true

  try {
    const response = await api.post('/branches', {
      name: formData.name.trim(),
      address: formData.address.trim(),
      manager_id: formData.manager_id,
      status: formData.status,
    })

    if (response.data.success) {
      notificationStore.success('Branch created successfully')
      showCreateModal.value = false
      formData.name = ''
      formData.address = ''
      formData.manager_id = null
      formData.status = 'active'
      await loadBranches()
    }
  } catch (error: any) {
    const details = error?.response?.data?.details
    const message =
      (details && typeof details === 'object' && Object.values(details).length > 0 ? String(Object.values(details)[0]) : null) ||
      error?.response?.data?.error ||
      error?.message ||
      'Failed to create branch'
    notificationStore.error(message)
  } finally {
    isSaving.value = false
  }
}

const openEditModal = async (branch: BranchRow) => {
  editingBranchId.value = branch.id
  editForm.name = branch.name
  editForm.address = branch.address || ''
  editForm.manager_id = branch.manager_id
  editForm.status = (branch.status || 'active') as any
  await loadManagers(editForm.manager_id)
  showEditModal.value = true
}

const handleUpdateBranch = async () => {
  if (!editingBranchId.value) return
  if (!editForm.name.trim()) {
    notificationStore.error('Branch Name is required')
    return
  }
  if (!editForm.address.trim()) {
    notificationStore.error('Address is required')
    return
  }
  if (!editForm.status) {
    notificationStore.error('Status is required')
    return
  }

  isSaving.value = true
  try {
    await api.put(`/branches/${editingBranchId.value}`, {
      name: editForm.name.trim(),
      address: editForm.address.trim(),
      manager_id: editForm.manager_id,
      status: editForm.status,
    })
    notificationStore.success('Branch updated')
    showEditModal.value = false
    editingBranchId.value = null
    await loadBranches()
  } catch (e: any) {
    const details = e?.response?.data?.details
    const message =
      (details && typeof details === 'object' && Object.values(details).length > 0 ? String(Object.values(details)[0]) : null) ||
      e?.response?.data?.error ||
      e?.message ||
      'Failed to update branch'
    notificationStore.error(message)
  } finally {
    isSaving.value = false
  }
}

const confirmDelete = (branch: BranchRow) => {
  deleteTargetId.value = branch.id
  showDeleteConfirm.value = true
}

const deleteBranch = async () => {
  if (!deleteTargetId.value) return
  try {
    await api.delete(`/branches/${deleteTargetId.value}`)
    notificationStore.success('Branch deleted')
    await loadBranches()
  } catch (e: any) {
    notificationStore.error(e?.response?.data?.error || e?.message || 'Failed to delete branch')
  } finally {
    deleteTargetId.value = null
  }
}

// Reset pagination when search changes
watch(searchQuery, () => {
  currentPage.value = 1
})

onMounted(() => {
  loadBranches()
})
</script>
