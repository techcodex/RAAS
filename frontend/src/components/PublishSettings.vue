<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'

import PublicationEmployees from '@/components/PublicationEmployees.vue'
import { api, errorMessage, validationErrors } from '@/lib/api'
import type { PublicationInfo } from '@/lib/types'

const props = defineProps<{ projectId: number | string }>()

const publication = ref<PublicationInfo | null>(null)
const loading = ref(true)
const loadError = ref('')
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const generalError = ref('')
const copied = ref(false)

const form = reactive({
  welcome_message: '',
  suggested_questions: '',
  daily_query_limit: '',
})

const publicUrl = computed(() =>
  publication.value ? `${window.location.origin}/app/${publication.value.slug}` : '',
)

function syncForm(p: PublicationInfo | null) {
  form.welcome_message = p?.welcome_message ?? ''
  form.suggested_questions = (p?.suggested_questions ?? []).join('\n')
  form.daily_query_limit = p?.daily_query_limit != null ? String(p.daily_query_limit) : ''
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get<{ data: PublicationInfo | null }>(
      `/projects/${props.projectId}/publication`,
    )
    publication.value = data.data
    syncForm(data.data)
  } catch (e) {
    loadError.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function save(overrides: Record<string, unknown> = {}) {
  saving.value = true
  errors.value = {}
  generalError.value = ''
  try {
    const payload: Record<string, unknown> = {
      welcome_message: form.welcome_message || null,
      suggested_questions: form.suggested_questions
        .split('\n')
        .map((s) => s.trim())
        .filter(Boolean),
      daily_query_limit: form.daily_query_limit === '' ? null : Number(form.daily_query_limit),
      ...overrides,
    }
    const { data } = await api.put<{ data: PublicationInfo }>(
      `/projects/${props.projectId}/publication`,
      payload,
    )
    publication.value = data.data
    syncForm(data.data)
  } catch (e) {
    errors.value = validationErrors(e)
    if (Object.keys(errors.value).length === 0) generalError.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

function toggleActive() {
  save({ is_active: !publication.value?.is_active })
}

async function copyLink() {
  try {
    await navigator.clipboard.writeText(publicUrl.value)
    copied.value = true
    setTimeout(() => (copied.value = false), 1500)
  } catch {
    /* clipboard unavailable */
  }
}
</script>

<template>
  <p v-if="loading" class="text-sm text-gray-500">Loading…</p>
  <p v-else-if="loadError" class="text-sm text-red-600">{{ loadError }}</p>

  <div v-else class="space-y-4">
    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 class="text-sm font-medium">Employee app</h3>
          <p class="mt-0.5 text-xs text-gray-500">
            Publish this project so the employees you add can ask questions against its documents.
          </p>
        </div>
        <button
          v-if="publication"
          :disabled="saving"
          class="rounded-md border border-gray-300 px-2.5 py-1 text-xs text-gray-700 hover:bg-gray-100 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
          @click="toggleActive"
        >
          {{ publication.is_active ? 'Deactivate' : 'Activate' }}
        </button>
      </div>

      <div
        v-if="publication"
        class="mt-3 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3 dark:border-gray-800"
      >
        <span
          class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
          :class="publication.is_active
            ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
            : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'"
        >
          {{ publication.is_active ? 'Live' : 'Draft' }}
        </span>
        <code class="truncate rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-800">{{ publicUrl }}</code>
        <button class="text-xs text-indigo-600 hover:underline" @click="copyLink">
          {{ copied ? 'Copied' : 'Copy link' }}
        </button>
      </div>

      <p v-if="generalError" class="mt-3 text-sm text-red-600">{{ generalError }}</p>
    </div>

    <PublicationEmployees v-if="publication" :project-id="projectId" />

    <form
      class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
      @submit.prevent="save()"
    >
      <label class="block text-sm">
        <span class="text-gray-700 dark:text-gray-300">Welcome message</span>
        <textarea
          v-model="form.welcome_message"
          rows="2"
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
      </label>

      <label class="block text-sm">
        <span class="text-gray-700 dark:text-gray-300">Suggested questions</span>
        <span class="block text-xs text-gray-400">One per line, up to 6.</span>
        <textarea
          v-model="form.suggested_questions"
          rows="4"
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <span v-if="errors.suggested_questions" class="text-xs text-red-600">
          {{ errors.suggested_questions[0] }}
        </span>
      </label>

      <label class="block text-sm">
        <span class="text-gray-700 dark:text-gray-300">Daily questions per employee</span>
        <input
          v-model="form.daily_query_limit"
          type="number"
          min="1"
          placeholder="No limit"
          class="mt-1 w-40 rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
      </label>

      <button
        type="submit"
        :disabled="saving"
        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
      >
        {{ saving ? 'Saving…' : publication ? 'Save' : 'Create draft' }}
      </button>
    </form>
  </div>
</template>
