<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'

import { api, errorMessage, validationErrors } from '@/lib/api'
import type { AppEmployee } from '@/lib/types'

const props = defineProps<{ projectId: number | string }>()

const employees = ref<AppEmployee[]>([])
const loading = ref(true)
const loadError = ref('')

const form = reactive({ name: '', email: '', access_code: '' })
const errors = ref<Record<string, string[]>>({})
const adding = ref(false)
const rowBusyId = ref<number | null>(null)

/** The last code created/reset — shown once so the owner can share it. */
const revealed = ref<{ email: string; code: string } | null>(null)

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get<{ data: AppEmployee[] }>(
      `/projects/${props.projectId}/publication/employees`,
    )
    employees.value = data.data
  } catch (e) {
    loadError.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function add() {
  adding.value = true
  errors.value = {}
  try {
    const payload: Record<string, unknown> = { name: form.name, email: form.email }
    if (form.access_code) payload.access_code = form.access_code
    const { data } = await api.post<{ data: AppEmployee; access_code: string }>(
      `/projects/${props.projectId}/publication/employees`,
      payload,
    )
    employees.value.push(data.data)
    employees.value.sort((a, b) => a.name.localeCompare(b.name))
    revealed.value = { email: data.data.email, code: data.access_code }
    form.name = ''
    form.email = ''
    form.access_code = ''
  } catch (e) {
    errors.value = validationErrors(e)
  } finally {
    adding.value = false
  }
}

async function toggleActive(employee: AppEmployee) {
  rowBusyId.value = employee.id
  try {
    const { data } = await api.patch<{ data: AppEmployee }>(
      `/projects/${props.projectId}/publication/employees/${employee.id}`,
      { is_active: !employee.is_active },
    )
    Object.assign(employee, data.data)
  } catch (e) {
    loadError.value = errorMessage(e)
  } finally {
    rowBusyId.value = null
  }
}

async function resetCode(employee: AppEmployee) {
  rowBusyId.value = employee.id
  try {
    const { data } = await api.post<{ access_code: string }>(
      `/projects/${props.projectId}/publication/employees/${employee.id}/reset-code`,
    )
    revealed.value = { email: employee.email, code: data.access_code }
  } catch (e) {
    loadError.value = errorMessage(e)
  } finally {
    rowBusyId.value = null
  }
}

async function remove(employee: AppEmployee) {
  if (!window.confirm(`Remove ${employee.name}? Their chat history is deleted too.`)) return
  rowBusyId.value = employee.id
  try {
    await api.delete(`/projects/${props.projectId}/publication/employees/${employee.id}`)
    employees.value = employees.value.filter((e) => e.id !== employee.id)
  } catch (e) {
    loadError.value = errorMessage(e)
  } finally {
    rowBusyId.value = null
  }
}
</script>

<template>
  <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
    <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
      <h3 class="text-sm font-medium">Employees</h3>
      <p class="mt-0.5 text-xs text-gray-500">
        Only people you add here can sign in. Each gets their own access code.
      </p>
    </div>

    <div
      v-if="revealed"
      class="mx-4 mt-3 rounded-md bg-green-50 p-3 text-sm text-green-800 dark:bg-green-950 dark:text-green-200"
    >
      Access code for <strong>{{ revealed.email }}</strong>:
      <code class="rounded bg-white/60 px-1.5 py-0.5 font-mono dark:bg-black/30">{{ revealed.code }}</code>
      — send this to them now; it won't be shown again.
      <button class="ml-2 text-xs underline" @click="revealed = null">Dismiss</button>
    </div>

    <form
      class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start"
      @submit.prevent="add"
    >
      <div class="flex-1">
        <input
          v-model="form.name"
          aria-label="Employee name"
          placeholder="Name"
          required
          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <span v-if="errors.name" class="text-xs text-red-600">{{ errors.name[0] }}</span>
      </div>
      <div class="flex-1">
        <input
          v-model="form.email"
          type="email"
          aria-label="Employee email"
          placeholder="Email"
          required
          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <span v-if="errors.email" class="text-xs text-red-600">{{ errors.email[0] }}</span>
      </div>
      <div class="sm:w-40">
        <input
          v-model="form.access_code"
          aria-label="Access code (optional)"
          placeholder="Code (optional)"
          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <span v-if="errors.access_code" class="text-xs text-red-600">{{ errors.access_code[0] }}</span>
      </div>
      <button
        type="submit"
        :disabled="adding"
        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
      >
        {{ adding ? 'Adding…' : 'Add' }}
      </button>
    </form>

    <p v-if="loading" class="px-4 py-3 text-sm text-gray-500">Loading…</p>
    <p v-else-if="loadError" class="px-4 py-3 text-sm text-red-600">{{ loadError }}</p>

    <div v-else class="overflow-x-auto border-t border-gray-100 dark:border-gray-800">
      <table class="w-full text-sm">
        <thead class="border-b border-gray-200 text-left text-xs uppercase text-gray-400 dark:border-gray-800">
          <tr>
            <th class="px-4 py-2 font-medium">Employee</th>
            <th class="px-4 py-2 font-medium">Status</th>
            <th class="px-4 py-2 font-medium">Last seen</th>
            <th class="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          <tr v-if="employees.length === 0">
            <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">
              No employees yet — add one above.
            </td>
          </tr>
          <tr v-for="e in employees" :key="e.id">
            <td class="px-4 py-2">
              <p class="font-medium">{{ e.name }}</p>
              <p class="text-xs text-gray-400">{{ e.email }}</p>
            </td>
            <td class="px-4 py-2">
              <span
                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                :class="e.is_active
                  ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                  : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'"
              >
                {{ e.is_active ? 'active' : 'deactivated' }}
              </span>
            </td>
            <td class="px-4 py-2 text-gray-500">
              {{ e.last_seen_at ? new Date(e.last_seen_at).toLocaleDateString() : '—' }}
            </td>
            <td class="px-4 py-2 text-right whitespace-nowrap">
              <button
                :disabled="rowBusyId === e.id"
                class="text-xs text-indigo-600 hover:underline disabled:opacity-50"
                @click="resetCode(e)"
              >
                Reset code
              </button>
              <button
                :disabled="rowBusyId === e.id"
                class="ml-3 text-xs text-gray-600 hover:underline disabled:opacity-50 dark:text-gray-300"
                @click="toggleActive(e)"
              >
                {{ e.is_active ? 'Deactivate' : 'Activate' }}
              </button>
              <button
                :disabled="rowBusyId === e.id"
                class="ml-3 text-xs text-red-600 hover:underline disabled:opacity-50"
                @click="remove(e)"
              >
                Remove
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
