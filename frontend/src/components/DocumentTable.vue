<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

import StatusBadge from '@/components/StatusBadge.vue'
import { api, errorMessage } from '@/lib/api'
import type { DocumentFile, Paginated } from '@/lib/types'

const props = defineProps<{ projectId: number | string }>()
const emit = defineEmits<{
  process: [DocumentFile]
  preview: [DocumentFile]
  settled: []
}>()

const documents = ref<DocumentFile[]>([])
const loading = ref(true)
const error = ref('')
let poll: ReturnType<typeof setInterval> | undefined

const hasProcessing = computed(() =>
  documents.value.some((d) => ['queued', 'chunking', 'embedding'].includes(d.status)),
)
const uploadedCount = computed(() => documents.value.filter((d) => d.status === 'uploaded').length)

async function load() {
  try {
    const wasProcessing = hasProcessing.value
    const { data } = await api.get<Paginated<DocumentFile>>(`/projects/${props.projectId}/documents`)
    documents.value = data.data
    error.value = ''
    // Processing just finished for the batch — let the parent refresh project state.
    if (wasProcessing && !hasProcessing.value) emit('settled')
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

function prepend(added: DocumentFile[]) {
  documents.value = [...added, ...documents.value]
}

function patch(updated: DocumentFile) {
  documents.value = documents.value.map((d) => (d.id === updated.id ? updated : d))
}

async function remove(doc: DocumentFile) {
  if (!confirm(`Delete "${doc.original_filename}"?`)) return
  const previous = documents.value
  documents.value = documents.value.filter((d) => d.id !== doc.id)
  try {
    await api.delete(`/documents/${doc.id}`)
  } catch (e) {
    documents.value = previous
    error.value = errorMessage(e, 'Could not delete document')
  }
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

onMounted(() => {
  load()
  poll = setInterval(() => {
    if (hasProcessing.value) load()
  }, 3000)
})
onBeforeUnmount(() => clearInterval(poll))

defineExpose({ prepend, patch, reload: load, uploadedCount, documents })
</script>

<template>
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
    <p v-if="loading" class="p-4 text-sm text-gray-500">Loading documents…</p>
    <p v-else-if="error" class="p-4 text-sm text-red-600">{{ error }}</p>
    <p v-else-if="documents.length === 0" class="p-4 text-sm text-gray-500">No documents uploaded yet.</p>

    <table v-else class="w-full text-sm">
      <thead class="border-b border-gray-200 text-left text-xs uppercase text-gray-400 dark:border-gray-800">
        <tr>
          <th class="px-4 py-2 font-medium">Name</th>
          <th class="px-4 py-2 font-medium">Size</th>
          <th class="px-4 py-2 font-medium">Chunks</th>
          <th class="px-4 py-2 font-medium">Status</th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        <tr v-for="doc in documents" :key="doc.id" data-testid="document-row">
          <td class="px-4 py-2">
            <button
              v-if="doc.status === 'ready'"
              class="font-medium text-indigo-600 hover:underline"
              @click="emit('preview', doc)"
            >
              {{ doc.original_filename }}
            </button>
            <span v-else class="font-medium">{{ doc.original_filename }}</span>
            <span v-if="doc.error_message" class="block text-xs text-red-600">{{ doc.error_message }}</span>
          </td>
          <td class="px-4 py-2 text-gray-500">{{ formatSize(doc.size_bytes) }}</td>
          <td class="px-4 py-2 text-gray-500">{{ doc.chunk_count || '—' }}</td>
          <td class="px-4 py-2"><StatusBadge :status="doc.status" /></td>
          <td class="px-4 py-2 text-right whitespace-nowrap">
            <button
              v-if="['uploaded', 'ready', 'failed'].includes(doc.status)"
              class="text-xs text-indigo-600 hover:underline"
              @click="emit('process', doc)"
            >
              {{ doc.status === 'uploaded' ? 'Process' : 'Reprocess' }}
            </button>
            <button class="ml-3 text-xs text-red-600 hover:underline" @click="remove(doc)">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
