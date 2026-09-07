<script setup lang="ts">
import { renderMarkdown } from '@/lib/markdown'
import type { AppMessage } from '@/lib/types'

defineProps<{ messages: AppMessage[]; pending?: boolean }>()
</script>

<template>
  <div class="space-y-5">
    <div v-for="m in messages" :key="m.id" :class="m.role === 'user' ? 'text-right' : 'text-left'">
      <div
        class="inline-block max-w-[85%] rounded-2xl px-4 py-2.5 text-left text-sm"
        :class="m.role === 'user'
          ? 'bg-indigo-600 text-white'
          : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100'"
      >
        <p v-if="m.role === 'user'" class="whitespace-pre-wrap">{{ m.content }}</p>
        <div
          v-else
          class="prose prose-sm dark:prose-invert max-w-none [&>:first-child]:mt-0 [&>:last-child]:mb-0"
          v-html="renderMarkdown(m.content)"
        />
      </div>

      <details
        v-if="m.citations.length"
        class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
      >
        <summary class="cursor-pointer select-none">Sources ({{ m.citations.length }})</summary>
        <ol class="mt-1 space-y-1">
          <li
            v-for="(c, i) in m.citations"
            :key="i"
            class="border-l-2 border-gray-200 pl-2 whitespace-pre-wrap dark:border-gray-700"
          >
            [{{ i + 1 }}] {{ c.excerpt }}
          </li>
        </ol>
      </details>
    </div>

    <p v-if="pending" class="text-left text-sm text-gray-400">Thinking…</p>
  </div>
</template>
