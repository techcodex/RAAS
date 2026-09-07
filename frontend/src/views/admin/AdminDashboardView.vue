<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import StatCard from '@/components/StatCard.vue'
import { api, errorMessage } from '@/lib/api'
import type { AdminStats, DocumentStatus } from '@/lib/types'

const stats = ref<AdminStats | null>(null)
const loading = ref(true)
const loadError = ref('')

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get<AdminStats>('/admin/stats')
    stats.value = data
  } catch (e) {
    loadError.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}
onMounted(load)

const statusOrder: DocumentStatus[] = [
  'uploaded',
  'queued',
  'chunking',
  'embedding',
  'ready',
  'failed',
]

const statusStyles: Record<DocumentStatus, string> = {
  uploaded: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
  queued: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
  chunking: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  embedding: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  ready: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
  failed: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
}

const docsByStatus = computed(() =>
  statusOrder.map((status) => ({ status, count: stats.value?.documents.by_status[status] ?? 0 })),
)
</script>

<template>
  <h1 class="text-xl font-semibold">Dashboard</h1>
  <p class="mt-1 text-sm text-gray-500">Platform-wide activity across every organization.</p>

  <p v-if="loading" class="mt-6 text-sm text-gray-500">Loading…</p>
  <p v-else-if="loadError" class="mt-6 text-sm text-red-600">{{ loadError }}</p>

  <template v-else-if="stats">
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <StatCard
        label="Organizations"
        :value="stats.organizations.total"
        :hint="`${stats.organizations.active} active · ${stats.organizations.disabled} disabled`"
      />
      <StatCard label="Projects" :value="stats.projects.total" />
      <StatCard
        label="Documents"
        :value="stats.documents.total"
        :hint="`${stats.documents.by_status.ready} ready · ${stats.documents.by_status.failed} failed`"
      />
    </div>

    <div class="mt-6 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
      <p class="text-sm font-medium">Documents by status</p>
      <div class="mt-3 flex flex-wrap gap-2">
        <span
          v-for="row in docsByStatus"
          :key="row.status"
          class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
          :class="statusStyles[row.status]"
        >
          {{ row.status }}
          <span class="tabular-nums opacity-80">{{ row.count }}</span>
        </span>
      </div>
    </div>
  </template>
</template>
