<template>
  <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
    <!-- Info text -->
    <div class="text-sm text-gray-600 dark:text-gray-400">
      Showing <span class="font-semibold">{{ startItem }}</span> to
      <span class="font-semibold">{{ endItem }}</span> of
      <span class="font-semibold">{{ totalItems }}</span> results
    </div>

    <!-- Pagination controls -->
    <div class="flex items-center space-x-2">
      <!-- Previous button -->
      <button
        @click="goToPage(currentPage - 1)"
        :disabled="currentPage === 1"
        class="px-3 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
        :class="
          currentPage === 1
            ? 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-600'
            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
        "
      >
        ← Previous
      </button>

      <!-- Page numbers -->
      <div class="flex items-center space-x-1">
        <button
          v-for="page in visiblePages"
          :key="page"
          @click="goToPage(page)"
          :class="
            page === currentPage
              ? 'bg-blue-500 text-white'
              : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
          "
          class="px-3 py-2 rounded-lg text-sm font-medium transition"
        >
          {{ page }}
        </button>
      </div>

      <!-- Next button -->
      <button
        @click="goToPage(currentPage + 1)"
        :disabled="currentPage === totalPages"
        class="px-3 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
        :class="
          currentPage === totalPages
            ? 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-600'
            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
        "
      >
        Next →
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  currentPage: number
  totalPages: number
  totalItems: number
  itemsPerPage: number
}

interface Emits {
  (e: 'update:currentPage', page: number): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const startItem = computed(() => {
  if (props.totalItems === 0) return 0
  return (props.currentPage - 1) * props.itemsPerPage + 1
})

const endItem = computed(() => {
  return Math.min(props.currentPage * props.itemsPerPage, props.totalItems)
})

const visiblePages = computed(() => {
  const pages: number[] = []
  const delta = 1 // Show 1 page before and after current
  const start = Math.max(1, props.currentPage - delta)
  const end = Math.min(props.totalPages, props.currentPage + delta)

  // Always show first page
  if (start > 1) {
    pages.push(1)
    if (start > 2) pages.push(-1) // Ellipsis placeholder
  }

  // Show page range
  for (let i = start; i <= end; i++) {
    pages.push(i)
  }

  // Always show last page
  if (end < props.totalPages) {
    if (end < props.totalPages - 1) pages.push(-1) // Ellipsis placeholder
    pages.push(props.totalPages)
  }

  return pages
})

const goToPage = (page: number) => {
  if (page >= 1 && page <= props.totalPages) {
    emit('update:currentPage', page)
  }
}
</script>
