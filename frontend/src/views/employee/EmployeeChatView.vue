<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import ChatTranscript from '@/components/employee/ChatTranscript.vue'
import { appApi, errorMessage } from '@/lib/api'
import type { AppConversation, AppMessage } from '@/lib/types'
import { useEmployeeStore } from '@/stores/employee'

const store = useEmployeeStore()
const route = useRoute()
const router = useRouter()

const messages = ref<AppMessage[]>([])
const question = ref('')
const asking = ref(false)
const loading = ref(false)
const error = ref('')
const scrollEl = ref<HTMLElement | null>(null)

const conversationId = computed(() => Number(route.params.conversationId) || null)
const isNewChat = computed(() => conversationId.value === null)

async function scrollToBottom() {
  await nextTick()
  scrollEl.value?.scrollTo({ top: scrollEl.value.scrollHeight })
}

async function loadConversation(id: number) {
  loading.value = true
  error.value = ''
  messages.value = []
  try {
    const { data } = await appApi.get<{ data: AppConversation }>(
      `/${store.slug}/conversations/${id}`,
    )
    messages.value = data.data.messages ?? []
    scrollToBottom()
  } catch (e) {
    error.value = errorMessage(e, 'Could not load this conversation')
  } finally {
    loading.value = false
  }
}

watch(
  conversationId,
  (id) => {
    if (id) loadConversation(id)
    else {
      messages.value = []
      error.value = ''
    }
  },
  { immediate: true },
)

async function ask(text?: string) {
  const q = (text ?? question.value).trim()
  if (!q || asking.value) return

  messages.value.push({
    id: Date.now(),
    conversation_id: conversationId.value ?? 0,
    role: 'user',
    content: q,
    citations: [],
    created_at: new Date().toISOString(),
  })
  question.value = ''
  asking.value = true
  error.value = ''
  scrollToBottom()

  try {
    const { data } = await appApi.post<{ data: AppMessage; conversation_id: number }>(
      `/${store.slug}/query`,
      { question: q, conversation_id: conversationId.value },
    )
    messages.value.push(data.data)

    if (isNewChat.value) {
      await router.replace({
        name: 'employee-conversation',
        params: { slug: store.slug, conversationId: data.conversation_id },
      })
    }
    await store.loadConversations()
  } catch (e) {
    messages.value.pop()
    question.value = q
    error.value = errorMessage(e, 'Could not get an answer')
  } finally {
    asking.value = false
    scrollToBottom()
  }
}

function onComposerKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    ask()
  }
}
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col">
    <div ref="scrollEl" class="min-h-0 flex-1 overflow-y-auto">
      <div class="mx-auto w-full max-w-3xl px-4 py-8">
        <p v-if="loading" class="text-sm text-gray-500">Loading…</p>
        <p v-else-if="error && messages.length === 0" class="text-sm text-red-600">{{ error }}</p>

        <div v-else-if="isNewChat && messages.length === 0" class="pt-8 text-center">
          <h1 class="text-lg font-semibold">{{ store.publication?.name }}</h1>
          <p v-if="store.publication?.welcome_message" class="mt-1 text-sm text-gray-500">
            {{ store.publication.welcome_message }}
          </p>
          <div
            v-if="store.publication?.suggested_questions?.length"
            class="mx-auto mt-6 flex max-w-md flex-wrap justify-center gap-2"
          >
            <button
              v-for="s in store.publication.suggested_questions"
              :key="s"
              class="rounded-full border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
              @click="ask(s)"
            >
              {{ s }}
            </button>
          </div>
        </div>

        <ChatTranscript v-else :messages="messages" :pending="asking" />
      </div>
    </div>

    <div class="border-t border-gray-200 dark:border-gray-800">
      <form
        class="mx-auto flex w-full max-w-3xl items-end gap-2 px-4 py-3"
        @submit.prevent="ask()"
      >
        <textarea
          v-model="question"
          rows="1"
          placeholder="Ask a question…"
          :disabled="asking"
          class="max-h-40 flex-1 resize-none rounded-xl border border-gray-300 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800"
          @keydown="onComposerKeydown"
        />
        <button
          type="submit"
          :disabled="asking || !question.trim()"
          class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
        >
          Send
        </button>
      </form>
      <p v-if="error && messages.length > 0" class="mx-auto max-w-3xl px-4 pb-3 text-sm text-red-600">
        {{ error }}
      </p>
    </div>
  </div>
</template>
