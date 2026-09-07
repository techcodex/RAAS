<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { api, errorMessage } from '@/lib/api'
import type { ProjectEmbedder } from '@/lib/types'
import { useStrategiesStore } from '@/stores/strategies'

const props = defineProps<{ projectId: number; embedder: ProjectEmbedder }>()
const emit = defineEmits<{ changed: [] }>()

const store = useStrategiesStore()
onMounted(() => store.ensureLoaded())

const models = computed(() => {
  const local = store.catalogue?.embedders.find((e) => e.provider === 'local')
  return local?.models ?? []
})

const current = computed(
  () => props.embedder.bound_model_id ?? props.embedder.model ?? models.value[0]?.id ?? '',
)
const busy = computed(
  () => props.embedder.reembed_status === 'queued' || props.embedder.reembed_status === 'running',
)

const saving = ref(false)
const error = ref('')

async function choose(event: Event) {
  const model = (event.target as HTMLSelectElement).value
  if (model === current.value) return
  saving.value = true
  error.value = ''
  try {
    await api.patch(`/projects/${props.projectId}`, { embedder_model: model })
    emit('changed')
  } catch (e) {
    error.value = errorMessage(e, 'Could not change the embedding model')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
    <div class="flex flex-wrap items-center gap-2">
      <label class="text-sm font-medium">Embedding model</label>
      <select
        class="rounded-md border border-gray-300 px-2 py-1 text-sm disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800"
        :value="current"
        :disabled="saving || busy"
        @change="choose"
      >
        <option v-for="m in models" :key="m.id" :value="m.id">{{ m.id }} ({{ m.dimension }}d)</option>
      </select>
    </div>

    <p v-if="embedder.bound_model_id" class="mt-1 text-xs text-gray-500">
      Changing this re-embeds every document in the project.
    </p>

    <p v-if="busy" class="mt-1 text-xs text-amber-600 dark:text-amber-500">
      Re-embedding documents… querying is paused until this finishes.
    </p>
    <p v-else-if="embedder.reembed_status === 'failed'" class="mt-1 text-xs text-red-600">
      Last re-embed failed: {{ embedder.reembed_error }} — pick a model to try again.
    </p>
    <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
  </div>
</template>
