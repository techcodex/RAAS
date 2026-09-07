<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterView, useRoute, useRouter } from 'vue-router'

import AdminSidebar from '@/components/AdminSidebar.vue'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const router = useRouter()
const route = useRoute()

const drawerOpen = ref(false)

watch(() => route.fullPath, () => {
  drawerOpen.value = false
})

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') drawerOpen.value = false
}
onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

async function logout() {
  await session.logout()
  router.push({ name: 'admin-login' })
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
    <div class="mx-auto flex min-h-screen">
      <!-- Sidebar: static on desktop, drawer on mobile -->
      <aside
        class="fixed inset-y-0 left-0 z-40 w-60 border-r border-gray-200 bg-white transition-transform motion-reduce:transition-none md:sticky md:top-0 md:h-screen md:translate-x-0 dark:border-gray-800 dark:bg-gray-900"
        :class="drawerOpen ? 'translate-x-0' : '-translate-x-full'"
      >
        <div class="flex h-14 items-center gap-2 border-b border-gray-200 px-4 dark:border-gray-800">
          <span class="rounded bg-gray-900 px-1.5 py-0.5 text-xs font-bold text-white dark:bg-white dark:text-gray-900">
            RAAS
          </span>
          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Admin</span>
        </div>
        <AdminSidebar @navigate="drawerOpen = false" />
      </aside>

      <!-- Mobile backdrop — closing also works via Esc and the nav links -->
      <div
        v-if="drawerOpen"
        class="fixed inset-0 z-30 bg-gray-900/40 md:hidden"
        aria-hidden="true"
        @click="drawerOpen = false"
      />

      <div class="flex min-w-0 flex-1 flex-col">
        <header
          class="sticky top-0 z-20 flex h-14 items-center justify-between border-b border-gray-200 bg-white/80 px-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/80"
        >
          <button
            class="-ml-1 rounded-md p-1.5 text-gray-500 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 md:hidden dark:hover:bg-gray-800"
            aria-label="Open menu"
            @click="drawerOpen = true"
          >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" stroke-linecap="round" />
            </svg>
          </button>
          <div class="ml-auto flex items-center gap-3 text-sm">
            <span class="hidden text-gray-500 sm:inline dark:text-gray-400">{{ session.user?.email }}</span>
            <button
              class="rounded-md border border-gray-300 px-2.5 py-1 text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
              @click="logout"
            >
              Sign out
            </button>
          </div>
        </header>

        <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
          <RouterView />
        </main>
      </div>
    </div>
  </div>
</template>
