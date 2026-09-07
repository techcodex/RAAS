<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import type { AppConversation } from '@/lib/types'
import { useEmployeeStore } from '@/stores/employee'

defineEmits<{ navigate: [] }>()

const store = useEmployeeStore()
const route = useRoute()

const activeId = computed(() => Number(route.params.conversationId) || null)

function groupLabel(iso: string): string {
  const d = new Date(iso)
  const now = new Date()
  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime()
  const days = Math.floor((startOfToday - new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime()) / 86_400_000)
  if (days <= 0) return 'Today'
  if (days === 1) return 'Yesterday'
  if (days < 7) return 'Previous 7 days'
  if (days < 30) return 'Previous 30 days'
  return 'Older'
}

const groups = computed(() => {
  const out: { label: string; items: AppConversation[] }[] = []
  for (const c of store.conversations) {
    const label = groupLabel(c.updated_at)
    const last = out[out.length - 1]
    if (last && last.label === label) last.items.push(c)
    else out.push({ label, items: [c] })
  }
  return out
})
</script>

<template>
  <div class="flex h-full flex-col">
    <div class="p-3">
      <RouterLink
        :to="{ name: 'employee-app', params: { slug: store.slug } }"
        class="flex items-center justify-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
        @click="$emit('navigate')"
      >
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M12 4.5v15m7.5-7.5h-15" stroke-linecap="round" />
        </svg>
        New chat
      </RouterLink>
    </div>

    <nav class="min-h-0 flex-1 overflow-y-auto px-2 pb-3">
      <p v-if="store.conversations.length === 0" class="px-2 py-3 text-xs text-gray-400">
        No conversations yet.
      </p>
      <template v-for="group in groups" :key="group.label">
        <p class="px-2 pt-3 pb-1 text-xs font-medium tracking-wide text-gray-400 uppercase">
          {{ group.label }}
        </p>
        <RouterLink
          v-for="c in group.items"
          :key="c.id"
          :to="{ name: 'employee-conversation', params: { slug: store.slug, conversationId: c.id } }"
          class="block truncate rounded-md px-2 py-1.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
          :class="c.id === activeId
            ? 'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'"
          @click="$emit('navigate')"
        >
          {{ c.title || 'Untitled' }}
        </RouterLink>
      </template>
    </nav>
  </div>
</template>
