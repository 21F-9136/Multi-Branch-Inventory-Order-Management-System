<template>
  <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex-1">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search users…"
            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
        <button
          v-if="authStore.isAdmin"
          @click="openCreateModal"
          class="ml-4 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition font-medium"
        >
          + Add User
        </button>
      </div>

      <!-- Users Table -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Name</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Email</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Role</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white">Branch</th>
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
            <tr v-else-if="paginatedUsers.length === 0">
              <td colspan="6" class="px-6 py-6 text-sm text-gray-600 dark:text-gray-400">No users found.</td>
            </tr>
            <tr v-for="u in paginatedUsers" :key="u.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
              <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ u.name }}</td>
              <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ u.email }}</td>
              <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ u.role }}</td>
              <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ u.branch_name || '—' }}</td>
              <td class="px-6 py-4 text-sm">
                <span
                  class="px-2 py-1 text-xs font-semibold rounded-full"
                  :class="
                    u.is_active
                      ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400'
                      : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'
                  "
                >
                  {{ u.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm">
                <button
                  v-if="authStore.isAdmin"
                  @click="openEditModal(u)"
                  class="text-blue-500 hover:text-blue-700 dark:hover:text-blue-400 mr-3"
                >
                  Edit
                </button>
                <button
                  v-if="authStore.isAdmin"
                  @click="confirmDelete(u)"
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
        :total-items="filteredUsers.length"
        :items-per-page="itemsPerPage"
        @update:current-page="currentPage = $event"
      />

      <Modal v-model="showModal">
        <template #header>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ editingUserId ? 'Edit User' : 'Create User' }}
          </h2>
        </template>

        <form @submit.prevent="saveUser" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
            <input
              v-model="form.name"
              type="text"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
            <input
              v-model="form.email"
              type="email"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div v-if="!editingUserId">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
            <input
              v-model="form.password"
              type="password"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Only required when creating a user.</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role</label>
            <select
              v-model="form.role"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="user">user</option>
              <option value="sales">sales</option>
              <option value="manager">manager</option>
              <option value="admin">admin</option>
              <option value="super_admin">super_admin</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Branch</label>
            <select
              v-model="form.branch_id"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">No branch</option>
              <option v-for="b in branches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
            </select>
          </div>

          <div class="flex items-center space-x-2">
            <input id="is_active" v-model="form.is_active" type="checkbox" class="h-4 w-4" />
            <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">Active</label>
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
            @click="saveUser"
            :disabled="isSaving"
            class="ml-3 px-4 py-2 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white rounded-lg transition"
          >
            {{ isSaving ? 'Saving…' : 'Save' }}
          </button>
        </template>
      </Modal>

      <ConfirmDialog
        v-model="showDeleteConfirm"
        title="Delete user?"
        message="This will remove the user from the system."
        icon="warning"
        :is-dangerous="true"
        yes-label="Delete"
        no-label="Cancel"
        @confirm="deleteUser"
      />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import Modal from '@/components/Modal.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import Pagination from '@/components/Pagination.vue'
import api from '@/services/api'
import { useAuthStore } from '@/store/auth'
import { useNotificationStore } from '@/store/notifications'

const authStore = useAuthStore()
const notificationStore = useNotificationStore()

const searchQuery = ref('')
const isLoading = ref(false)
const error = ref<string | null>(null)
const currentPage = ref(1)
const itemsPerPage = 10

interface Branch {
  id: number
  name: string
}

interface UserRow {
  id: number
  name: string
  email: string
  role: string
  branch_id: number | null
  branch_name: string | null
  is_active: boolean
}

const branches = ref<Branch[]>([])
const users = ref<UserRow[]>([])

const showModal = ref(false)
const isSaving = ref(false)
const editingUserId = ref<number | null>(null)

const showDeleteConfirm = ref(false)
const deleteTargetId = ref<number | null>(null)

const form = reactive({
  name: '',
  email: '',
  password: '',
  role: 'user',
  branch_id: '',
  is_active: true,
})

const getItems = <T,>(payload: any): T[] => {
  if (Array.isArray(payload?.data?.items)) return payload.data.items as T[]
  if (Array.isArray(payload?.items)) return payload.items as T[]
  if (Array.isArray(payload?.data)) return payload.data as T[]
  return []
}

const loadUsersPageData = async () => {
  isLoading.value = true
  error.value = null
  try {
    const [branchesRes, usersRes] = await Promise.all([api.get('/branches'), api.get('/users')])
    branches.value = getItems<Branch>(branchesRes.data)

    const raw = getItems<any>(usersRes.data)
    users.value = raw.map((u) => ({
      id: Number(u.id),
      name: String(u.name ?? ''),
      email: String(u.email ?? ''),
      role: String(u.role ?? 'user'),
      branch_id: u.branch_id === null || u.branch_id === undefined ? null : Number(u.branch_id),
      branch_name: u.branch_name ?? null,
      is_active: Number(u.is_active ?? 1) === 1,
    }))
  } catch (e: any) {
    error.value = e?.response?.data?.error || e?.message || 'Failed to load users.'
  } finally {
    isLoading.value = false
  }
}

const filteredUsers = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return users.value
  return users.value.filter((u) => {
    return u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q) || u.role.toLowerCase().includes(q)
  })
})

const totalPages = computed(() => Math.ceil(filteredUsers.value.length / itemsPerPage))

const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredUsers.value.slice(start, end)
})

const resetForm = () => {
  form.name = ''
  form.email = ''
  form.password = ''
  form.role = 'user'
  form.branch_id = ''
  form.is_active = true
}

const openCreateModal = () => {
  editingUserId.value = null
  resetForm()
  showModal.value = true
}

const openEditModal = (u: UserRow) => {
  editingUserId.value = u.id
  form.name = u.name
  form.email = u.email
  form.password = ''
  form.role = u.role
  form.branch_id = u.branch_id ? String(u.branch_id) : ''
  form.is_active = u.is_active
  showModal.value = true
}

const saveUser = async () => {
  if (!authStore.isAdmin) return

  if (!form.name.trim() || !form.email.trim()) {
    notificationStore.error('Name and email are required')
    return
  }

  if (!editingUserId.value && !form.password.trim()) {
    notificationStore.error('Password is required for new users')
    return
  }

  isSaving.value = true
  try {
    if (editingUserId.value) {
      await api.put(`/users/${editingUserId.value}`, {
        name: form.name.trim(),
        email: form.email.trim(),
        branch_id: form.branch_id ? Number(form.branch_id) : null,
        is_active: form.is_active ? 1 : 0,
        role: form.role,
      })
      notificationStore.success('User updated')
    } else {
      await api.post('/auth/register', {
        name: form.name.trim(),
        email: form.email.trim(),
        password: form.password,
        branch_id: form.branch_id ? Number(form.branch_id) : null,
        is_active: form.is_active ? 1 : 0,
        role: form.role,
      })
      notificationStore.success('User created')
    }

    showModal.value = false
    await loadUsersPageData()
  } catch (e: any) {
    notificationStore.error(e?.response?.data?.error || e?.message || 'Failed to save user')
  } finally {
    isSaving.value = false
  }
}

const confirmDelete = (u: UserRow) => {
  deleteTargetId.value = u.id
  showDeleteConfirm.value = true
}

const deleteUser = async () => {
  if (!deleteTargetId.value) return
  try {
    await api.delete(`/users/${deleteTargetId.value}`)
    notificationStore.success('User deleted')
    await loadUsersPageData()
  } catch (e: any) {
    notificationStore.error(e?.response?.data?.error || e?.message || 'Failed to delete user')
  } finally {
    deleteTargetId.value = null
  }
}

// Reset pagination when search changes
watch(searchQuery, () => {
  currentPage.value = 1
})

onMounted(() => {
  loadUsersPageData()
})
</script>
