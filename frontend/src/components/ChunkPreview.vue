<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { api, errorMessage } from '@/lib/api'
import type { Chunk, DocumentFile, Paginated } from '@/lib/types'

const props = defineProps<{ document: DocumentFile }>()
defineEmits<{ close: [] }>()

const chunks = ref<Chunk[]>([])
const total = ref(0)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const { data } = await api.get<Paginated<Chunk>>(`/documents/${props.document.id}/chunks`)
    chunks.value = data.data
    total.value = data.meta.total
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
})

function headingPath(chunk: Chunk): string | null {
  const path = chunk.metadata?.heading_path
  return Array.isArray(path) && path.length ? path.join(' › ') : null
}
</script>

<template>
  <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2 dark:border-gray-800">
      <h3 class="text-sm font-medium">
        Chunks — {{ document.original_filename }}
        <span class="text-gray-400">({{ total }} · {{ document.chunking_strategy }})</span>
      </h3>
      <button class="text-xs text-gray-500 hover:underline" @click="$emit('close')">Close</button>
    </div>

    <p v-if="loading" class="p-4 text-sm text-gray-500">Loading…</p>
    <p v-else-if="error" class="p-4 text-sm text-red-600">{{ error }}</p>

    <ol v-else class="max-h-96 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800">
      <li v-for="chunk in chunks" :key="chunk.id" class="px-4 py-3">
        <div class="mb-1 flex items-center gap-2 text-xs text-gray-400">
          <span class="font-mono">#{{ chunk.chunk_index }}</span>
          <span>{{ chunk.token_count }} tokens</span>
          <span v-if="headingPath(chunk)" class="truncate text-indigo-500">{{ headingPath(chunk) }}</span>
        </div>
        <p class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">{{ chunk.content }}</p>
      </li>
    </ol>
    <p v-if="!loading && total > chunks.length" class="px-4 py-2 text-xs text-gray-400">
      Showing the first {{ chunks.length }} of {{ total }}.
    </p>
  </div>
</template>
