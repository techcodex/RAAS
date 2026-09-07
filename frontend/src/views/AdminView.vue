<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'

import AdminShell from '@/components/AdminShell.vue'
import { api, errorMessage, validationErrors } from '@/lib/api'
import type { AdminOrganization, Paginated } from '@/lib/types'

const organizations = ref<AdminOrganization[]>([])
const loading = ref(true)
const loadError = ref('')

const form = reactive<{ name: string; document_limit: string }>({ name: '', document_limit: '' })
const errors = ref<Record<string, string[]>>({})
const creating = ref(false)

/** Row id currently being edited, plus the draft limit value. */
const editingId = ref<number | null>(null)
const editValue = ref('')
const savingId = ref<number | null>(null)
const rowError = ref('')

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get<Paginated<AdminOrganization>>('/admin/organizations')
    organizations.value = data.data
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
    const payload: Record<string, unknown> = { name: form.name }
    if (form.document_limit !== '') payload.document_limit = Number(form.document_limit)
    const { data } = await api.post<{ data: AdminOrganization }>('/admin/organizations', payload)
    organizations.value.push(data.data)
    organizations.value.sort((a, b) => a.name.localeCompare(b.name))
    form.name = ''
    form.document_limit = ''
  } catch (e) {
    errors.value = validationErrors(e)
  } finally {
    creating.value = false
  }
}

function startEdit(org: AdminOrganization) {
  editingId.value = org.id
  editValue.value = org.document_limit === null ? '' : String(org.document_limit)
  rowError.value = ''
}

function cancelEdit() {
  editingId.value = null
  editValue.value = ''
  rowError.value = ''
}

async function saveLimit(org: AdminOrganization) {
  savingId.value = org.id
  rowError.value = ''
  try {
    const document_limit = editValue.value === '' ? null : Number(editValue.value)
    const { data } = await api.patch<{ data: AdminOrganization }>(
      `/admin/organizations/${org.id}`,
      { document_limit },
    )
    Object.assign(org, data.data)
    editingId.value = null
  } catch (e) {
    rowError.value = validationErrors(e).document_limit?.[0] ?? errorMessage(e)
  } finally {
    savingId.value = null
  }
}

onMounted(load)
</script>

<template>
  <AdminShell>
    <h1 class="text-xl font-semibold">Organizations</h1>
    <p class="mt-1 text-sm text-gray-500">
      Platform administration — every organization on this deployment and its document limit.
    </p>

    <form
      class="mt-4 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:flex-row sm:items-start dark:border-gray-800 dark:bg-gray-900"
      @submit.prevent="create"
    >
      <div class="flex-1">
        <input
          v-model="form.name"
          placeholder="New organization name"
          required
          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <span v-if="errors.name" class="text-xs text-red-600">{{ errors.name[0] }}</span>
      </div>
      <div class="sm:w-48">
        <input
          v-model="form.document_limit"
          type="number"
          min="0"
          placeholder="Doc limit (blank = default)"
          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <span v-if="errors.document_limit" class="text-xs text-red-600">
          {{ errors.document_limit[0] }}
        </span>
      </div>
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

    <div
      v-else
      class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
    >
      <table class="w-full text-sm">
        <thead class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800">
          <tr>
            <th class="px-4 py-2.5 font-medium">Organization</th>
            <th class="px-4 py-2.5 font-medium">Owner</th>
            <th class="px-4 py-2.5 font-medium text-right">Projects</th>
            <th class="px-4 py-2.5 font-medium text-right">Documents</th>
            <th class="px-4 py-2.5 font-medium">Limit</th>
            <th class="px-4 py-2.5"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
          <tr v-for="org in organizations" :key="org.id">
            <td class="px-4 py-3">
              <p class="font-medium">{{ org.name }}</p>
              <p class="text-xs text-gray-400">{{ org.slug }}</p>
            </td>
            <td class="px-4 py-3 text-gray-500">{{ org.owner.email ?? '—' }}</td>
            <td class="px-4 py-3 text-right text-gray-500">{{ org.projects_count }}</td>
            <td class="px-4 py-3 text-right">
              <span
                :class="
                  org.effective_document_limit !== null &&
                  org.documents_count >= org.effective_document_limit
                    ? 'text-red-600 font-medium'
                    : 'text-gray-500'
                "
              >
                {{ org.documents_count }}<template v-if="org.effective_document_limit !== null">
                  / {{ org.effective_document_limit }}</template>
              </span>
            </td>
            <td class="px-4 py-3">
              <template v-if="editingId === org.id">
                <input
                  v-model="editValue"
                  type="number"
                  min="0"
                  placeholder="default"
                  class="w-24 rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
                  @keyup.enter="saveLimit(org)"
                  @keyup.esc="cancelEdit"
                />
                <span v-if="rowError" class="ml-2 text-xs text-red-600">{{ rowError }}</span>
              </template>
              <span v-else class="text-gray-500">
                {{ org.document_limit ?? 'default' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <template v-if="editingId === org.id">
                <button
                  :disabled="savingId === org.id"
                  class="rounded-md bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                  @click="saveLimit(org)"
                >
                  {{ savingId === org.id ? 'Saving…' : 'Save' }}
                </button>
                <button
                  class="ml-1.5 rounded-md border border-gray-300 px-2.5 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                  @click="cancelEdit"
                >
                  Cancel
                </button>
              </template>
              <button
                v-else
                class="rounded-md border border-gray-300 px-2.5 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                @click="startEdit(org)"
              >
                Edit limit
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminShell>
</template>
