<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { api, errorMessage, validationErrors } from '@/lib/api'
import type { AdminOrganization, OrganizationStatus, Paginated } from '@/lib/types'

const route = useRoute()
const router = useRouter()

const organizations = ref<AdminOrganization[]>([])
const meta = ref<Paginated<AdminOrganization>['meta'] | null>(null)
const page = computed(() => Math.max(1, Number(route.query.page) || 1))
const loading = ref(true)
const loadError = ref('')

const form = reactive({ name: '', document_limit: '', owner_name: '', owner_email: '' })
const errors = ref<Record<string, string[]>>({})
const creating = ref(false)

/** Credentials for the just-created org owner — shown once. */
const provisioned = ref<{ email: string; password: string } | null>(null)

const editingId = ref<number | null>(null)
const editValue = ref('')
const savingId = ref<number | null>(null)
const statusSavingId = ref<number | null>(null)
const rowError = ref('')

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get<Paginated<AdminOrganization>>('/admin/organizations', {
      params: { page: page.value },
    })
    organizations.value = data.data
    meta.value = data.meta
  } catch (e) {
    loadError.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}
watch(page, load, { immediate: true })

function goToPage(next: number) {
  router.push({ query: next > 1 ? { page: String(next) } : {} })
}

async function create() {
  creating.value = true
  errors.value = {}
  try {
    const payload: Record<string, unknown> = {
      name: form.name,
      owner_name: form.owner_name,
      owner_email: form.owner_email,
    }
    if (form.document_limit !== '') payload.document_limit = Number(form.document_limit)
    const { data } = await api.post<{ temporary_password: string }>(
      '/admin/organizations',
      payload,
    )
    provisioned.value = { email: form.owner_email, password: data.temporary_password }
    form.name = ''
    form.document_limit = ''
    form.owner_name = ''
    form.owner_email = ''
    if (page.value === 1) {
      await load()
    } else {
      router.push({ query: {} })
    }
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

async function toggleStatus(org: AdminOrganization) {
  const next: OrganizationStatus = org.status === 'active' ? 'disabled' : 'active'
  if (
    next === 'disabled' &&
    !window.confirm(`Disable ${org.name}? Its users will be signed out and blocked from signing in.`)
  ) {
    return
  }
  statusSavingId.value = org.id
  rowError.value = ''
  try {
    const { data } = await api.patch<{ data: AdminOrganization }>(
      `/admin/organizations/${org.id}/status`,
      { status: next },
    )
    Object.assign(org, data.data)
  } catch (e) {
    rowError.value = errorMessage(e, 'Could not change the organization status')
  } finally {
    statusSavingId.value = null
  }
}
</script>

<template>
  <h1 class="text-xl font-semibold">Organizations</h1>
  <p class="mt-1 text-sm text-gray-500">
    Every organization on this deployment — document limits and access.
  </p>

  <div
    v-if="provisioned"
    class="mt-4 rounded-md bg-green-50 p-3 text-sm text-green-800 dark:bg-green-950 dark:text-green-200"
  >
    Owner sign-in for <strong>{{ provisioned.email }}</strong> —
    <code class="rounded bg-white/60 px-1.5 py-0.5 font-mono dark:bg-black/30">{{ provisioned.password }}</code>
    Send this to them now; the password won't be shown again.
    <button class="ml-2 text-xs underline" @click="provisioned = null">Dismiss</button>
  </div>

  <form
    class="mt-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
    @submit.prevent="create"
  >
    <p class="text-sm font-medium">New organization</p>
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
      <label class="block text-sm">
        <span class="text-gray-700 dark:text-gray-300">Organization name</span>
        <input
          v-model="form.name"
          autocomplete="off"
          required
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <span v-if="errors.name" class="text-xs text-red-600">{{ errors.name[0] }}</span>
      </label>
      <label class="block text-sm">
        <span class="text-gray-700 dark:text-gray-300">Document limit</span>
        <input
          v-model="form.document_limit"
          type="number"
          min="0"
          placeholder="Blank = platform default"
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <span v-if="errors.document_limit" class="text-xs text-red-600">{{ errors.document_limit[0] }}</span>
      </label>
      <label class="block text-sm">
        <span class="text-gray-700 dark:text-gray-300">Owner name</span>
        <input
          v-model="form.owner_name"
          autocomplete="off"
          required
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <span v-if="errors.owner_name" class="text-xs text-red-600">{{ errors.owner_name[0] }}</span>
      </label>
      <label class="block text-sm">
        <span class="text-gray-700 dark:text-gray-300">Owner email</span>
        <input
          v-model="form.owner_email"
          type="email"
          autocomplete="off"
          required
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <span v-if="errors.owner_email" class="text-xs text-red-600">{{ errors.owner_email[0] }}</span>
      </label>
    </div>
    <button
      type="submit"
      :disabled="creating"
      class="mt-3 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
    >
      {{ creating ? 'Creating…' : 'Create organization' }}
    </button>
  </form>

  <p v-if="loading" class="mt-6 text-sm text-gray-500">Loading…</p>
  <p v-else-if="loadError" class="mt-6 text-sm text-red-600">{{ loadError }}</p>

  <template v-else>
    <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
      <table class="w-full text-sm">
        <thead class="border-b border-gray-200 text-left text-xs uppercase text-gray-400 dark:border-gray-800">
          <tr>
            <th class="px-4 py-2 font-medium">Organization</th>
            <th class="px-4 py-2 font-medium">Owner</th>
            <th class="px-4 py-2 font-medium">Status</th>
            <th class="px-4 py-2 font-medium text-right">Projects</th>
            <th class="px-4 py-2 font-medium text-right">Documents</th>
            <th class="px-4 py-2 font-medium">Limit</th>
            <th class="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          <tr v-if="organizations.length === 0">
            <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">
              No organizations yet.
            </td>
          </tr>
          <tr v-for="org in organizations" :key="org.id">
            <td class="px-4 py-2">
              <p class="font-medium">{{ org.name }}</p>
              <p class="text-xs text-gray-400">{{ org.slug }}</p>
            </td>
            <td class="px-4 py-2 text-gray-500">{{ org.owner.email ?? '—' }}</td>
            <td class="px-4 py-2">
              <span
                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                :class="org.status === 'active'
                  ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                  : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'"
              >
                {{ org.status }}
              </span>
            </td>
            <td class="px-4 py-2 text-right text-gray-500">{{ org.projects_count }}</td>
            <td class="px-4 py-2 text-right">
              <span
                :class="
                  org.effective_document_limit !== null &&
                  org.documents_count >= org.effective_document_limit
                    ? 'font-medium text-red-600'
                    : 'text-gray-500'
                "
              >
                {{ org.documents_count }}<template v-if="org.effective_document_limit !== null">
                  / {{ org.effective_document_limit }}</template>
              </span>
            </td>
            <td class="px-4 py-2">
              <template v-if="editingId === org.id">
                <input
                  v-model="editValue"
                  type="number"
                  min="0"
                  :aria-label="`Document limit for ${org.name}`"
                  placeholder="default"
                  class="w-24 rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
                  @keyup.enter="saveLimit(org)"
                  @keyup.esc="cancelEdit"
                />
              </template>
              <span v-else class="text-gray-500">{{ org.document_limit ?? 'default' }}</span>
            </td>
            <td class="px-4 py-2 text-right whitespace-nowrap">
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
              <template v-else>
                <button
                  class="rounded-md border border-gray-300 px-2.5 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                  @click="startEdit(org)"
                >
                  Edit limit
                </button>
                <button
                  :disabled="statusSavingId === org.id"
                  class="ml-1.5 rounded-md border border-gray-300 px-2.5 py-1 text-xs text-gray-700 hover:bg-gray-100 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                  @click="toggleStatus(org)"
                >
                  {{ statusSavingId === org.id ? '…' : org.status === 'active' ? 'Disable' : 'Enable' }}
                </button>
              </template>
              <p v-if="rowError && (editingId === org.id || statusSavingId === org.id)" class="mt-1 text-xs text-red-600">
                {{ rowError }}
              </p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="meta && meta.last_page > 1"
      class="mt-3 flex items-center justify-between text-sm text-gray-500"
    >
      <span>Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} total</span>
      <span class="flex gap-2">
        <button
          :disabled="meta.current_page <= 1"
          class="rounded-md border border-gray-300 px-2.5 py-1 text-xs text-gray-700 hover:bg-gray-100 disabled:opacity-40 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
          @click="goToPage(meta.current_page - 1)"
        >
          Previous
        </button>
        <button
          :disabled="meta.current_page >= meta.last_page"
          class="rounded-md border border-gray-300 px-2.5 py-1 text-xs text-gray-700 hover:bg-gray-100 disabled:opacity-40 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
          @click="goToPage(meta.current_page + 1)"
        >
          Next
        </button>
      </span>
    </div>
  </template>
</template>
