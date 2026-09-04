<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'

import { api, errorMessage, validationErrors } from '@/lib/api'
import type { ProjectCredentialInfo } from '@/lib/types'

const props = defineProps<{ projectId: number | string }>()
const emit = defineEmits<{ changed: []; 'update:configured': [boolean] }>()

const credential = ref<ProjectCredentialInfo | null>(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const errors = ref<Record<string, string[]>>({})

const form = reactive({ api_key: '', model: 'claude-opus-5' })

const MODELS = [
  { id: 'claude-opus-5', label: 'Claude Opus 5 (most capable)' },
  { id: 'claude-sonnet-5', label: 'Claude Sonnet 5 (balanced)' },
  { id: 'claude-haiku-4-5', label: 'Claude Haiku 4.5 (fastest/cheapest)' },
]

async function load() {
  loading.value = true
  try {
    const res = await api.get<{ data: ProjectCredentialInfo }>(`/projects/${props.projectId}/credentials`)
    credential.value = res.status === 204 ? null : res.data.data
    emit('update:configured', credential.value !== null)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  errors.value = {}
  error.value = ''
  try {
    const { data } = await api.post<{ data: ProjectCredentialInfo }>(`/projects/${props.projectId}/credentials`, form)
    credential.value = data.data
    form.api_key = ''
    emit('changed')
    emit('update:configured', true)
  } catch (e) {
    errors.value = validationErrors(e)
    if (Object.keys(errors.value).length === 0) error.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!confirm('Remove the API key from this project?')) return
  await api.delete(`/projects/${props.projectId}/credentials`)
  credential.value = null
  emit('changed')
  emit('update:configured', false)
}

onMounted(load)
</script>

<template>
  <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
    <p v-if="loading" class="text-sm text-gray-500">Loading…</p>

    <div v-else-if="credential" class="flex items-center justify-between text-sm">
      <span>
        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950 dark:text-green-300">Configured</span>
        <span class="ml-2 text-gray-500">{{ credential.model }}</span>
      </span>
      <button class="text-xs text-red-600 hover:underline" @click="remove">Remove key</button>
    </div>

    <form v-else class="flex flex-wrap items-end gap-2" @submit.prevent="save">
      <label class="block text-xs">
        <span class="text-gray-600 dark:text-gray-400">Anthropic API key</span>
        <input
          v-model="form.api_key"
          type="password"
          placeholder="sk-ant-…"
          required
          class="mt-1 w-64 rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <span v-if="errors.api_key" class="block text-red-600">{{ errors.api_key[0] }}</span>
      </label>
      <label class="block text-xs">
        <span class="text-gray-600 dark:text-gray-400">Model</span>
        <select
          v-model="form.model"
          class="mt-1 rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
        >
          <option v-for="m in MODELS" :key="m.id" :value="m.id">{{ m.label }}</option>
        </select>
      </label>
      <button
        type="submit"
        :disabled="saving"
        class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
      >
        {{ saving ? 'Saving…' : 'Save key' }}
      </button>
      <span v-if="error" class="text-xs text-red-600">{{ error }}</span>
    </form>
  </div>
</template>
