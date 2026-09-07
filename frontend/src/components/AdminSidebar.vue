<script setup lang="ts">
import { RouterLink } from 'vue-router'

import AdminNavIcon from '@/components/AdminNavIcon.vue'

defineEmits<{ navigate: [] }>()

const items = [
  { name: 'admin-dashboard', label: 'Dashboard', icon: 'dashboard' },
  { name: 'admin-organizations', label: 'Organizations', icon: 'organizations' },
  { name: 'admin-settings', label: 'Settings', icon: 'settings' },
] as const
</script>

<template>
  <nav class="flex flex-col gap-1 p-3" aria-label="Admin">
    <RouterLink
      v-for="item in items"
      :key="item.name"
      :to="{ name: item.name }"
      class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-gray-600 transition-colors hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-gray-400 dark:hover:bg-gray-800"
      active-class="bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
      exact-active-class="bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
      @click="$emit('navigate')"
    >
      <AdminNavIcon :name="item.icon" />
      {{ item.label }}
    </RouterLink>
  </nav>
</template>
