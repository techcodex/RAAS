<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'

import AppShell from '@/components/AppShell.vue'
import { api, errorMessage, validationErrors } from '@/lib/api'
import type { Paginated, Project } from '@/lib/types'

const projects = ref<Project[]>([])
const loading = ref(true)
const loadError = ref('')

const form = reactive({ name: '', description: '' })
const errors = ref<Record<string, string[]>>({})
const creating = ref(false)

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get<Paginated<Project>>('/projects')
    projects.value = data.data
  } catch (e) {
    loadError.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

async function create() {
  creating.value = true
  errors.value = {}
  try {
    const { data } = await api.post<{ data: Project }>('/projects', { ...form })
    projects.value.unshift(data.data)
    form.name = ''
    form.description = ''
  } catch (e) {
    errors.value = validationErrors(e)
  } finally {
    creating.value = false
  }
}

onMounted(load)
</script>

<template>
  <AppShell>
    <h1 class="text-xl font-semibold">Projects</h1>

    <form
      class="mt-4 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:flex-row sm:items-start dark:border-gray-800 dark:bg-gray-900"
      @submit.prevent="create"
    >
      <div class="flex-1">
        <input
          v-model="form.name"
          placeholder="New project name"
          required
          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <span v-if="errors.name" class="text-xs text-red-600">{{ errors.name[0] }}</span>
      </div>
      <input
        v-model="form.description"
        placeholder="Description (optional)"
        class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
      />
      <button
        type="submit"
        :disabled="creating"
        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
      >
        {{ creating ? 'Creating…' : 'Create' }}
      </button>
    </form>

    <p v-if="loading" class="mt-6 text-sm text-gray-500">Loading…</p>
    <p v-else-if="loadError" class="mt-6 text-sm text-red-600">{{ loadError }}</p>
    <p v-else-if="projects.length === 0" class="mt-6 text-sm text-gray-500">
      No projects yet — create your first one above.
    </p>

    <ul v-else class="mt-6 divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 bg-white dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900">
      <li v-for="project in projects" :key="project.id">
        <RouterLink
          :to="{ name: 'project', params: { id: project.id } }"
          class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800"
        >
          <div>
            <p class="font-medium">{{ project.name }}</p>
            <p v-if="project.description" class="text-sm text-gray-500">{{ project.description }}</p>
          </div>
          <span class="text-sm text-gray-400">{{ project.documents_count ?? 0 }} docs</span>
        </RouterLink>
      </li>
    </ul>
  </AppShell>
</template>
