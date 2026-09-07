<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ label: string; value: number | string; hint?: string }>()

const display = computed(() =>
  typeof props.value === 'number' ? new Intl.NumberFormat().format(props.value) : props.value,
)
</script>

<template>
  <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <p class="text-sm text-gray-500">{{ label }}</p>
    <p class="mt-1 text-2xl font-semibold tabular-nums">{{ display }}</p>
    <p v-if="hint" class="mt-1 text-xs text-gray-400">{{ hint }}</p>
  </div>
</template>
