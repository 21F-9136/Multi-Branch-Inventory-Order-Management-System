<template>
  <Teleport to="body">
    <transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        @click.self="handleBackdropClick"
        class="fixed inset-0 bg-black/50 dark:bg-black/70 z-40 flex items-center justify-center p-4"
      >
        <!-- Modal Content -->
        <transition
          enter-active-class="transition ease-out duration-200"
          enter-from-class="transform opacity-0 scale-95"
          enter-to-class="transform opacity-100 scale-100"
          leave-active-class="transition ease-in duration-150"
          leave-from-class="transform opacity-100 scale-100"
          leave-to-class="transform opacity-0 scale-95"
        >
          <div
            v-if="isOpen"
            class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full"
          >
            <!-- Header -->
            <div
              v-if="$slots.header"
              class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between"
            >
              <slot name="header"></slot>
              <button
                v-if="closeable"
                @click="close"
                class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition"
              >
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
              </button>
            </div>

            <!-- Body -->
            <div class="px-6 py-4">
              <slot></slot>
            </div>

            <!-- Footer -->
            <div
              v-if="$slots.footer"
              class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end space-x-3"
            >
              <slot name="footer"></slot>
            </div>
          </div>
        </transition>
      </div>
    </transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, toRefs, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    closeable?: boolean
  }>(),
  {
    closeable: true,
  },
)

const { closeable } = toRefs(props)

const emit = defineEmits<{
  'update:modelValue': [boolean]
}>()

const isOpen = ref(false)

const close = () => {
  emit('update:modelValue', false)
}

const handleBackdropClick = () => {
  if (props.closeable) {
    close()
  }
}

// Watch for external updates
watch(
  () => props.modelValue,
  (newVal) => {
    isOpen.value = newVal
  },
  { immediate: true },
)
</script>
