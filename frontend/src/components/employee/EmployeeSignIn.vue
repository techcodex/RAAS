<script setup lang="ts">
import { reactive, ref } from 'vue'

import { errorMessage, validationErrors } from '@/lib/api'
import { useEmployeeStore } from '@/stores/employee'

const store = useEmployeeStore()

const form = reactive({ email: '', access_code: '' })
const errors = ref<Record<string, string[]>>({})
const generalError = ref('')
const loading = ref(false)

async function submit() {
  loading.value = true
  errors.value = {}
  generalError.value = ''
  try {
    await store.signIn({ ...form })
  } catch (e) {
    errors.value = validationErrors(e)
    if (Object.keys(errors.value).length === 0) generalError.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-50 p-4 dark:bg-gray-950">
    <form
      class="w-full max-w-sm space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
      @submit.prevent="submit"
    >
      <div>
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
          {{ store.publication?.name }}
        </h1>
        <p v-if="store.publication?.welcome_message" class="mt-1 text-sm text-gray-500">
          {{ store.publication.welcome_message }}
        </p>
      </div>

      <p
        v-if="generalError"
        class="rounded-md bg-red-50 p-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300"
      >
        {{ generalError }}
      </p>

      <label class="block text-sm">
        <span class="text-gray-700 dark:text-gray-300">Work email</span>
        <input
          v-model="form.email"
          type="email"
          autocomplete="email"
          required
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <span v-if="errors.email" class="text-xs text-red-600">{{ errors.email[0] }}</span>
      </label>

      <label class="block text-sm">
        <span class="text-gray-700 dark:text-gray-300">Access code</span>
        <input
          v-model="form.access_code"
          autocomplete="off"
          required
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <span v-if="errors.access_code" class="text-xs text-red-600">{{ errors.access_code[0] }}</span>
      </label>

      <button
        type="submit"
        :disabled="loading"
        class="w-full rounded-md bg-indigo-600 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
      >
        {{ loading ? 'Signing in…' : 'Enter' }}
      </button>
    </form>
  </div>
</template>
