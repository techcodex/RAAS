<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'

import { api, errorMessage } from '@/lib/api'
import type { ChatMessage } from '@/lib/types'

const props = defineProps<{ projectId: number | string; enabled: boolean }>()

const messages = ref<ChatMessage[]>([])
const conversationId = ref<number | null>(null)
const question = ref('')
const asking = ref(false)
const error = ref('')
const scrollEl = ref<HTMLElement | null>(null)

async function scrollToBottom() {
  await nextTick()
  scrollEl.value?.scrollTo({ top: scrollEl.value.scrollHeight })
}

async function ask() {
  const q = question.value.trim()
  if (!q || asking.value) return

  const optimisticUser: ChatMessage = {
    id: Date.now(),
    conversation_id: conversationId.value ?? 0,
    role: 'user',
    content: q,
    citations: [],
    usage: null,
    created_at: new Date().toISOString(),
  }
  messages.value.push(optimisticUser)
  question.value = ''
  asking.value = true
  error.value = ''
  scrollToBottom()

  try {
    const res = await api.post<{ data: ChatMessage; conversation_id: number }>(`/projects/${props.projectId}/query`, {
      question: q,
      conversation_id: conversationId.value,
    })
    conversationId.value = res.data.conversation_id
    messages.value.push(res.data.data)
  } catch (e) {
    messages.value.pop() // roll back the optimistic bubble; the question wasn't answered
    question.value = q
    error.value = errorMessage(e, 'Could not get an answer')
  } finally {
    asking.value = false
    scrollToBottom()
  }
}

function newChat() {
  conversationId.value = null
  messages.value = []
  error.value = ''
}

watch(() => props.projectId, newChat)
</script>

<template>
  <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2 dark:border-gray-800">
      <h3 class="text-sm font-medium">Ask this project</h3>
      <button v-if="messages.length" class="text-xs text-gray-500 hover:underline" @click="newChat">New chat</button>
    </div>

    <p v-if="!enabled" class="p-4 text-sm text-gray-500">
      Add an Anthropic API key above, and process at least one document, to start asking questions.
    </p>

    <template v-else>
      <div ref="scrollEl" class="max-h-96 space-y-4 overflow-y-auto p-4">
        <p v-if="messages.length === 0" class="text-sm text-gray-400">
          Ask a question about the documents in this project.
        </p>
        <div v-for="m in messages" :key="m.id" :class="m.role === 'user' ? 'text-right' : 'text-left'">
          <div
            class="inline-block max-w-[85%] rounded-lg px-3 py-2 text-left text-sm"
            :class="m.role === 'user'
              ? 'bg-indigo-600 text-white'
              : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100'"
          >
            <p class="whitespace-pre-wrap">{{ m.content }}</p>
          </div>
          <div v-if="m.citations.length" class="mt-1 space-y-1">
            <details v-for="(c, i) in m.citations" :key="i" class="text-xs text-gray-500">
              <summary class="cursor-pointer">[{{ i + 1 }}] doc #{{ c.document_id }} · chunk {{ c.chunk_index }} · score {{ c.score.toFixed(2) }}</summary>
              <p class="mt-1 whitespace-pre-wrap border-l-2 border-gray-200 pl-2 dark:border-gray-700">{{ c.excerpt }}</p>
            </details>
          </div>
        </div>
        <p v-if="asking" class="text-sm text-gray-400">Thinking…</p>
      </div>

      <form class="flex gap-2 border-t border-gray-100 p-3 dark:border-gray-800" @submit.prevent="ask">
        <input
          v-model="question"
          placeholder="Ask a question about this project's documents…"
          :disabled="asking"
          class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <button
          type="submit"
          :disabled="asking || !question.trim()"
          class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
        >
          Ask
        </button>
      </form>
      <p v-if="error" class="px-3 pb-3 text-sm text-red-600">{{ error }}</p>
    </template>
  </div>
</template>
