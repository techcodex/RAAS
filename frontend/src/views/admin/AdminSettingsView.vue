<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { api, errorMessage } from '@/lib/api'
import type { AdminSettings } from '@/lib/types'

const settings = ref<AdminSettings | null>(null)
const loading = ref(true)
const loadError = ref('')

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get<AdminSettings>('/admin/settings')
    settings.value = data
  } catch (e) {
    loadError.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}
onMounted(load)

const maxUploadMb = computed(() =>
  settings.value ? Math.round(settings.value.documents.max_size_kb / 1024) : 0,
)
</script>

<template>
  <h1 class="text-xl font-semibold">Settings</h1>
  <p class="mt-1 text-sm text-gray-500">Platform configuration.</p>

  <p v-if="loading" class="mt-6 text-sm text-gray-500">Loading…</p>
  <p v-else-if="loadError" class="mt-6 text-sm text-red-600">{{ loadError }}</p>

  <template v-else-if="settings">
    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
      <p class="text-sm font-medium">Defaults</p>
      <p class="mt-0.5 text-xs text-gray-400">
        Configured via environment variables; not editable here yet.
      </p>
      <dl class="mt-4 space-y-3 text-sm">
        <div class="flex items-baseline justify-between gap-4">
          <dt class="text-gray-500">Default document limit per organization</dt>
          <dd class="tabular-nums font-medium">
            {{ settings.organizations.default_document_limit ?? 'Unlimited' }}
          </dd>
        </div>
        <div class="flex items-baseline justify-between gap-4">
          <dt class="text-gray-500">Max upload size</dt>
          <dd class="tabular-nums font-medium">{{ maxUploadMb }} MB</dd>
        </div>
        <div class="flex items-baseline justify-between gap-4">
          <dt class="text-gray-500">Allowed file types</dt>
          <dd class="flex flex-wrap justify-end gap-1">
            <span
              v-for="ext in settings.documents.allowed_extensions"
              :key="ext"
              class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300"
            >
              {{ ext }}
            </span>
          </dd>
        </div>
      </dl>
    </div>

    <div class="mt-4 rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-400 dark:border-gray-700">
      More platform settings will appear here.
    </div>
  </template>
</template>
