<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { errorMessage, validationErrors } from '@/lib/api'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const router = useRouter()
const route = useRoute()

const form = reactive({ email: '', password: '' })
const errors = ref<Record<string, string[]>>({})
const generalError = ref('')
const loading = ref(false)

async function submit() {
  loading.value = true
  errors.value = {}
  generalError.value = ''
  try {
    await session.login({ ...form })
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
    router.push(redirect)
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
        <span class="rounded bg-indigo-600 px-1.5 py-0.5 text-xs font-bold text-white">RAAS</span>
        <h1 class="mt-3 text-lg font-semibold text-gray-900 dark:text-gray-100">Sign in</h1>
      </div>

      <p v-if="generalError" class="rounded-md bg-red-50 p-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
        {{ generalError }}
      </p>

      <label class="block text-sm">
        <span class="text-gray-700 dark:text-gray-300">Email</span>
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
        <span class="text-gray-700 dark:text-gray-300">Password</span>
        <input
          v-model="form.password"
          type="password"
          autocomplete="current-password"
          required
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
      </label>

      <button
        type="submit"
        :disabled="loading"
        class="w-full rounded-md bg-indigo-600 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
      >
        {{ loading ? 'Signing in…' : 'Sign in' }}
      </button>

      <p class="text-center text-sm text-gray-500 dark:text-gray-400">
        No account?
        <RouterLink :to="{ name: 'register' }" class="text-indigo-600 hover:underline">Create one</RouterLink>
      </p>
    </form>
  </div>
</template>
