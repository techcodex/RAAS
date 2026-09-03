<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef } from 'vue'
import { RouterLink } from 'vue-router'

import AppShell from '@/components/AppShell.vue'
import ChunkPreview from '@/components/ChunkPreview.vue'
import DocumentTable from '@/components/DocumentTable.vue'
import DocumentUploader from '@/components/DocumentUploader.vue'
import StrategyPicker from '@/components/StrategyPicker.vue'
import { api, errorMessage } from '@/lib/api'
import type { DocumentFile, Project } from '@/lib/types'

const props = defineProps<{ id: string }>()

const project = ref<Project | null>(null)
const loading = ref(true)
const error = ref('')
const actionError = ref('')
const exporting = ref(false)
const previewed = ref<DocumentFile | null>(null)

const strategy = ref('auto')
const strategyConfig = ref<Record<string, unknown>>({})

const table = useTemplateRef<InstanceType<typeof DocumentTable>>('table')
const canExport = computed(() => project.value?.embedder.bound_model_id != null)

async function load(showSpinner = true) {
  if (showSpinner) loading.value = true
  try {
    const { data } = await api.get<{ data: Project }>(`/projects/${props.id}`)
    project.value = data.data
    if (showSpinner) strategy.value = data.data.chunking_strategy ?? 'auto'
    error.value = ''
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

function onUploaded(documents: DocumentFile[]) {
  table.value?.prepend(documents)
}

async function process(doc: DocumentFile) {
  actionError.value = ''
  try {
    const { data } = await api.post<{ data: DocumentFile }>(`/documents/${doc.id}/process`, {
      strategy: strategy.value,
      strategy_config: strategyConfig.value,
    })
    table.value?.patch(data.data)
  } catch (e) {
    actionError.value = errorMessage(e, 'Could not start processing')
  }
}

async function processAll() {
  const pending = (table.value?.documents ?? []).filter((d: DocumentFile) => d.status === 'uploaded')
  for (const doc of pending) await process(doc)
}

async function exportEmbeddings() {
  if (!project.value) return
  exporting.value = true
  actionError.value = ''
  try {
    const res = await api.get(`/projects/${project.value.id}/export`, { responseType: 'blob' })
    const url = URL.createObjectURL(res.data as Blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${project.value.name.replace(/\s+/g, '-').toLowerCase()}-embeddings.ndjson`
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    actionError.value = errorMessage(e, 'Export failed')
  } finally {
    exporting.value = false
  }
}

onMounted(load)
</script>

<template>
  <AppShell>
    <RouterLink :to="{ name: 'projects' }" class="text-sm text-indigo-600 hover:underline">← Projects</RouterLink>

    <p v-if="loading" class="mt-4 text-sm text-gray-500">Loading…</p>
    <p v-else-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>

    <template v-else-if="project">
      <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold">{{ project.name }}</h1>
          <p v-if="project.description" class="text-sm text-gray-500">{{ project.description }}</p>
          <p v-if="project.embedder.bound_model_id" class="mt-1 text-xs text-gray-400">
            Embeddings: {{ project.embedder.bound_model_id }} ({{ project.embedder.dimension }}d)
          </p>
        </div>
        <button
          :disabled="!canExport || exporting"
          class="rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-100 disabled:opacity-40 dark:border-gray-700 dark:hover:bg-gray-800"
          :title="canExport ? '' : 'Process a document first'"
          @click="exportEmbeddings"
        >
          {{ exporting ? 'Exporting…' : 'Export embeddings' }}
        </button>
      </div>

      <section class="mt-6">
        <DocumentUploader :project-id="project.id" @uploaded="onUploaded" />
      </section>

      <section class="mt-6 space-y-3">
        <StrategyPicker v-model:strategy="strategy" v-model:config="strategyConfig" />
        <div class="flex items-center gap-3">
          <button
            class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
            @click="processAll"
          >
            Process uploaded documents
          </button>
          <span v-if="actionError" class="text-sm text-red-600">{{ actionError }}</span>
        </div>
      </section>

      <section class="mt-4">
        <DocumentTable
          ref="table"
          :project-id="project.id"
          @process="process"
          @preview="previewed = $event"
          @settled="load(false)"
        />
      </section>

      <section v-if="previewed" class="mt-4">
        <ChunkPreview :document="previewed" @close="previewed = null" />
      </section>
    </template>
  </AppShell>
</template>
