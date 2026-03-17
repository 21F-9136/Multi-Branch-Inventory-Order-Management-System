<template>
  <router-view />
</template>

<script setup lang="ts">
import { useThemeStore } from '@/store/theme'

// Initialize theme on app load
const themeStore = useThemeStore()
themeStore.applyTheme()
</script>
