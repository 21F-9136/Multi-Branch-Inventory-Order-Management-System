<template>
  <div class="flex items-center justify-center">
    <div class="animate-spin">
      <svg
        class="w-8 h-8 text-blue-500"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <circle
          cx="12"
          cy="12"
          r="10"
          stroke="currentColor"
          stroke-width="2"
          stroke-dasharray="15.7 47.1"
          stroke-linecap="round"
          opacity="0.5"
        />
        <circle
          cx="12"
          cy="12"
          r="10"
          stroke="currentColor"
          stroke-width="2"
          stroke-dasharray="1 47.1"
          stroke-linecap="round"
        />
      </svg>
    </div>
    <span v-if="text" class="ml-3 text-gray-600 dark:text-gray-400">{{ text }}</span>
  </div>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    text?: string
  }>(),
  { text: 'Loading...' },
)
</script>

<style scoped>
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.animate-spin {
  animation: spin 1s linear infinite;
}
</style>
