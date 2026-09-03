<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'

import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const router = useRouter()

async function logout() {
  await session.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
    <header class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
      <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
        <RouterLink :to="{ name: 'projects' }" class="flex items-center gap-2 font-semibold">
          <span class="rounded bg-indigo-600 px-1.5 py-0.5 text-xs font-bold text-white">RAAS</span>
          <span class="text-sm text-gray-500 dark:text-gray-400">{{ session.organization?.name }}</span>
        </RouterLink>
        <div class="flex items-center gap-3 text-sm">
          <span class="text-gray-500 dark:text-gray-400">{{ session.user?.email }}</span>
          <button
            class="rounded-md border border-gray-300 px-2.5 py-1 text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
            @click="logout"
          >
            Sign out
          </button>
        </div>
      </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-8">
      <slot />
    </main>
  </div>
</template>
