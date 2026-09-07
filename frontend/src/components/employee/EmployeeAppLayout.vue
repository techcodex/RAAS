<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterView, useRoute, useRouter } from 'vue-router'

import { setAppUnauthorizedHandler } from '@/lib/api'
import { useEmployeeStore } from '@/stores/employee'

import ConversationSidebar from './ConversationSidebar.vue'
import EmployeeSignIn from './EmployeeSignIn.vue'

const store = useEmployeeStore()
const route = useRoute()
const router = useRouter()

const drawerOpen = ref(false)

async function boot(slug: string) {
  await store.load(slug)
  if (store.signedIn) {
    try {
      await store.loadConversations()
    } catch {
      /* the 401 handler below deals with an expired token */
    }
  }
}

watch(
  () => route.params.slug,
  (slug) => {
    if (typeof slug === 'string') boot(slug)
  },
  { immediate: true },
)

watch(() => route.fullPath, () => {
  drawerOpen.value = false
})

watch(
  () => store.signedIn,
  (signedIn) => {
    if (signedIn) store.loadConversations().catch(() => {})
  },
)

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') drawerOpen.value = false
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
  setAppUnauthorizedHandler(() => {
    store.clear()
    if (route.name !== 'employee-app') {
      router.replace({ name: 'employee-app', params: { slug: store.slug } })
    }
  })
})
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

async function signOut() {
  await store.signOut()
  router.replace({ name: 'employee-app', params: { slug: store.slug } })
}
</script>

<template>
  <p v-if="!store.ready" class="p-8 text-sm text-gray-500">Loading…</p>

  <p v-else-if="store.loadError" class="p-8 text-sm text-gray-600 dark:text-gray-300">
    {{ store.loadError }}
  </p>

  <EmployeeSignIn v-else-if="!store.signedIn" />

  <div v-else class="flex h-screen bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
    <aside
      class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-gray-200 bg-white transition-transform motion-reduce:transition-none md:static md:translate-x-0 dark:border-gray-800 dark:bg-gray-900"
      :class="drawerOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex h-14 items-center border-b border-gray-200 px-4 dark:border-gray-800">
        <span class="truncate text-sm font-semibold">{{ store.publication?.name }}</span>
      </div>
      <ConversationSidebar class="min-h-0 flex-1" @navigate="drawerOpen = false" />
      <div class="border-t border-gray-200 p-3 text-sm dark:border-gray-800">
        <button
          class="w-full rounded-md px-2 py-1.5 text-left text-gray-600 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-gray-400 dark:hover:bg-gray-800"
          @click="signOut"
        >
          Sign out
        </button>
      </div>
    </aside>

    <div
      v-if="drawerOpen"
      class="fixed inset-0 z-30 bg-gray-900/40 md:hidden"
      aria-hidden="true"
      @click="drawerOpen = false"
    />

    <div class="flex min-w-0 flex-1 flex-col">
      <header
        class="flex h-14 items-center gap-2 border-b border-gray-200 px-4 md:hidden dark:border-gray-800"
      >
        <button
          class="-ml-1 rounded-md p-1.5 text-gray-500 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:hover:bg-gray-800"
          aria-label="Open menu"
          @click="drawerOpen = true"
        >
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" stroke-linecap="round" />
          </svg>
        </button>
        <span class="truncate text-sm font-semibold">{{ store.publication?.name }}</span>
      </header>

      <RouterView />
    </div>
  </div>
</template>
