<script setup lang="ts">
import { computed } from 'vue'

import type { DocumentStatus } from '@/lib/types'

const props = defineProps<{ status: DocumentStatus }>()

const styles: Record<DocumentStatus, string> = {
  uploaded: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
  queued: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
  chunking: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  embedding: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  ready: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
  failed: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
}

const label = computed(() => props.status.charAt(0).toUpperCase() + props.status.slice(1))
</script>

<template>
  <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="styles[status]">
    {{ label }}
  </span>
</template>
