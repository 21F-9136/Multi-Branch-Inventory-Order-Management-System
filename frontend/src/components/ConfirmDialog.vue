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
        @click.self="handleNo"
        class="fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center p-4"
      >
        <!-- Dialog Content -->
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
            <!-- Icon -->
            <div
              v-if="icon"
              class="pt-6 flex justify-center"
            >
              <div :class="getIconClasses(icon)">
                <component :is="getIconComponent(icon)" class="w-6 h-6" />
              </div>
            </div>

            <!-- Title & Message -->
            <div class="px-6 pt-4 pb-3 text-center">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ title }}
              </h2>
              <p v-if="message" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                {{ message }}
              </p>
            </div>

            <!-- Actions -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end space-x-3">
              <button
                @click="handleNo"
                class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition"
              >
                {{ noLabel }}
              </button>
              <button
                @click="handleYes"
                :class="[
                  'px-4 py-2 text-white rounded-lg transition font-medium',
                  isDangerous
                    ? 'bg-red-500 hover:bg-red-600 dark:bg-red-600 dark:hover:bg-red-700'
                    : 'bg-blue-500 hover:bg-blue-600 dark:bg-blue-600 dark:hover:bg-blue-700',
                ]"
              >
                {{ yesLabel }}
              </button>
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
    title: string
    message?: string
    icon?: 'warning' | 'error' | 'success' | 'info'
    isDangerous?: boolean
    yesLabel?: string
    noLabel?: string
  }>(),
  {
    icon: 'warning',
    isDangerous: false,
    yesLabel: 'Yes',
    noLabel: 'No',
  },
)

const { title, message, icon, isDangerous, yesLabel, noLabel } = toRefs(props)

const emit = defineEmits<{
  'update:modelValue': [boolean]
  confirm: []
  cancel: []
}>()

const isOpen = ref(false)

const handleYes = () => {
  emit('confirm')
  emit('update:modelValue', false)
}

const handleNo = () => {
  emit('cancel')
  emit('update:modelValue', false)
}

watch(
  () => props.modelValue,
  (newVal) => {
    isOpen.value = newVal
  },
  { immediate: true },
)

const getIconClasses = (icon: string) => {
  const base = 'flex justify-center items-center w-12 h-12 rounded-full mx-auto'
  const variants = {
    warning: 'bg-yellow-100 dark:bg-yellow-900/30',
    error: 'bg-red-100 dark:bg-red-900/30',
    success: 'bg-green-100 dark:bg-green-900/30',
    info: 'bg-blue-100 dark:bg-blue-900/30',
  }
  return `${base} ${variants[icon as keyof typeof variants] || variants.info}`
}

const getIconComponent = (icon: string) => {
  switch (icon) {
    case 'warning':
    case 'error':
    case 'success':
    case 'info':
      return 'div'
    default:
      return 'div'
  }
}
</script>
